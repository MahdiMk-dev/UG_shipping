<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$name = api_string($input['name'] ?? null);
$username = api_string($input['username'] ?? null);
$password = api_string($input['password'] ?? null);
$roleId = api_int($input['role_id'] ?? null);
$branchId = api_int($input['branch_id'] ?? null);
$phone = api_string($input['phone'] ?? null);
$address = api_string($input['address'] ?? null);

if (!$name || !$username || !$password || !$roleId) {
    api_error('name, username, password, and role_id are required', 422);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = db()->prepare(
    'INSERT INTO users (name, username, password_hash, role_id, branch_id, phone, address, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

try {
    $stmt->execute([
        $name,
        $username,
        $passwordHash,
        $roleId,
        $branchId,
        $phone,
        $address,
        $user['id'] ?? null,
    ]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        api_error('Username already exists', 409);
    }
    api_error('Failed to create user', 500);
}

api_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
