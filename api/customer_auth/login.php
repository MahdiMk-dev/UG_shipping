<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/customer_auth.php';

api_require_method('POST');
$input = api_read_input();

$username = api_string($input['username'] ?? null);
$password = api_string($input['password'] ?? null);

if (!$username || !$password) {
    api_error('Username and password are required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT ca.id, ca.username, ca.password_hash, ca.phone, ca.sub_branch_id '
    . 'FROM customer_accounts ca '
    . 'WHERE ca.username = ? AND ca.deleted_at IS NULL '
    . 'LIMIT 1'
);
$stmt->execute([$username]);
$row = $stmt->fetch();

if (!$row || !password_verify($password, $row['password_hash'])) {
    api_error('Invalid credentials', 401);
}

$profilesStmt = $db->prepare(
    'SELECT id FROM customers WHERE account_id = ? AND deleted_at IS NULL AND is_system = 0 LIMIT 1'
);
$profilesStmt->execute([(int) $row['id']]);
if (!$profilesStmt->fetch()) {
    api_error('No active profiles found for this account', 403);
}

$update = $db->prepare('UPDATE customer_accounts SET last_login_at = NOW() WHERE id = ?');
$update->execute([$row['id']]);

customer_auth_login([
    'account_id' => (int) $row['id'],
    'username' => $row['username'],
    'phone' => $row['phone'],
    'sub_branch_id' => $row['sub_branch_id'] ? (int) $row['sub_branch_id'] : null,
]);

api_json(['ok' => true]);
