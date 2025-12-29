<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$partnerId = api_int($input['id'] ?? ($input['partner_id'] ?? null));
if (!$partnerId) {
    api_error('partner_id is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM partner_profiles WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$partnerId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Partner profile not found', 404);
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

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;
$params[] = $partnerId;

$sql = 'UPDATE partner_profiles SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';

$db->beginTransaction();
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $afterStmt = $db->prepare('SELECT * FROM partner_profiles WHERE id = ?');
    $afterStmt->execute([$partnerId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'partner.update', 'partner_profile', $partnerId, $before, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to update partner profile', 500);
}

api_json(['ok' => true]);
