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
$fromAccountId = api_int($input['from_account_id'] ?? null);
$paymentDate = api_string($input['payment_date'] ?? null);
$note = api_string($input['note'] ?? null);

if (!$expenseId || !$fromAccountId) {
    api_error('expense_id and from_account_id are required', 422);
}
if ($paymentDate !== null && strtotime($paymentDate) === false) {
    api_error('Invalid payment_date', 422);
}

$db = db();
$expenseStmt = $db->prepare('SELECT * FROM general_expenses WHERE id = ? AND deleted_at IS NULL');
$expenseStmt->execute([$expenseId]);
$expense = $expenseStmt->fetch();
if (!$expense) {
    api_error('Expense not found', 404);
}

$transferId = !empty($expense['account_transfer_id']) ? (int) $expense['account_transfer_id'] : null;
if (!empty($expense['is_paid'])) {
    if ($transferId) {
        $transferStatusStmt = $db->prepare('SELECT status FROM account_transfers WHERE id = ?');
        $transferStatusStmt->execute([$transferId]);
        $transferStatus = $transferStatusStmt->fetchColumn();
        if ($transferStatus === 'active') {
            api_error('Expense is already paid', 409);
        }
    } else {
        api_error('Expense is already paid', 409);
    }
}

$fromAccount = fetch_account($db, $fromAccountId);
if (($fromAccount['owner_type'] ?? '') !== 'admin') {
    api_error('Expenses must be paid from an admin account', 422);
}

$amount = (float) ($expense['amount'] ?? 0);
if ($amount <= 0.0) {
    api_error('Expense amount must be greater than zero', 422);
}

$entryType = !empty($expense['shipment_id']) ? 'shipment_expense' : 'general_expense';
$transferDate = $paymentDate ?: date('Y-m-d');

$db->beginTransaction();
try {
    $paymentTransferId = create_account_transfer(
        $db,
        $fromAccountId,
        null,
        $amount,
        $entryType,
        $transferDate,
        $note,
        'general_expense',
        $expenseId,
        $user['id'] ?? null
    );
    $updateStmt = $db->prepare(
        'UPDATE general_expenses SET account_transfer_id = ?, is_paid = 1, paid_at = NOW(), '
        . 'paid_by_user_id = ?, updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
    );
    $updateStmt->execute([$paymentTransferId, $user['id'] ?? null, $user['id'] ?? null, $expenseId]);

    $afterStmt = $db->prepare('SELECT * FROM general_expenses WHERE id = ?');
    $afterStmt->execute([$expenseId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'expenses.pay', 'general_expense', $expenseId, $expense, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to pay expense', 500);
}

api_json(['ok' => true, 'transfer_id' => $paymentTransferId]);
