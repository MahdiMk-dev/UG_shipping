<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Sub Branch']);
$input = api_read_input();

$staffId = api_int($input['staff_id'] ?? null);
$type = api_string($input['type'] ?? null);
$amount = api_float($input['amount'] ?? null);
$expenseDate = api_string($input['expense_date'] ?? null);
$note = api_string($input['note'] ?? null);

if (!$staffId || !$type || $amount === null) {
    api_error('staff_id, type, and amount are required', 422);
}

$allowedTypes = ['advance', 'bonus'];
if (!in_array($type, $allowedTypes, true)) {
    api_error('Invalid type', 422);
}

if ($amount <= 0) {
    api_error('amount must be greater than zero', 422);
}

$db = db();
$stmt = $db->prepare('SELECT id, branch_id FROM staff_members WHERE id = ? AND deleted_at IS NULL');
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

$expenseDate = $expenseDate ?: date('Y-m-d');

$insertStmt = $db->prepare(
    'INSERT INTO staff_expenses '
    . '(staff_id, branch_id, type, amount, expense_date, note, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$insertStmt->execute([
    $staffId,
    $staff['branch_id'],
    $type,
    $amount,
    $expenseDate,
    $note,
    $user['id'] ?? null,
]);

$expenseId = (int) $db->lastInsertId();

audit_log($user, 'staff.expense', 'staff_expense', $expenseId, null, [
    'staff_id' => $staffId,
    'type' => $type,
    'amount' => $amount,
    'expense_date' => $expenseDate,
    'note' => $note,
]);

api_json(['ok' => true, 'id' => $expenseId]);
