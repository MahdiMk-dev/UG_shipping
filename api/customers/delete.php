<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch', 'Warehouse']);
$input = api_read_input();

$customerId = api_int($input['customer_id'] ?? ($input['id'] ?? null));
if (!$customerId) {
    api_error('customer_id is required', 422);
}

$stmt = db()->prepare('SELECT id, is_system, sub_branch_id FROM customers WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    api_error('Customer not found', 404);
}
if ((int) $customer['is_system'] === 1) {
    api_error('System customer cannot be deleted', 422);
}

$role = $user['role'] ?? '';
$fullAccess = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
if (!$fullAccess) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId || (int) $customer['sub_branch_id'] !== (int) $branchId) {
        api_error('Forbidden', 403);
    }
}

$deleteStmt = db()->prepare(
    'UPDATE customers SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
    . 'WHERE id = ? AND deleted_at IS NULL'
);
$deleteStmt->execute([$user['id'] ?? null, $customerId]);

api_json(['ok' => true]);
