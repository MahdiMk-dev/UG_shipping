<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/services/invoice_service.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/company.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$orderId = api_int($input['order_id'] ?? null);
$customerId = api_int($input['customer_id'] ?? null);

if (!$orderId || !$customerId) {
    api_error('order_id and customer_id are required', 422);
}

$customerStmt = db()->prepare(
    'SELECT id, sub_branch_id, profile_country_id FROM customers WHERE id = ? AND deleted_at IS NULL'
);
$customerStmt->execute([$customerId]);
$customer = $customerStmt->fetch();

if (!$customer) {
    api_error('Customer not found', 404);
}
if (empty($customer['sub_branch_id'])) {
    api_error('Customer has no sub branch assigned', 422);
}
if (empty($customer['profile_country_id'])) {
    api_error('Customer profile has no country assigned', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$orderId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Order not found', 404);
}
if (order_has_active_invoice($db, (int) $orderId)) {
    api_error('Order is already invoiced. Customer reassignment is locked.', 409);
}

$shipmentStmt = $db->prepare('SELECT origin_country_id FROM shipments WHERE id = ? AND deleted_at IS NULL');
$shipmentStmt->execute([$before['shipment_id']]);
$shipment = $shipmentStmt->fetch();
if (!$shipment) {
    api_error('Shipment not found', 404);
}
if ((int) $customer['profile_country_id'] !== (int) ($shipment['origin_country_id'] ?? 0)) {
    api_error('Customer profile country must match the shipment origin', 422);
}

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'UPDATE orders SET customer_id = ?, sub_branch_id = ?, updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$customerId, $customer['sub_branch_id'], $user['id'] ?? null, $orderId]);

    $afterStmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $afterStmt->execute([$orderId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'orders.reassign_customer', 'order', $orderId, $before, $after, [
        'new_customer_id' => $customerId,
    ]);

    $chargedStatuses = ['received_subbranch', 'with_delivery', 'picked_up'];
    if (in_array(($before['fulfillment_status'] ?? ''), $chargedStatuses, true)) {
        $orderTotal = (float) ($before['total_price'] ?? 0);
        $pointsSettings = company_points_settings();
        $pointsPrice = (float) ($pointsSettings['points_price'] ?? 0);
        adjust_customer_balance($db, (int) $before['customer_id'], -$orderTotal);
        adjust_customer_balance($db, $customerId, $orderTotal);
        adjust_customer_points_for_amount($db, (int) $before['customer_id'], -$orderTotal, $pointsPrice);
        adjust_customer_points_for_amount($db, $customerId, $orderTotal, $pointsPrice);
        record_customer_balance(
            $db,
            (int) $before['customer_id'],
            !empty($before['sub_branch_id']) ? (int) $before['sub_branch_id'] : null,
            -$orderTotal,
            'order_reversal',
            'order',
            $orderId,
            $user['id'] ?? null,
            'Order reassigned'
        );
        record_customer_balance(
            $db,
            $customerId,
            !empty($customer['sub_branch_id']) ? (int) $customer['sub_branch_id'] : null,
            $orderTotal,
            'order_charge',
            'order',
            $orderId,
            $user['id'] ?? null,
            'Order reassigned'
        );

        $oldBranchId = (int) $before['sub_branch_id'];
        $newBranchId = (int) $customer['sub_branch_id'];
        if ($oldBranchId && $newBranchId && $oldBranchId !== $newBranchId) {
            record_branch_balance(
                $db,
                $oldBranchId,
                -$orderTotal,
                'order_reversal',
                'order',
                $orderId,
                $user['id'] ?? null,
                'Order moved to another branch'
            );
            record_branch_balance(
                $db,
                $newBranchId,
                $orderTotal,
                'order_received',
                'order',
                $orderId,
                $user['id'] ?? null,
                'Order moved from another branch'
            );
        }
    }
    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to reassign order', 500);
}

api_json(['ok' => true]);
