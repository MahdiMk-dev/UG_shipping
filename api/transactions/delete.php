<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/finance_service.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$transactionId = api_int($input['transaction_id'] ?? ($input['id'] ?? null));
if (!$transactionId) {
    api_error('transaction_id is required', 422);
}

$db = db();
$db->beginTransaction();

try {
    $txStmt = $db->prepare(
        'SELECT id, customer_id, branch_id, amount, type FROM transactions WHERE id = ? AND deleted_at IS NULL'
    );
    $txStmt->execute([$transactionId]);
    $transaction = $txStmt->fetch();

    if (!$transaction) {
        api_error('Transaction not found', 404);
    }

    $allocStmt = $db->prepare(
        'SELECT invoice_id, amount_allocated FROM transaction_allocations WHERE transaction_id = ?'
    );
    $allocStmt->execute([$transactionId]);
    $allocations = $allocStmt->fetchAll();

    if (!empty($allocations)) {
        $db->prepare('DELETE FROM transaction_allocations WHERE transaction_id = ?')
            ->execute([$transactionId]);

        $invoiceIds = array_unique(array_map(static fn ($row) => (int) $row['invoice_id'], $allocations));
        $sumStmt = $db->prepare(
            'SELECT SUM(amount_allocated) AS total_allocated FROM transaction_allocations WHERE invoice_id = ?'
        );
        $invStmt = $db->prepare('SELECT id, total FROM invoices WHERE id = ?');
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
            $sumStmt->execute([$invoiceId]);
            $sumRow = $sumStmt->fetch();
            $paidTotal = (float) ($sumRow['total_allocated'] ?? 0);
            $dueTotal = (float) $invoice['total'] - $paidTotal;
            $status = invoice_status_from_totals($paidTotal, (float) $invoice['total']);
            $updateInv->execute([$paidTotal, $dueTotal, $status, $user['id'] ?? null, $invoiceId]);
        }
    }

    $db->prepare(
        'UPDATE transactions SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
    )->execute([$user['id'] ?? null, $transactionId]);

    $normalizedAmount = abs((float) ($transaction['amount'] ?? 0));
    $balanceDelta = $normalizedAmount;
    if (in_array($transaction['type'] ?? '', ['refund', 'adjustment'], true)) {
        $balanceDelta = -$normalizedAmount;
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
        'Transaction voided'
    );

    $afterStmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $afterStmt->execute([$transactionId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'transactions.delete', 'transaction', $transactionId, $transaction, $after);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to delete transaction', 500);
}

api_json(['ok' => true]);
