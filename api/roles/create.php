<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$name = api_string($input['name'] ?? null);
if (!$name) {
    api_error('name is required', 422);
}

$stmt = db()->prepare('INSERT INTO roles (name) VALUES (?)');

try {
    $stmt->execute([$name]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        api_error('Role already exists', 409);
    }
    api_error('Failed to create role', 500);
}

api_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
