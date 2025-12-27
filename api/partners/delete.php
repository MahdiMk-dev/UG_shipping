<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
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

$shipmentCheck = $db->prepare(
    'SELECT 1 FROM shipments WHERE deleted_at IS NULL AND (shipper_profile_id = ? OR consignee_profile_id = ?) LIMIT 1'
);
$shipmentCheck->execute([$partnerId, $partnerId]);
if ($shipmentCheck->fetch()) {
    api_error('Partner is linked to shipments and cannot be deleted', 409);
}

$invoiceCheck = $db->prepare(
    'SELECT 1 FROM partner_invoices WHERE deleted_at IS NULL AND partner_id = ? LIMIT 1'
);
$invoiceCheck->execute([$partnerId]);
if ($invoiceCheck->fetch()) {
    api_error('Partner has invoices and cannot be deleted', 409);
}

$stmt = $db->prepare(
    'UPDATE partner_profiles SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
    . 'WHERE id = ? AND deleted_at IS NULL'
);

try {
    $stmt->execute([$user['id'] ?? null, $partnerId]);
    $afterStmt = $db->prepare('SELECT * FROM partner_profiles WHERE id = ?');
    $afterStmt->execute([$partnerId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'partner.delete', 'partner_profile', $partnerId, $before, $after);
} catch (PDOException $e) {
    api_error('Failed to delete partner profile', 500);
}

api_json(['ok' => true]);
