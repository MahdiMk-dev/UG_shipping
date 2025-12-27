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

$db = db();
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

audit_log($user, 'staff.create', 'staff', $staffId, null, [
    'name' => $name,
    'phone' => $phone,
    'position' => $position,
    'branch_id' => $branchId,
    'base_salary' => $baseSalary,
    'status' => $status,
    'hired_at' => $hiredAt,
    'note' => $note,
]);

api_json(['ok' => true, 'id' => $staffId]);
