<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin']);
$input = api_read_input();

$customerId = api_int($input['customer_id'] ?? ($input['id'] ?? null));
if (!$customerId) {
    api_error('customer_id is required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT id, is_system, code FROM customers WHERE id = ? AND deleted_at IS NULL'
);
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    api_error('Customer not found', 404);
}
if ((int) $customer['is_system'] === 1) {
    api_error('System customer cannot be edited', 422);
}

if (!array_key_exists('code', $input)) {
    api_error('code is required', 422);
}
$allowedKeys = ['customer_id', 'id', 'code'];
foreach (array_keys($input) as $key) {
    if (!in_array($key, $allowedKeys, true)) {
        api_error('Only code can be updated for customer profiles', 422);
    }
}

$code = api_string($input['code'] ?? null);
if (!$code) {
    api_error('code cannot be empty', 422);
}

try {
    $db->beginTransaction();

    $update = $db->prepare(
        'UPDATE customers SET code = ?, updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ? AND deleted_at IS NULL'
    );
    $update->execute([$code, $user['id'] ?? null, $customerId]);

    $db->commit();

    $after = array_merge($customer, [
        'code' => $code,
    ]);

    audit_log($user, 'customer.update', 'customer', $customerId, $customer, $after);
} catch (PDOException $e) {
    $db->rollBack();
    if ((int) $e->getCode() === 23000) {
        api_error('Customer code or profile already exists', 409);
    }
    api_error('Failed to update customer', 500);
}

api_json(['ok' => true]);
