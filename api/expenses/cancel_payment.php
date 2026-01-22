<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/services/account_service.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$expenseId = api_int($input['expense_id'] ?? null);
if (!$expenseId) {
    api_error('expense_id is required', 422);
}

$db = db();
$expenseStmt = $db->prepare('SELECT * FROM general_expenses WHERE id = ? AND deleted_at IS NULL');
$expenseStmt->execute([$expenseId]);
$expense = $expenseStmt->fetch();
if (!$expense) {
    api_error('Expense not found', 404);
}

$transferId = !empty($expense['account_transfer_id']) ? (int) $expense['account_transfer_id'] : 0;
if ($transferId <= 0) {
    api_error('Expense has no payment to cancel', 409);
}

$transferStmt = $db->prepare('SELECT status FROM account_transfers WHERE id = ?');
$transferStmt->execute([$transferId]);
$transferStatus = $transferStmt->fetchColumn();
if ($transferStatus !== 'active') {
    api_error('Expense payment is already canceled', 409);
}

$db->beginTransaction();
try {
    cancel_account_transfer($db, $transferId, 'Expense payment canceled', $user['id'] ?? null);
    $updateStmt = $db->prepare(
        'UPDATE general_expenses SET account_transfer_id = NULL, is_paid = 0, '
        . 'paid_at = NULL, paid_by_user_id = NULL, updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
    );
    $updateStmt->execute([$user['id'] ?? null, $expenseId]);
    $afterStmt = $db->prepare('SELECT * FROM general_expenses WHERE id = ?');
    $afterStmt->execute([$expenseId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'expenses.cancel_payment', 'general_expense', $expenseId, $expense, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to cancel expense payment', 500);
}

api_json(['ok' => true]);
