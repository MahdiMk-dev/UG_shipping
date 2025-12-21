<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$userId = api_int($input['user_id'] ?? ($input['id'] ?? null));
if (!$userId) {
    api_error('user_id is required', 422);
}

if ((int) ($user['id'] ?? 0) === $userId) {
    api_error('Cannot delete the current user', 422);
}

$stmt = db()->prepare(
    'UPDATE users SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
    . 'WHERE id = ? AND deleted_at IS NULL'
);
$stmt->execute([$user['id'] ?? null, $userId]);

if ($stmt->rowCount() === 0) {
    api_error('User not found', 404);
}

api_json(['ok' => true]);
