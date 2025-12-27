<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Sub Branch']);
$input = api_read_input();

$staffId = api_int($input['staff_id'] ?? ($input['id'] ?? null));
if (!$staffId) {
    api_error('staff_id is required', 422);
}

$db = db();
$stmt = $db->prepare('SELECT id, branch_id, name FROM staff_members WHERE id = ? AND deleted_at IS NULL');
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

$deleteStmt = $db->prepare(
    'UPDATE staff_members SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
    . 'WHERE id = ? AND deleted_at IS NULL'
);
$deleteStmt->execute([$user['id'] ?? null, $staffId]);

audit_log($user, 'staff.delete', 'staff', $staffId, $staff, null);

api_json(['ok' => true]);
