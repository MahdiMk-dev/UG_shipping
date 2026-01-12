<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/account_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Sub Branch']);
$input = api_read_input();

$staffId = api_int($input['staff_id'] ?? null);
$salaryMonthInput = api_string($input['salary_month'] ?? null);
$amount = api_float($input['amount'] ?? null);
$paymentDate = api_string($input['payment_date'] ?? null);
$note = api_string($input['note'] ?? null);
$fromAccountId = api_int($input['from_account_id'] ?? null);

if (!$staffId || !$salaryMonthInput || $amount === null || !$fromAccountId) {
    api_error('staff_id, salary_month, amount, and from_account_id are required', 422);
}
if ($amount <= 0) {
    api_error('amount must be greater than zero', 422);
}
if ($paymentDate !== null && strtotime($paymentDate) === false) {
    api_error('Invalid payment_date', 422);
}

if (preg_match('/^\d{4}-\d{2}$/', $salaryMonthInput)) {
    $salaryMonth = $salaryMonthInput . '-01';
} elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $salaryMonthInput)) {
    $salaryMonth = date('Y-m-01', strtotime($salaryMonthInput));
} else {
    api_error('salary_month must be YYYY-MM', 422);
}

if (strtotime($salaryMonth) === false) {
    api_error('Invalid salary_month', 422);
}

$salaryMonthStart = $salaryMonth;
$monthEnd = (new DateTime($salaryMonthStart))->modify('last day of this month')->format('Y-m-d');

$db = db();
$staffStmt = $db->prepare('SELECT id, branch_id, base_salary, hired_at FROM staff_members WHERE id = ? AND deleted_at IS NULL');
$staffStmt->execute([$staffId]);
$staff = $staffStmt->fetch();
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

$hiredAt = $staff['hired_at'] ?? null;
if ($hiredAt && strtotime($monthEnd) < strtotime($hiredAt)) {
    api_error('Salary month is before hire date', 422);
}

$existingStmt = $db->prepare(
    'SELECT 1 FROM staff_expenses WHERE staff_id = ? AND type = ? AND salary_month = ? AND deleted_at IS NULL LIMIT 1'
);
$existingStmt->execute([$staffId, 'salary_payment', $salaryMonthStart]);
if ($existingStmt->fetch()) {
    api_error('Salary already paid for this month', 409);
}

$lastPaidStmt = $db->prepare(
    'SELECT salary_month FROM staff_expenses '
    . 'WHERE staff_id = ? AND type = ? AND deleted_at IS NULL '
    . 'ORDER BY salary_month DESC, id DESC LIMIT 1'
);
$lastPaidStmt->execute([$staffId, 'salary_payment']);
$lastPaid = $lastPaidStmt->fetch();
if ($lastPaid && !empty($lastPaid['salary_month'])) {
    $lastPaidMonth = $lastPaid['salary_month'];
    if (strtotime($salaryMonthStart) <= strtotime($lastPaidMonth)) {
        api_error('Salary month must be after the last paid month', 409);
    }
} else {
    $lastPaidMonth = null;
}

$adjStmt = $db->prepare(
    'SELECT salary_after FROM staff_expenses '
    . 'WHERE staff_id = ? AND type = ? AND deleted_at IS NULL AND expense_date <= ? '
    . 'ORDER BY expense_date DESC, id DESC LIMIT 1'
);
$adjStmt->execute([$staffId, 'salary_adjustment', $monthEnd]);
$adjustment = $adjStmt->fetch();

if ($adjustment && $adjustment['salary_after'] !== null) {
    $baseSalary = (float) $adjustment['salary_after'];
} else {
    $nextAdjStmt = $db->prepare(
        'SELECT salary_before FROM staff_expenses '
        . 'WHERE staff_id = ? AND type = ? AND deleted_at IS NULL AND expense_date > ? '
        . 'ORDER BY expense_date ASC, id ASC LIMIT 1'
    );
    $nextAdjStmt->execute([$staffId, 'salary_adjustment', $monthEnd]);
    $nextAdjustment = $nextAdjStmt->fetch();
    if ($nextAdjustment && $nextAdjustment['salary_before'] !== null) {
        $baseSalary = (float) $nextAdjustment['salary_before'];
    } else {
        $baseSalary = (float) ($staff['base_salary'] ?? 0);
    }
}

if ($baseSalary <= 0) {
    api_error('Base salary must be greater than zero for this month', 422);
}

if ($lastPaidMonth) {
    $advanceStart = date('Y-m-01', strtotime($lastPaidMonth . ' +1 month'));
} else {
    $advanceStart = $hiredAt ?: null;
}

$advanceTotal = 0.0;
if ($advanceStart && strtotime($advanceStart) > strtotime($monthEnd)) {
    $advanceTotal = 0.0;
} else {
    $advanceSql = 'SELECT SUM(amount) AS total FROM staff_expenses '
        . 'WHERE staff_id = ? AND type = ? AND deleted_at IS NULL ';
    $params = [$staffId, 'advance'];
    if ($advanceStart) {
        $advanceSql .= 'AND expense_date >= ? ';
        $params[] = $advanceStart;
    }
    $advanceSql .= 'AND expense_date <= ?';
    $params[] = $monthEnd;

    $advanceStmt = $db->prepare($advanceSql);
    $advanceStmt->execute($params);
    $advanceRow = $advanceStmt->fetch();
    $advanceTotal = (float) ($advanceRow['total'] ?? 0);
}

if ($advanceTotal >= $baseSalary) {
    api_error('Advances already meet or exceed the base salary for this period', 422);
}
if (($advanceTotal + $amount) > $baseSalary) {
    api_error('Payment plus advances exceeds the base salary for this period', 422);
}

$paymentDateValue = $paymentDate ?: date('Y-m-d');

$fromAccount = fetch_account($db, $fromAccountId);
if ($fromAccount['owner_type'] !== 'admin') {
    api_error('Salary payments must be paid from an admin account', 422);
}

$db->beginTransaction();
try {
    $insertStmt = $db->prepare(
        'INSERT INTO staff_expenses '
        . '(staff_id, branch_id, type, amount, salary_before, expense_date, salary_month, note, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertStmt->execute([
        $staffId,
        $staff['branch_id'],
        'salary_payment',
        $amount,
        $baseSalary,
        $paymentDateValue,
        $salaryMonthStart,
        $note,
        $user['id'] ?? null,
    ]);

    $expenseId = (int) $db->lastInsertId();
    $transferId = create_account_transfer(
        $db,
        $fromAccountId,
        null,
        (float) $amount,
        'staff_expense',
        $paymentDateValue,
        $note,
        'staff_expense',
        $expenseId,
        $user['id'] ?? null
    );
    $db->prepare('UPDATE staff_expenses SET account_transfer_id = ? WHERE id = ?')
        ->execute([$transferId, $expenseId]);
    audit_log($user, 'staff.salary_payment', 'staff_expense', $expenseId, null, [
        'staff_id' => $staffId,
        'salary_month' => $salaryMonthStart,
        'base_salary' => $baseSalary,
        'advance_total' => $advanceTotal,
        'amount' => $amount,
        'payment_date' => $paymentDateValue,
    ]);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to record salary payment', 500);
}

api_json([
    'ok' => true,
    'amount' => $amount,
    'salary_month' => $salaryMonthStart,
    'base_salary' => $baseSalary,
    'advance_total' => $advanceTotal,
]);
