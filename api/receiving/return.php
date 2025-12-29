<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$scanId = api_int($input['scan_id'] ?? null);
if (!$scanId) {
    api_error('scan_id is required', 422);
}

$db = db();
$scanStmt = $db->prepare(
    'SELECT id, shipment_id, tracking_number FROM branch_receiving_scans '
    . 'WHERE id = ? AND match_status = \'unmatched\''
);
$scanStmt->execute([$scanId]);
$scan = $scanStmt->fetch();
if (!$scan) {
    api_error('Unmatched scan not found', 404);
}

$orderStmt = $db->prepare(
    'SELECT * FROM orders WHERE shipment_id = ? AND tracking_number = ? AND deleted_at IS NULL LIMIT 1'
);
$orderStmt->execute([(int) $scan['shipment_id'], (string) $scan['tracking_number']]);
$order = $orderStmt->fetch();
if (!$order) {
    api_error('Order not found for this scan', 404);
}

$previousStatus = (string) $order['fulfillment_status'];
$previousBranchId = (int) ($order['sub_branch_id'] ?? 0);
$previousTotal = (float) ($order['total_price'] ?? 0);
$previousCustomerId = (int) ($order['customer_id'] ?? 0);

$db->beginTransaction();
try {
    if ($previousStatus !== 'in_shipment') {
        $db->prepare(
            'UPDATE orders SET fulfillment_status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE id = ? AND deleted_at IS NULL'
        )->execute(['in_shipment', $user['id'] ?? null, (int) $order['id']]);
    }

    if ($previousStatus === 'received_subbranch' && $previousBranchId) {
        if ($previousCustomerId) {
            adjust_customer_balance($db, $previousCustomerId, -$previousTotal);
            record_customer_balance(
                $db,
                $previousCustomerId,
                $previousBranchId ?: null,
                -$previousTotal,
                'order_reversal',
                'order',
                (int) $order['id'],
                $user['id'] ?? null,
                'Returned to main branch'
            );
        }
        record_branch_balance(
            $db,
            $previousBranchId,
            -$previousTotal,
            'order_reversal',
            'order',
            (int) $order['id'],
            $user['id'] ?? null,
            'Returned to main branch'
        );
    }

    $db->prepare(
        'UPDATE branch_receiving_scans SET match_status = ?, matched_order_id = ? '
        . 'WHERE id = ? AND match_status = \'unmatched\''
    )->execute(['matched', (int) $order['id'], $scanId]);

    $afterStmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $afterStmt->execute([(int) $order['id']]);
    $after = $afterStmt->fetch();
    audit_log($user, 'receiving.return', 'order', (int) $order['id'], $order, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to return order to main branch', 500);
}

api_json(['ok' => true]);
