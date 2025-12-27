<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$type = api_string($input['type'] ?? null);
$name = api_string($input['name'] ?? null);
$phone = api_string($input['phone'] ?? null);
$address = api_string($input['address'] ?? null);
$countryId = api_int($input['country_id'] ?? null);

if (!$type || !in_array($type, ['shipper', 'consignee'], true)) {
    api_error('type must be shipper or consignee', 422);
}
if (!$name) {
    api_error('name is required', 422);
}
if (!$countryId) {
    api_error('country_id is required', 422);
}

$countryStmt = db()->prepare('SELECT id FROM countries WHERE id = ?');
$countryStmt->execute([$countryId]);
if (!$countryStmt->fetch()) {
    api_error('Country not found', 404);
}

$db = db();
$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'INSERT INTO partner_profiles (type, name, phone, address, country_id, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $type,
        $name,
        $phone,
        $address,
        $countryId,
        $user['id'] ?? null,
    ]);
    $partnerId = (int) $db->lastInsertId();

    $rowStmt = $db->prepare('SELECT * FROM partner_profiles WHERE id = ?');
    $rowStmt->execute([$partnerId]);
    $after = $rowStmt->fetch();
    audit_log($user, 'partner.create', 'partner_profile', $partnerId, null, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to create partner profile', 500);
}

api_json(['ok' => true, 'id' => $partnerId]);
