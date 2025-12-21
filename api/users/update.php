<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$userId = api_int($input['user_id'] ?? ($input['id'] ?? null));
if (!$userId) {
    api_error('user_id is required', 422);
}

$fields = [];
$params = [];

if (array_key_exists('name', $input)) {
    $name = api_string($input['name'] ?? null);
    if (!$name) {
        api_error('name cannot be empty', 422);
    }
    $fields[] = 'name = ?';
    $params[] = $name;
}

if (array_key_exists('username', $input)) {
    $username = api_string($input['username'] ?? null);
    if (!$username) {
        api_error('username cannot be empty', 422);
    }
    $fields[] = 'username = ?';
    $params[] = $username;
}

if (array_key_exists('password', $input)) {
    $password = api_string($input['password'] ?? null);
    if (!$password) {
        api_error('password cannot be empty', 422);
    }
    $fields[] = 'password_hash = ?';
    $params[] = password_hash($password, PASSWORD_DEFAULT);
}

if (array_key_exists('role_id', $input)) {
    $roleId = api_int($input['role_id'] ?? null);
    if (!$roleId) {
        api_error('role_id is invalid', 422);
    }
    $fields[] = 'role_id = ?';
    $params[] = $roleId;
}

if (array_key_exists('branch_id', $input)) {
    $branchId = api_int($input['branch_id'] ?? null);
    $fields[] = 'branch_id = ?';
    $params[] = $branchId;
}

if (array_key_exists('phone', $input)) {
    $fields[] = 'phone = ?';
    $params[] = api_string($input['phone'] ?? null);
}

if (array_key_exists('address', $input)) {
    $fields[] = 'address = ?';
    $params[] = api_string($input['address'] ?? null);
}

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;
$params[] = $userId;

$sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';

try {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        api_error('Username already exists', 409);
    }
    api_error('Failed to update user', 500);
}

if ($stmt->rowCount() === 0) {
    api_error('User not found', 404);
}

api_json(['ok' => true]);
