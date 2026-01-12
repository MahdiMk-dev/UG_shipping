<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Sub Branch']);
$input = api_read_input();
$createUser = api_bool($input['create_user'] ?? false);
$hasUserUsername = array_key_exists('user_username', $input);
$hasUserPassword = array_key_exists('user_password', $input);
$hasUserRole = array_key_exists('user_role_id', $input);
$userUsername = api_string($input['user_username'] ?? null);
$userPassword = api_string($input['user_password'] ?? null);
$userRoleId = api_int($input['user_role_id'] ?? null);

$staffId = api_int($input['staff_id'] ?? ($input['id'] ?? null));
if (!$staffId) {
    api_error('staff_id is required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT id, branch_id, user_id, name, phone, position, base_salary, status, hired_at, note '
    . 'FROM staff_members WHERE id = ? AND deleted_at IS NULL'
);
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    api_error('Staff member not found', 404);
}

$role = $user['role'] ?? '';
$fullAccess = in_array($role, ['Admin', 'Owner'], true);
if (!$fullAccess) {
    $userBranchId = $user['branch_id'] ?? null;
    if (!$userBranchId || (int) $staff['branch_id'] !== (int) $userBranchId) {
        api_error('Forbidden', 403);
    }
}

$canManageUser = $fullAccess;
$requestsUserChange = $createUser || $hasUserUsername || $hasUserPassword || $hasUserRole;
if ($requestsUserChange && !$canManageUser) {
    api_error('User login updates are restricted to Admin and Owner roles.', 403);
}

if (array_key_exists('base_salary', $input)) {
    api_error('Use salary adjustment to change base salary', 422);
}

$fields = [];
$params = [];
$name = null;
$phone = null;
$branchId = null;

if (array_key_exists('name', $input)) {
    $name = api_string($input['name'] ?? null);
    if (!$name) {
        api_error('name cannot be empty', 422);
    }
    $fields[] = 'name = ?';
    $params[] = $name;
}

if (array_key_exists('phone', $input)) {
    $phone = api_string($input['phone'] ?? null);
    if ($phone && strlen($phone) < 8) {
        api_error('phone must be at least 8 characters', 422);
    }
    $fields[] = 'phone = ?';
    $params[] = $phone;
}

if (array_key_exists('position', $input)) {
    $fields[] = 'position = ?';
    $params[] = api_string($input['position'] ?? null);
}

if (array_key_exists('branch_id', $input) && $fullAccess) {
    $branchId = api_int($input['branch_id'] ?? null);
    $fields[] = 'branch_id = ?';
    $params[] = $branchId;
}

if (array_key_exists('status', $input)) {
    $status = api_string($input['status'] ?? null);
    $allowedStatus = ['active', 'inactive'];
    if (!$status || !in_array($status, $allowedStatus, true)) {
        api_error('Invalid status', 422);
    }
    $fields[] = 'status = ?';
    $params[] = $status;
}

if (array_key_exists('hired_at', $input)) {
    $fields[] = 'hired_at = ?';
    $params[] = api_string($input['hired_at'] ?? null);
}

if (array_key_exists('note', $input)) {
    $fields[] = 'note = ?';
    $params[] = api_string($input['note'] ?? null);
}

$staffUserId = $staff['user_id'] ?? null;
if ($createUser && $staffUserId) {
    api_error('This staff member already has a user login.', 422);
}

if ($createUser) {
    if (!$userUsername || !$userPassword || !$userRoleId) {
        api_error('user_username, user_password, and user_role_id are required to create a login', 422);
    }
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

if ($hasUserUsername && !$userUsername) {
    api_error('user_username cannot be empty', 422);
}
if ($hasUserPassword && !$userPassword) {
    api_error('user_password cannot be empty', 422);
}
if ($hasUserRole && !$userRoleId) {
    api_error('user_role_id is invalid', 422);
}

$branchId = $branchId ?? $staff['branch_id'];
if ($requestsUserChange && !$createUser && !$staffUserId) {
    api_error('Create a user login before updating credentials.', 422);
}

$userUpdates = [];
$userParams = [];
$targetUserId = $staffUserId ? (int) $staffUserId : null;
if ($targetUserId) {
    $userStmt = $db->prepare('SELECT id, username FROM users WHERE id = ? AND deleted_at IS NULL');
    $userStmt->execute([$targetUserId]);
    $linkedUser = $userStmt->fetch();
    if (!$linkedUser) {
        api_error('Linked user account not found', 404);
    }
    if ($name !== null) {
        $userUpdates[] = 'name = ?';
        $userParams[] = $name;
    }
    if ($phone !== null) {
        $userUpdates[] = 'phone = ?';
        $userParams[] = $phone;
    }
    if ($branchId !== null && (int) $branchId !== (int) ($staff['branch_id'] ?? 0)) {
        $userUpdates[] = 'branch_id = ?';
        $userParams[] = $branchId;
    }
    if ($hasUserUsername) {
        if ($userUsername && $userUsername !== ($linkedUser['username'] ?? null)) {
            $dupStmt = $db->prepare('SELECT id FROM users WHERE username = ? AND id <> ? AND deleted_at IS NULL LIMIT 1');
            $dupStmt->execute([$userUsername, $targetUserId]);
            if ($dupStmt->fetch()) {
                api_error('Username already exists', 409);
            }
        }
        $userUpdates[] = 'username = ?';
        $userParams[] = $userUsername;
    }
    if ($hasUserPassword) {
        $userUpdates[] = 'password_hash = ?';
        $userParams[] = password_hash($userPassword, PASSWORD_DEFAULT);
    }
    if ($hasUserRole) {
        $roleCheck = $db->prepare('SELECT id FROM roles WHERE id = ?');
        $roleCheck->execute([$userRoleId]);
        if (!$roleCheck->fetchColumn()) {
            api_error('user_role_id is invalid', 422);
        }
        $userUpdates[] = 'role_id = ?';
        $userParams[] = $userRoleId;
    }
}

if (empty($fields) && !$createUser && empty($userUpdates)) {
    api_error('No fields to update', 422);
}

$db->beginTransaction();
try {
    if (!empty($fields)) {
        $fields[] = 'updated_at = NOW()';
        $fields[] = 'updated_by_user_id = ?';
        $params[] = $user['id'] ?? null;
        $params[] = $staffId;
        $sql = 'UPDATE staff_members SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
        $db->prepare($sql)->execute($params);
    }

    if ($createUser) {
        $userStmt = $db->prepare(
            'INSERT INTO users (name, username, password_hash, role_id, branch_id, phone, address, created_by_user_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $userStmt->execute([
            $name ?? ($staff['name'] ?? ''),
            $userUsername,
            password_hash($userPassword, PASSWORD_DEFAULT),
            $userRoleId,
            $branchId,
            $phone ?? ($staff['phone'] ?? null),
            null,
            $user['id'] ?? null,
        ]);
        $targetUserId = (int) $db->lastInsertId();
        $db->prepare('UPDATE staff_members SET user_id = ?, updated_at = NOW(), updated_by_user_id = ? WHERE id = ?')
            ->execute([$targetUserId, $user['id'] ?? null, $staffId]);
    }

    if ($targetUserId && !empty($userUpdates)) {
        $userUpdates[] = 'updated_at = NOW()';
        $userUpdates[] = 'updated_by_user_id = ?';
        $userParams[] = $user['id'] ?? null;
        $userParams[] = $targetUserId;
        $userSql = 'UPDATE users SET ' . implode(', ', $userUpdates) . ' WHERE id = ? AND deleted_at IS NULL';
        $db->prepare($userSql)->execute($userParams);
    }

    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to update staff member', 500);
}

$afterStmt = $db->prepare(
    'SELECT id, branch_id, user_id, name, phone, position, base_salary, status, hired_at, note '
    . 'FROM staff_members WHERE id = ?'
);
$afterStmt->execute([$staffId]);
$after = $afterStmt->fetch();

audit_log($user, 'staff.update', 'staff', $staffId, $staff, $after);

api_json(['ok' => true]);
