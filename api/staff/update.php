<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Sub Branch']);
$input = api_read_input();

$staffId = api_int($input['staff_id'] ?? ($input['id'] ?? null));
if (!$staffId) {
    api_error('staff_id is required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT id, branch_id, name, phone, position, base_salary, status, hired_at, note '
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

if (array_key_exists('base_salary', $input)) {
    api_error('Use salary adjustment to change base salary', 422);
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

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;
$params[] = $staffId;

$sql = 'UPDATE staff_members SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';

$db->prepare($sql)->execute($params);

$afterStmt = $db->prepare(
    'SELECT id, branch_id, name, phone, position, base_salary, status, hired_at, note '
    . 'FROM staff_members WHERE id = ?'
);
$afterStmt->execute([$staffId]);
$after = $afterStmt->fetch();

audit_log($user, 'staff.update', 'staff', $staffId, $staff, $after);

api_json(['ok' => true]);
