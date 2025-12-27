<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Sub Branch']);
$input = api_read_input();

$staffId = api_int($input['staff_id'] ?? null);
$newSalary = api_float($input['new_salary'] ?? null);
$expenseDate = api_string($input['expense_date'] ?? null);
$note = api_string($input['note'] ?? null);

if (!$staffId || $newSalary === null) {
    api_error('staff_id and new_salary are required', 422);
}
if ($newSalary < 0) {
    api_error('new_salary must be zero or greater', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT id, branch_id, base_salary FROM staff_members WHERE id = ? AND deleted_at IS NULL'
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

$currentSalary = (float) $staff['base_salary'];
if (abs($currentSalary - $newSalary) < 0.0001) {
    api_error('Salary is unchanged', 422);
}

$delta = $newSalary - $currentSalary;
$expenseDate = $expenseDate ?: date('Y-m-d');

$db->beginTransaction();
try {
    $updateStmt = $db->prepare(
        'UPDATE staff_members SET base_salary = ?, updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ? AND deleted_at IS NULL'
    );
    $updateStmt->execute([$newSalary, $user['id'] ?? null, $staffId]);

    $expenseStmt = $db->prepare(
        'INSERT INTO staff_expenses '
        . '(staff_id, branch_id, type, amount, salary_before, salary_after, expense_date, note, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $expenseStmt->execute([
        $staffId,
        $staff['branch_id'],
        'salary_adjustment',
        $delta,
        $currentSalary,
        $newSalary,
        $expenseDate,
        $note,
        $user['id'] ?? null,
    ]);

    $afterStmt = $db->prepare(
        'SELECT id, branch_id, base_salary FROM staff_members WHERE id = ?'
    );
    $afterStmt->execute([$staffId]);
    $after = $afterStmt->fetch();

    audit_log($user, 'staff.adjust_salary', 'staff', $staffId, $staff, $after, [
        'amount' => $delta,
        'expense_date' => $expenseDate,
        'note' => $note,
    ]);

    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to adjust salary', 500);
}

api_json(['ok' => true]);
