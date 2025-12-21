<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$branchId = api_int($input['branch_id'] ?? ($input['id'] ?? null));
if (!$branchId) {
    api_error('branch_id is required', 422);
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

if (array_key_exists('type', $input)) {
    $type = api_string($input['type'] ?? null);
    $allowed = ['head', 'main', 'sub', 'warehouse'];
    if (!$type || !in_array($type, $allowed, true)) {
        api_error('Invalid branch type', 422);
    }
    $fields[] = 'type = ?';
    $params[] = $type;
}

if (array_key_exists('country_id', $input)) {
    $countryId = api_int($input['country_id'] ?? null);
    if (!$countryId) {
        api_error('country_id is invalid', 422);
    }
    $fields[] = 'country_id = ?';
    $params[] = $countryId;
}

if (array_key_exists('parent_branch_id', $input)) {
    $parentBranchId = api_int($input['parent_branch_id'] ?? null);
    $fields[] = 'parent_branch_id = ?';
    $params[] = $parentBranchId;
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
$params[] = $branchId;

$sql = 'UPDATE branches SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
$stmt = db()->prepare($sql);
$stmt->execute($params);

if ($stmt->rowCount() === 0) {
    api_error('Branch not found', 404);
}

api_json(['ok' => true]);
