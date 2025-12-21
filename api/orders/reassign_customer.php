<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$orderId = api_int($input['order_id'] ?? null);
$customerId = api_int($input['customer_id'] ?? null);

if (!$orderId || !$customerId) {
    api_error('order_id and customer_id are required', 422);
}

$customerStmt = db()->prepare('SELECT id, sub_branch_id FROM customers WHERE id = ? AND deleted_at IS NULL');
$customerStmt->execute([$customerId]);
$customer = $customerStmt->fetch();

if (!$customer) {
    api_error('Customer not found', 404);
}
if (empty($customer['sub_branch_id'])) {
    api_error('Customer has no sub branch assigned', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$orderId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Order not found', 404);
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
    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to reassign order', 500);
}

api_json(['ok' => true]);
