<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$supplierId = api_int($input['id'] ?? ($input['supplier_id'] ?? null));
if (!$supplierId) {
    api_error('supplier_id is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM supplier_profiles WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$supplierId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Supplier profile not found', 404);
}

$fields = [];
$params = [];

if (array_key_exists('type', $input)) {
    $type = api_string($input['type'] ?? null);
    if (!$type || !in_array($type, ['shipper', 'consignee'], true)) {
        api_error('type must be shipper or consignee', 422);
    }
    $fields[] = 'type = ?';
    $params[] = $type;
}

if (array_key_exists('name', $input)) {
    $name = api_string($input['name'] ?? null);
    if (!$name) {
        api_error('name is required', 422);
    }
    $fields[] = 'name = ?';
    $params[] = $name;
}

if (array_key_exists('phone', $input)) {
    $fields[] = 'phone = ?';
    $params[] = api_string($input['phone'] ?? null);
}

if (array_key_exists('address', $input)) {
    $fields[] = 'address = ?';
    $params[] = api_string($input['address'] ?? null);
}

if (array_key_exists('note', $input)) {
    $fields[] = 'note = ?';
    $params[] = api_string($input['note'] ?? null);
}

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;
$params[] = $supplierId;

$sql = 'UPDATE supplier_profiles SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';

$db->beginTransaction();
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $afterStmt = $db->prepare('SELECT * FROM supplier_profiles WHERE id = ?');
    $afterStmt->execute([$supplierId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'supplier.update', 'supplier_profile', $supplierId, $before, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to update Supplier profile', 500);
}

api_json(['ok' => true]);


