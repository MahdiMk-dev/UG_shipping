<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = auth_require_user();
$input = api_read_input();

$oldPassword = api_string($input['old_password'] ?? null);
$newPassword = api_string($input['new_password'] ?? null);

if (!$oldPassword || !$newPassword) {
    api_error('old_password and new_password are required', 422);
}

$db = db();
$stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$user['id'] ?? null]);
$row = $stmt->fetch();

if (!$row || !password_verify($oldPassword, $row['password_hash'] ?? '')) {
    api_error('Old password is incorrect', 403);
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$update = $db->prepare(
    'UPDATE users SET password_hash = ?, updated_at = NOW(), updated_by_user_id = ? WHERE id = ? AND deleted_at IS NULL'
);
$update->execute([$hash, $user['id'] ?? null, $user['id'] ?? null]);

audit_log($user, 'users.change_password', 'user', (int) ($user['id'] ?? 0), null, ['self' => true]);

api_json(['ok' => true]);
