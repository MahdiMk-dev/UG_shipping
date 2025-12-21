<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$roleId = api_int($input['role_id'] ?? ($input['id'] ?? null));
$name = api_string($input['name'] ?? null);

if (!$roleId || !$name) {
    api_error('role_id and name are required', 422);
}

$stmt = db()->prepare('UPDATE roles SET name = ? WHERE id = ?');

try {
    $stmt->execute([$name, $roleId]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        api_error('Role name already exists', 409);
    }
    api_error('Failed to update role', 500);
}

if ($stmt->rowCount() === 0) {
    api_error('Role not found', 404);
}

api_json(['ok' => true]);
