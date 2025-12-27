<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Owner']);
$input = api_read_input();

$roleId = api_int($input['role_id'] ?? ($input['id'] ?? null));
if (!$roleId) {
    api_error('role_id is required', 422);
}

$stmt = db()->prepare('DELETE FROM roles WHERE id = ?');

try {
    $stmt->execute([$roleId]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        api_error('Role is in use and cannot be deleted', 409);
    }
    api_error('Failed to delete role', 500);
}

if ($stmt->rowCount() === 0) {
    api_error('Role not found', 404);
}

api_json(['ok' => true]);
