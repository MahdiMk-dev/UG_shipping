<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/balance_service.php';

api_require_method('POST');
$user = auth_require_user();
$input = api_read_input();

$shipmentId = api_int($input['shipment_id'] ?? null);
$trackingNumber = api_string($input['tracking_number'] ?? null);
$branchId = api_int($input['branch_id'] ?? null);
$note = api_string($input['note'] ?? null);

if (!$shipmentId || !$trackingNumber) {
    api_error('shipment_id and tracking_number are required', 422);
}

$db = db();
$db->beginTransaction();

try {
    $role = $user['role'] ?? '';
    $isMainBranch = $role === 'Main Branch';
    $readOnly = is_read_only_role($user) && $role !== 'Warehouse';
    $branchScopeId = $user['branch_id'] ?? null;
    $pendingStatus = $isMainBranch ? 'in_shipment' : 'pending_receipt';
    $receivedStatus = $isMainBranch ? 'main_branch' : 'received_subbranch';

    if ($isMainBranch) {
        $shipmentStmt = $db->prepare('SELECT status FROM shipments WHERE id = ? AND deleted_at IS NULL');
        $shipmentStmt->execute([$shipmentId]);
        $shipment = $shipmentStmt->fetch();
        if (!$shipment) {
            api_error('Shipment not found', 404);
        }
        if (!in_array(($shipment['status'] ?? ''), ['arrived', 'partially_distributed'], true)) {
            api_error('Shipment is not ready for receiving', 422);
        }
    }

    $orderWhere = [
        'shipment_id = ?',
        'tracking_number = ?',
        'fulfillment_status = ?',
        'deleted_at IS NULL',
    ];
    $orderParams = [$shipmentId, $trackingNumber, $pendingStatus];

    $matchBranchId = $branchId;
    if ($readOnly && !$matchBranchId) {
        $matchBranchId = $branchScopeId ? (int) $branchScopeId : null;
    }
    if ($matchBranchId) {
        $orderWhere[] = 'sub_branch_id = ?';
        $orderParams[] = $matchBranchId;
    }

    $orderStmt = $db->prepare(
        'SELECT id, customer_id, sub_branch_id, total_price FROM orders '
        . 'WHERE ' . implode(' AND ', $orderWhere) . ' '
        . 'LIMIT 1'
    );
    $orderStmt->execute($orderParams);
    $order = $orderStmt->fetch();

    $matched = false;
    $matchedOrderId = null;
    $matchedBranchId = null;

    if ($order) {
        $matched = true;
        $matchedOrderId = (int) $order['id'];
        $matchedBranchId = $order['sub_branch_id'] ? (int) $order['sub_branch_id'] : null;
        if ($isMainBranch) {
            if ($branchScopeId) {
                $matchedBranchId = (int) $branchScopeId;
            } elseif ($branchId) {
                $matchedBranchId = $branchId;
            }
        }
        $db->prepare(
            'UPDATE orders SET fulfillment_status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE id = ?'
        )->execute([$receivedStatus, $user['id'] ?? null, $matchedOrderId]);

        if (!$isMainBranch && $matchedBranchId) {
            $customerId = (int) ($order['customer_id'] ?? 0);
            $totalPrice = (float) ($order['total_price'] ?? 0);
            adjust_customer_balance($db, $customerId, $totalPrice);
            record_customer_balance(
                $db,
                $customerId,
                $matchedBranchId,
                $totalPrice,
                'order_charge',
                'order',
                $matchedOrderId,
                $user['id'] ?? null,
                'Order received'
            );
            record_branch_balance(
                $db,
                $matchedBranchId,
                $totalPrice,
                'order_received',
                'order',
                $matchedOrderId,
                $user['id'] ?? null,
                'Order received'
            );
        }
    }

    $scanBranchId = $matchedBranchId;
    if (!$scanBranchId && $branchId) {
        $scanBranchId = $branchId;
    }
    if (!$scanBranchId && $branchScopeId) {
        $scanBranchId = (int) $branchScopeId;
    }
    if (!$scanBranchId) {
        api_error('branch_id is required for unmatched scans', 422);
    }

    if (!$matched) {
        $dupStmt = $db->prepare(
            'SELECT id FROM branch_receiving_scans '
            . 'WHERE branch_id = ? AND shipment_id = ? AND tracking_number = ? AND match_status = ? LIMIT 1'
        );
        $dupStmt->execute([$scanBranchId, $shipmentId, $trackingNumber, 'unmatched']);
        if ($dupStmt->fetch()) {
            $db->commit();
            api_json(['ok' => true, 'matched' => false, 'matched_order_id' => null, 'duplicate' => true]);
        }
    }

    $scanStmt = $db->prepare(
        'INSERT INTO branch_receiving_scans '
        . '(branch_id, shipment_id, tracking_number, scanned_by_user_id, match_status, matched_order_id, note) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $scanStmt->execute([
        $scanBranchId,
        $shipmentId,
        $trackingNumber,
        $user['id'] ?? null,
        $matched ? 'matched' : 'unmatched',
        $matchedOrderId,
        $note,
    ]);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to record scan', 500);
}

api_json(['ok' => true, 'matched' => $matched, 'matched_order_id' => $matchedOrderId]);
