<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/finance_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$input = api_read_input();

$transactionId = api_int($input['transaction_id'] ?? null);
$allocations = $input['allocations'] ?? null;

if (!$transactionId || !is_array($allocations)) {
    api_error('transaction_id and allocations array are required', 422);
}

$db = db();
$role = $user['role'] ?? '';
$userBranchId = null;
if ($role === 'Sub Branch') {
    $userBranchId = api_int($user['branch_id'] ?? null);
    if (!$userBranchId) {
        api_error('Branch scope required', 403);
    }
}
$db->beginTransaction();

try {
    $txStmt = $db->prepare(
        'SELECT id, customer_id, branch_id, amount FROM transactions WHERE id = ? AND deleted_at IS NULL'
    );
    $txStmt->execute([$transactionId]);
    $transaction = $txStmt->fetch();

    if (!$transaction) {
        api_error('Transaction not found', 404);
    }
    if ($userBranchId && (int) $transaction['branch_id'] !== (int) $userBranchId) {
        api_error('Forbidden', 403);
    }

    $transactionAmount = (float) $transaction['amount'];
    if ($transactionAmount <= 0) {
        api_error('Transaction amount must be positive to allocate', 422);
    }

    $totalAlloc = 0.0;
    $invoiceIds = [];

    foreach ($allocations as $allocation) {
        if (!is_array($allocation)) {
            api_error('Invalid allocation payload', 422);
        }
        $invoiceId = api_int($allocation['invoice_id'] ?? null);
        $amount = api_float($allocation['amount'] ?? null);

        if (!$invoiceId || $amount === null || $amount <= 0) {
            api_error('Each allocation requires invoice_id and positive amount', 422);
        }

        $invoiceIds[] = $invoiceId;
        $totalAlloc += $amount;
    }

    if ($totalAlloc > $transactionAmount + 0.0001) {
        api_error('Allocated amount exceeds transaction amount', 422);
    }

    $invoiceStmt = $db->prepare(
        'SELECT id, customer_id, branch_id, total, paid_total, due_total, status '
        . 'FROM invoices WHERE id = ? AND deleted_at IS NULL'
    );
    $allocStmt = $db->prepare(
        'INSERT INTO transaction_allocations (transaction_id, invoice_id, amount_allocated) '
        . 'VALUES (?, ?, ?)'
    );

    foreach ($allocations as $allocation) {
        $invoiceId = api_int($allocation['invoice_id'] ?? null);
        $amount = api_float($allocation['amount'] ?? null);

        $invoiceStmt->execute([$invoiceId]);
        $invoice = $invoiceStmt->fetch();

        if (!$invoice) {
            api_error('Invoice not found', 404, ['invoice_id' => $invoiceId]);
        }
        if ($invoice['status'] === 'void') {
            api_error('Cannot allocate to a void invoice', 422, ['invoice_id' => $invoiceId]);
        }
        if ((int) $invoice['customer_id'] !== (int) $transaction['customer_id']) {
            api_error('Invoice customer does not match transaction customer', 422, ['invoice_id' => $invoiceId]);
        }
        if ($userBranchId && (int) $invoice['branch_id'] !== (int) $userBranchId) {
            api_error('Invoice does not belong to this branch', 403, ['invoice_id' => $invoiceId]);
        }
        if ((float) $invoice['due_total'] + 0.0001 < $amount) {
            api_error('Allocation exceeds invoice due_total', 422, ['invoice_id' => $invoiceId]);
        }

        $allocStmt->execute([$transactionId, $invoiceId, $amount]);
    }

    $sumStmt = $db->prepare(
        'SELECT SUM(amount_allocated) AS total_allocated FROM transaction_allocations WHERE invoice_id = ?'
    );
    $updateInvoice = $db->prepare(
        'UPDATE invoices SET paid_total = ?, due_total = ?, status = ?, updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ?'
    );

    $uniqueInvoiceIds = array_values(array_unique($invoiceIds));
    $invoiceUpdates = [];
    foreach ($uniqueInvoiceIds as $invoiceId) {
        $invoiceStmt->execute([$invoiceId]);
        $invoice = $invoiceStmt->fetch();
        if (!$invoice) {
            continue;
        }
        $sumStmt->execute([$invoiceId]);
        $sumRow = $sumStmt->fetch();
        $paidTotal = (float) ($sumRow['total_allocated'] ?? 0);
        $dueTotal = (float) $invoice['total'] - $paidTotal;
        $status = invoice_status_from_totals($paidTotal, (float) $invoice['total']);
        $updateInvoice->execute([$paidTotal, $dueTotal, $status, $user['id'] ?? null, $invoiceId]);
        $invoiceUpdates[] = [
            'invoice_id' => $invoiceId,
            'paid_total' => $paidTotal,
            'due_total' => $dueTotal,
            'status' => $status,
        ];
    }

    $db->prepare('UPDATE transactions SET updated_at = NOW(), updated_by_user_id = ? WHERE id = ?')
        ->execute([$user['id'] ?? null, $transactionId]);

    $afterStmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $afterStmt->execute([$transactionId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'transactions.allocate', 'transaction', $transactionId, $transaction, $after, [
        'allocations' => $allocations,
        'invoice_updates' => $invoiceUpdates,
    ]);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to allocate transaction', 500);
}

api_json(['ok' => true]);
