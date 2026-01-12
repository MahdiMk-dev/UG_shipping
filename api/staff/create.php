<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Sub Branch']);
$input = api_read_input();

$name = api_string($input['name'] ?? null);
$phone = api_string($input['phone'] ?? null);
$position = api_string($input['position'] ?? null);
$branchId = api_int($input['branch_id'] ?? null);
$baseSalary = api_float($input['base_salary'] ?? null) ?? 0.0;
$status = api_string($input['status'] ?? 'active') ?? 'active';
$hiredAt = api_string($input['hired_at'] ?? null);
$note = api_string($input['note'] ?? null);
$createUser = api_bool($input['create_user'] ?? false);
$userUsername = api_string($input['user_username'] ?? null);
$userPassword = api_string($input['user_password'] ?? null);
$userRoleId = api_int($input['user_role_id'] ?? null);

if (!$name) {
    api_error('name is required', 422);
}
if ($baseSalary < 0) {
    api_error('base_salary must be zero or greater', 422);
}
if ($phone && strlen($phone) < 8) {
    api_error('phone must be at least 8 characters', 422);
}

$allowedStatus = ['active', 'inactive'];
if (!in_array($status, $allowedStatus, true)) {
    api_error('Invalid status', 422);
}

$role = $user['role'] ?? '';
$fullAccess = in_array($role, ['Admin', 'Owner'], true);
if (!$fullAccess) {
    $userBranchId = $user['branch_id'] ?? null;
    if (!$userBranchId) {
        api_error('Branch scope required', 403);
    }
    $branchId = $userBranchId;
}

if (!$branchId) {
    api_error('branch_id is required', 422);
}

$canManageUser = $fullAccess;
if ($createUser) {
    if (!$canManageUser) {
        api_error('User login creation is restricted to Admin and Owner roles.', 403);
    }
    if (!$userUsername || !$userPassword || !$userRoleId) {
        api_error('user_username, user_password, and user_role_id are required to create a login', 422);
    }
}

$db = db();
if ($createUser) {
    $roleCheck = $db->prepare('SELECT id FROM roles WHERE id = ?');
    $roleCheck->execute([$userRoleId]);
    if (!$roleCheck->fetchColumn()) {
        api_error('user_role_id is invalid', 422);
    }
    $dupStmt = $db->prepare('SELECT id FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1');
    $dupStmt->execute([$userUsername]);
    if ($dupStmt->fetch()) {
        api_error('Username already exists', 409);
    }
}

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'INSERT INTO staff_members (name, phone, position, branch_id, base_salary, status, hired_at, note, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $phone,
        $position,
        $branchId,
        $baseSalary,
        $status,
        $hiredAt,
        $note,
        $user['id'] ?? null,
    ]);

    $staffId = (int) $db->lastInsertId();
    $accountStmt = $db->prepare(
        'INSERT INTO accounts (owner_type, owner_id, name, account_type, payment_method_id, created_by_user_id) '
        . 'SELECT ?, ?, CONCAT(?, \' \', pm.name), pm.name, pm.id, ? FROM payment_methods pm'
    );
    $accountStmt->execute([
        'staff',
        $staffId,
        $name,
        $user['id'] ?? null,
    ]);

    $userId = null;
    if ($createUser) {
        $userStmt = $db->prepare(
            'INSERT INTO users (name, username, password_hash, role_id, branch_id, phone, address, created_by_user_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $userStmt->execute([
            $name,
            $userUsername,
            password_hash($userPassword, PASSWORD_DEFAULT),
            $userRoleId,
            $branchId,
            $phone,
            null,
            $user['id'] ?? null,
        ]);
        $userId = (int) $db->lastInsertId();
        $db->prepare('UPDATE staff_members SET user_id = ?, updated_at = NOW(), updated_by_user_id = ? WHERE id = ?')
            ->execute([$userId, $user['id'] ?? null, $staffId]);
    }

    audit_log($user, 'staff.create', 'staff', $staffId, null, [
        'name' => $name,
        'phone' => $phone,
        'position' => $position,
        'branch_id' => $branchId,
        'base_salary' => $baseSalary,
        'status' => $status,
        'hired_at' => $hiredAt,
        'note' => $note,
        'user_id' => $userId,
    ]);

    $db->commit();
    api_json(['ok' => true, 'id' => $staffId]);
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to create staff member', 500);
}
