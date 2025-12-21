<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch', 'Warehouse']);
$input = api_read_input();

$name = api_string($input['name'] ?? null);
$code = api_string($input['code'] ?? null);
$phone = api_string($input['phone'] ?? null);
$address = api_string($input['address'] ?? null);
$subBranchId = api_int($input['sub_branch_id'] ?? null);
$portalUsername = api_string($input['portal_username'] ?? null);
$portalPassword = api_string($input['portal_password'] ?? null);

$role = $user['role'] ?? '';
$fullAccess = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
if (!$fullAccess) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $subBranchId = $branchId;
}

if (!$name || !$code) {
    api_error('name and code are required', 422);
}

if (!$portalUsername || !$portalPassword) {
    api_error('Portal username and password are required', 422);
}

if ($phone && strlen($phone) < 8) {
    api_error('phone must be at least 8 characters', 422);
}

$db = db();
$checkUsername = $db->prepare('SELECT id FROM customer_auth WHERE username = ? LIMIT 1');
$checkUsername->execute([$portalUsername]);
if ($checkUsername->fetch()) {
    api_error('Portal username already exists', 409);
}

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'INSERT INTO customers (name, code, phone, address, sub_branch_id, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $code,
        $phone,
        $address,
        $subBranchId,
        $user['id'] ?? null,
    ]);

    $customerId = (int) $db->lastInsertId();
    $hash = password_hash($portalPassword, PASSWORD_DEFAULT);
    $authStmt = $db->prepare(
        'INSERT INTO customer_auth (customer_id, username, password_hash, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?)'
    );
    $authStmt->execute([$customerId, $portalUsername, $hash, $user['id'] ?? null]);

    $db->commit();

    audit_log(
        $user,
        'customer.create',
        'customer',
        $customerId,
        null,
        [
            'name' => $name,
            'code' => $code,
            'phone' => $phone,
            'address' => $address,
            'sub_branch_id' => $subBranchId,
            'portal_username' => $portalUsername,
        ]
    );
} catch (PDOException $e) {
    $db->rollBack();
    if ((int) $e->getCode() === 23000) {
        api_error('Customer code or portal username already exists', 409);
    }
    api_error('Failed to create customer', 500);
}

api_json(['ok' => true, 'id' => $customerId]);
