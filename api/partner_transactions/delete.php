<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/finance_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
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
        'SELECT id, partner_id, invoice_id, type, amount, status '
        . 'FROM partner_transactions WHERE id = ? AND deleted_at IS NULL'
    );
    $txStmt->execute([$transactionId]);
    $transaction = $txStmt->fetch();

    if (!$transaction) {
        api_error('Transaction not found', 404);
    }
    if (($transaction['status'] ?? '') !== 'active') {
        api_error('Transaction already canceled', 409);
    }

    $db->prepare(
        'UPDATE partner_transactions SET status = ?, canceled_at = NOW(), canceled_reason = ?, '
        . 'canceled_by_user_id = ?, updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
    )->execute([
        'canceled',
        $reason,
        $user['id'] ?? null,
        $user['id'] ?? null,
        $transactionId,
    ]);

    $amount = (float) ($transaction['amount'] ?? 0);
    if (!empty($transaction['partner_id']) && abs($amount) > 0.0001) {
        $db->prepare('UPDATE partner_profiles SET balance = balance - ? WHERE id = ?')
            ->execute([$amount, $transaction['partner_id']]);
    }

    if (!empty($transaction['invoice_id'])) {
        $invoiceId = (int) $transaction['invoice_id'];
        $sumStmt = $db->prepare(
            'SELECT SUM(amount) AS total_receipts FROM partner_transactions '
            . 'WHERE invoice_id = ? AND deleted_at IS NULL AND status = ? AND type = ?'
        );
        $sumStmt->execute([$invoiceId, 'active', 'receipt']);
        $sumRow = $sumStmt->fetch();
        $paidTotal = (float) ($sumRow['total_receipts'] ?? 0);

        $invStmt = $db->prepare('SELECT id, total FROM partner_invoices WHERE id = ? AND deleted_at IS NULL');
        $invStmt->execute([$invoiceId]);
        $invoice = $invStmt->fetch();
        if ($invoice) {
            $total = (float) $invoice['total'];
            $paidTotal = min($paidTotal, $total);
            $dueTotal = round($total - $paidTotal, 2);
            $status = invoice_status_from_totals($paidTotal, $total);
            $updateInvoice = $db->prepare(
                'UPDATE partner_invoices SET paid_total = ?, due_total = ?, status = ?, '
                . 'updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
            );
            $updateInvoice->execute([$paidTotal, $dueTotal, $status, $user['id'] ?? null, $invoiceId]);
        }
    }

    $afterStmt = $db->prepare('SELECT * FROM partner_transactions WHERE id = ?');
    $afterStmt->execute([$transactionId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'partner_transactions.cancel', 'partner_transaction', $transactionId, $transaction, $after, [
        'reason' => $reason,
    ]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    api_error('Failed to cancel partner transaction', 500);
}

api_json(['ok' => true]);
