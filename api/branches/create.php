<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$name = api_string($input['name'] ?? null);
$type = api_string($input['type'] ?? null);
$countryId = api_int($input['country_id'] ?? null);
$parentBranchId = api_int($input['parent_branch_id'] ?? null);
$phone = api_string($input['phone'] ?? null);
$address = api_string($input['address'] ?? null);

if (!$name || !$type || !$countryId) {
    api_error('name, type, and country_id are required', 422);
}

$allowed = ['head', 'main', 'sub', 'warehouse'];
if (!in_array($type, $allowed, true)) {
    api_error('Invalid branch type', 422);
}

$stmt = db()->prepare(
    'INSERT INTO branches (name, type, country_id, parent_branch_id, phone, address, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
);

try {
    $stmt->execute([
        $name,
        $type,
        $countryId,
        $parentBranchId,
        $phone,
        $address,
        $user['id'] ?? null,
    ]);
} catch (PDOException $e) {
    api_error('Failed to create branch', 500);
}

api_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
