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
$note = api_string($input['note'] ?? null);

if (!$type || !in_array($type, ['shipper', 'consignee'], true)) {
    api_error('type must be shipper or consignee', 422);
}
if (!$name) {
    api_error('name is required', 422);
}

$db = db();
$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'INSERT INTO supplier_profiles (type, name, phone, address, note, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $type,
        $name,
        $phone,
        $address,
        $note,
        $user['id'] ?? null,
    ]);
    $supplierId = (int) $db->lastInsertId();

    $accountStmt = $db->prepare(
        'INSERT INTO accounts (owner_type, owner_id, name, account_type, payment_method_id, created_by_user_id) '
        . 'SELECT ?, ?, CONCAT(?, \' \', pm.name), pm.name, pm.id, ? FROM payment_methods pm'
    );
    $accountStmt->execute([
        'supplier',
        $supplierId,
        $name,
        $user['id'] ?? null,
    ]);

    $rowStmt = $db->prepare('SELECT * FROM supplier_profiles WHERE id = ?');
    $rowStmt->execute([$supplierId]);
    $after = $rowStmt->fetch();
    audit_log($user, 'supplier.create', 'supplier_profile', $supplierId, null, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to create Supplier profile', 500);
}

api_json(['ok' => true, 'id' => $supplierId]);


