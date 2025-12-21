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
    'SELECT ca.id, ca.customer_id, ca.username, ca.password_hash, c.name, c.code, c.sub_branch_id '
    . 'FROM customer_auth ca '
    . 'JOIN customers c ON c.id = ca.customer_id '
    . 'WHERE ca.username = ? AND ca.deleted_at IS NULL AND c.deleted_at IS NULL AND c.is_system = 0 '
    . 'LIMIT 1'
);
$stmt->execute([$username]);
$row = $stmt->fetch();

if (!$row || !password_verify($password, $row['password_hash'])) {
    api_error('Invalid credentials', 401);
}

$update = $db->prepare('UPDATE customer_auth SET last_login_at = NOW() WHERE id = ?');
$update->execute([$row['id']]);

customer_auth_login([
    'id' => (int) $row['id'],
    'customer_id' => (int) $row['customer_id'],
    'username' => $row['username'],
    'name' => $row['name'],
    'code' => $row['code'],
    'sub_branch_id' => $row['sub_branch_id'] ? (int) $row['sub_branch_id'] : null,
]);

api_json(['ok' => true]);
