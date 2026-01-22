<?php
declare(strict_types=1);

require_once __DIR__ . '/account_service.php';

function sync_invoice_points_expense(
    PDO $db,
    int $invoiceId,
    string $invoiceNo,
    float $amount,
    ?int $userId
): void {
    $expenseStmt = $db->prepare(
        'SELECT id, amount, account_transfer_id FROM general_expenses '
        . 'WHERE reference_type = ? AND reference_id = ? AND deleted_at IS NULL '
        . 'ORDER BY id DESC LIMIT 1'
    );
    $expenseStmt->execute(['invoice_points', $invoiceId]);
    $expense = $expenseStmt->fetch();

    $note = 'Points deduction - Invoice ' . $invoiceNo;
    $title = 'Points deduction';

    if ($amount <= 0.0001) {
        if ($expense) {
            $transferId = (int) ($expense['account_transfer_id'] ?? 0);
            if ($transferId) {
                cancel_account_transfer($db, $transferId, 'Points expense removed', $userId);
            }
            $db->prepare(
                'UPDATE general_expenses SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
                . 'WHERE id = ?'
            )->execute([$userId, (int) $expense['id']]);
        }
        return;
    }

    if ($expense) {
        $expenseId = (int) $expense['id'];
        $transferId = (int) ($expense['account_transfer_id'] ?? 0);
        if ($transferId) {
            cancel_account_transfer($db, $transferId, 'Points expense updated', $userId);
        }
        $db->prepare(
            'UPDATE general_expenses SET title = ?, amount = ?, note = ?, updated_at = NOW(), '
            . 'updated_by_user_id = ?, account_transfer_id = NULL, is_paid = 1, paid_at = NOW(), '
            . 'paid_by_user_id = ? WHERE id = ?'
        )->execute([$title, $amount, $note, $userId, $userId, $expenseId]);
        return;
    }

    $insertStmt = $db->prepare(
        'INSERT INTO general_expenses '
        . '(branch_id, shipment_id, title, amount, expense_date, note, reference_type, reference_id, is_paid, paid_at, '
        . 'paid_by_user_id, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)'
    );
    $insertStmt->execute([
        null,
        null,
        $title,
        $amount,
        null,
        $note,
        'invoice_points',
        $invoiceId,
        1,
        $userId,
        $userId,
    ]);
}
