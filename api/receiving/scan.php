<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

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
    $readOnly = is_read_only_role($user) && $role !== 'Warehouse';
    $branchScopeId = $user['branch_id'] ?? null;

    $orderWhere = [
        'shipment_id = ?',
        'tracking_number = ?',
        'fulfillment_status = ?',
        'deleted_at IS NULL',
    ];
    $orderParams = [$shipmentId, $trackingNumber, 'pending_receipt'];

    $matchBranchId = $branchId;
    if ($readOnly && !$matchBranchId) {
        $matchBranchId = $branchScopeId ? (int) $branchScopeId : null;
    }
    if ($matchBranchId) {
        $orderWhere[] = 'sub_branch_id = ?';
        $orderParams[] = $matchBranchId;
    }

    $orderStmt = $db->prepare(
        'SELECT id, sub_branch_id FROM orders '
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
        $db->prepare(
            'UPDATE orders SET fulfillment_status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE id = ?'
        )->execute(['received_subbranch', $user['id'] ?? null, $matchedOrderId]);
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
