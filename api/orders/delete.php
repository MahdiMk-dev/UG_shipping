<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/services/invoice_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$orderId = api_int($input['order_id'] ?? ($input['id'] ?? null));
if (!$orderId) {
    api_error('order_id is required', 422);
}

$db = db();
$orderStmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND deleted_at IS NULL');
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();
if (!$order) {
    api_error('Order not found', 404);
}

if (order_has_active_invoice($db, (int) $orderId)) {
    api_error('Order is already invoiced. Delete the invoice before deleting the order.', 409);
}

$db->beginTransaction();

try {
    $stmt = $db->prepare(
        'UPDATE orders SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$user['id'] ?? null, $orderId]);

    $afterStmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $afterStmt->execute([$orderId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'orders.delete', 'order', $orderId, $order, $after);

    adjust_customer_balance($db, (int) $order['customer_id'], (float) $order['total_price']);
    record_customer_balance(
        $db,
        (int) $order['customer_id'],
        !empty($order['sub_branch_id']) ? (int) $order['sub_branch_id'] : null,
        (float) $order['total_price'],
        'order_reversal',
        'order',
        $orderId,
        $user['id'] ?? null,
        'Order deleted'
    );
    if (($order['fulfillment_status'] ?? '') === 'received_subbranch') {
        record_branch_balance(
            $db,
            (int) $order['sub_branch_id'],
            -(float) $order['total_price'],
            'order_reversal',
            'order',
            $orderId,
            $user['id'] ?? null,
            'Order deleted'
        );
    }

    update_shipment_totals((int) $order['shipment_id']);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to delete order', 500);
}

api_json(['ok' => true]);
