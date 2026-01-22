<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
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

$shipmentCheck = $db->prepare(
    'SELECT 1 FROM shipments WHERE deleted_at IS NULL AND (shipper_profile_id = ? OR consignee_profile_id = ?) LIMIT 1'
);
$shipmentCheck->execute([$supplierId, $supplierId]);
if ($shipmentCheck->fetch()) {
    api_error('Supplier is linked to shipments and cannot be deleted', 409);
}

$invoiceCheck = $db->prepare(
    'SELECT 1 FROM supplier_invoices WHERE deleted_at IS NULL AND supplier_id = ? LIMIT 1'
);
$invoiceCheck->execute([$supplierId]);
if ($invoiceCheck->fetch()) {
    api_error('Supplier has invoices and cannot be deleted', 409);
}

$stmt = $db->prepare(
    'UPDATE supplier_profiles SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
    . 'WHERE id = ? AND deleted_at IS NULL'
);

try {
    $stmt->execute([$user['id'] ?? null, $supplierId]);
    $afterStmt = $db->prepare('SELECT * FROM supplier_profiles WHERE id = ?');
    $afterStmt->execute([$supplierId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'supplier.delete', 'supplier_profile', $supplierId, $before, $after);
} catch (PDOException $e) {
    api_error('Failed to delete Supplier profile', 500);
}

api_json(['ok' => true]);


