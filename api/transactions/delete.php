<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/finance_service.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/services/account_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$input = api_read_input();

$transactionId = api_int($input['transaction_id'] ?? ($input['id'] ?? null));
$reason = api_string($input['reason'] ?? null);
if (!$transactionId) {
    api_error('transaction_id is required', 422);
}
if (!$reason) {
    api_error('Cancellation reason is required', 422);
}

$db = db();
$db->beginTransaction();

try {
    $txStmt = $db->prepare(
        'SELECT id, customer_id, branch_id, amount, type, status, account_transfer_id '
        . 'FROM transactions WHERE id = ? AND deleted_at IS NULL'
    );
    $txStmt->execute([$transactionId]);
    $transaction = $txStmt->fetch();

    if (!$transaction) {
        api_error('Transaction not found', 404);
    }
    if (($transaction['status'] ?? '') !== 'active') {
        api_error('Transaction already canceled', 409);
    }

    $role = $user['role'] ?? '';
    if ($role === 'Sub Branch') {
        $userBranchId = api_int($user['branch_id'] ?? null);
        if (!$userBranchId) {
            api_error('Branch scope required', 403);
        }
        if ((int) $transaction['branch_id'] !== $userBranchId) {
            api_error('Transaction does not belong to this branch', 403);
        }
    }

    $branchTypeStmt = $db->prepare('SELECT 1 FROM branches WHERE id = ? AND deleted_at IS NULL');
    $branchTypeStmt->execute([(int) $transaction['branch_id']]);
    if (!$branchTypeStmt->fetchColumn()) {
        api_error('Branch not found', 404);
    }

    $allocStmt = $db->prepare('SELECT invoice_id FROM transaction_allocations WHERE transaction_id = ?');
    $allocStmt->execute([$transactionId]);
    $allocations = $allocStmt->fetchAll();

    if (!empty($allocations)) {
        $invoiceIds = array_unique(array_map(static fn ($row) => (int) $row['invoice_id'], $allocations));
        $sumStmt = $db->prepare(
            'SELECT SUM(ta.amount_allocated) AS total_allocated '
            . 'FROM transaction_allocations ta '
            . 'JOIN transactions t ON t.id = ta.transaction_id '
            . 'WHERE ta.invoice_id = ? AND t.deleted_at IS NULL AND t.status = ?'
        );
        $invStmt = $db->prepare(
            'SELECT id, total, points_discount, status FROM invoices WHERE id = ? AND deleted_at IS NULL'
        );
        $updateInv = $db->prepare(
            'UPDATE invoices SET paid_total = ?, due_total = ?, status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE id = ?'
        );

        foreach ($invoiceIds as $invoiceId) {
            $invStmt->execute([$invoiceId]);
            $invoice = $invStmt->fetch();
            if (!$invoice) {
                continue;
            }
            $sumStmt->execute([$invoiceId, 'active']);
            $sumRow = $sumStmt->fetch();
            $paidTotal = (float) ($sumRow['total_allocated'] ?? 0);
            $netTotal = (float) $invoice['total'] - (float) ($invoice['points_discount'] ?? 0);
            if ($netTotal < 0) {
                $netTotal = 0.0;
            }
            $dueTotal = $netTotal - $paidTotal;
            if ($dueTotal < 0) {
                $dueTotal = 0.0;
            }
            $status = invoice_status_from_totals($paidTotal, $netTotal);
            $updateInv->execute([$paidTotal, $dueTotal, $status, $user['id'] ?? null, $invoiceId]);
        }
    }

    $db->prepare(
        'UPDATE transactions SET status = ?, canceled_at = NOW(), canceled_reason = ?, canceled_by_user_id = ?, '
        . 'updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
    )->execute([
        'canceled',
        $reason,
        $user['id'] ?? null,
        $user['id'] ?? null,
        $transactionId,
    ]);

    $normalizedAmount = abs((float) ($transaction['amount'] ?? 0));
    $balanceDelta = -$normalizedAmount;
    if (in_array($transaction['type'] ?? '', ['refund', 'adjustment'], true)) {
        $balanceDelta = $normalizedAmount;
    }
    adjust_customer_balance($db, (int) $transaction['customer_id'], -$balanceDelta);
    record_customer_balance(
        $db,
        (int) $transaction['customer_id'],
        (int) ($transaction['branch_id'] ?? 0),
        -$balanceDelta,
        (string) ($transaction['type'] ?? 'payment'),
        'transaction',
        $transactionId,
        $user['id'] ?? null,
        'Transaction canceled: ' . $reason
    );

    $branchBalanceDelta = -$normalizedAmount;
    if (in_array($transaction['type'] ?? '', ['refund', 'adjustment'], true)) {
        $branchBalanceDelta = $normalizedAmount;
    }
    record_branch_balance(
        $db,
        (int) ($transaction['branch_id'] ?? 0),
        -$branchBalanceDelta,
        'customer_payment',
        'transaction',
        $transactionId,
        $user['id'] ?? null,
        'Transaction canceled: ' . $reason
    );

    if (!empty($transaction['account_transfer_id'])) {
        cancel_account_transfer(
            $db,
            (int) $transaction['account_transfer_id'],
            $reason,
            $user['id'] ?? null
        );
    }

    $afterStmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $afterStmt->execute([$transactionId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'transactions.cancel', 'transaction', $transactionId, $transaction, $after, [
        'reason' => $reason,
    ]);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to cancel transaction', 500);
}

api_json(['ok' => true]);
