<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/finance_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$partnerId = api_int($input['partner_id'] ?? null);
$invoiceId = api_int($input['invoice_id'] ?? null);
$paymentMethodId = api_int($input['payment_method_id'] ?? null);
$type = api_string($input['type'] ?? 'receipt') ?? 'receipt';
$paymentDate = api_string($input['payment_date'] ?? null);
$note = api_string($input['note'] ?? null);
$items = $input['items'] ?? [];

if (!$partnerId || !$paymentMethodId) {
    api_error('partner_id and payment_method_id are required', 422);
}
if ($paymentDate !== null && strtotime($paymentDate) === false) {
    api_error('Invalid payment_date', 422);
}

$allowedTypes = ['receipt', 'refund', 'adjustment'];
if (!in_array($type, $allowedTypes, true)) {
    api_error('Invalid transaction type', 422);
}

if (!is_array($items) || empty($items)) {
    api_error('Transaction line items are required', 422);
}

$cleanItems = [];
$amount = 0.0;
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $description = api_string($item['description'] ?? null);
    $lineAmount = api_float($item['amount'] ?? null);
    if (!$description) {
        api_error('Each line item must include a description', 422);
    }
    if ($lineAmount === null || (float) $lineAmount === 0.0) {
        api_error('Each line item must include a non-zero amount', 422);
    }
    $cleanItems[] = [
        'description' => $description,
        'amount' => (float) $lineAmount,
    ];
    $amount += (float) $lineAmount;
}

$amount = round($amount, 2);
if ($amount === 0.0) {
    api_error('amount cannot be zero', 422);
}
if ($type === 'receipt' && $amount <= 0.0) {
    api_error('receipt amount must be greater than zero', 422);
}

$db = db();
$partnerStmt = $db->prepare('SELECT id FROM partner_profiles WHERE id = ? AND deleted_at IS NULL');
$partnerStmt->execute([$partnerId]);
if (!$partnerStmt->fetch()) {
    api_error('Partner profile not found', 404);
}

$methodStmt = $db->prepare('SELECT id FROM payment_methods WHERE id = ?');
$methodStmt->execute([$paymentMethodId]);
if (!$methodStmt->fetch()) {
    api_error('Payment method not found', 404);
}

$invoice = null;
if ($invoiceId) {
    $invStmt = $db->prepare(
        'SELECT id, partner_id, total, paid_total, due_total, status '
        . 'FROM partner_invoices WHERE id = ? AND deleted_at IS NULL'
    );
    $invStmt->execute([$invoiceId]);
    $invoice = $invStmt->fetch();
    if (!$invoice) {
        api_error('Invoice not found', 404);
    }
    if ((int) $invoice['partner_id'] !== $partnerId) {
        api_error('Invoice does not belong to partner', 422);
    }
    if (($invoice['status'] ?? '') === 'void') {
        api_error('Cannot apply receipt to a void invoice', 422);
    }
    if ($type !== 'receipt') {
        api_error('Only receipt transactions can be applied to an invoice', 422);
    }
    $remaining = (float) ($invoice['due_total'] ?? 0);
    if ($amount > $remaining) {
        api_error('Amount exceeds invoice due total', 422);
    }
}

$insertTransaction = $db->prepare(
    'INSERT INTO partner_transactions '
    . '(partner_id, invoice_id, branch_id, type, payment_method_id, amount, payment_date, note, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$insertItem = $db->prepare(
    'INSERT INTO partner_transaction_items (transaction_id, description, amount) VALUES (?, ?, ?)'
);

$db->beginTransaction();
try {
    $insertTransaction->execute([
        $partnerId,
        $invoiceId ?: null,
        null,
        $type,
        $paymentMethodId,
        $amount,
        $paymentDate,
        $note,
        $user['id'] ?? null,
    ]);

    $transactionId = (int) $db->lastInsertId();
    foreach ($cleanItems as $item) {
        $insertItem->execute([$transactionId, $item['description'], $item['amount']]);
    }

    $db->prepare('UPDATE partner_profiles SET balance = balance + ? WHERE id = ?')
        ->execute([$amount, $partnerId]);

    if ($invoice) {
        $paidTotal = (float) $invoice['paid_total'] + $amount;
        $total = (float) $invoice['total'];
        $paidTotal = min($paidTotal, $total);
        $dueTotal = round($total - $paidTotal, 2);
        $status = invoice_status_from_totals($paidTotal, $total);
        $updateInvoice = $db->prepare(
            'UPDATE partner_invoices SET paid_total = ?, due_total = ?, status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE id = ?'
        );
        $updateInvoice->execute([$paidTotal, $dueTotal, $status, $user['id'] ?? null, $invoiceId]);
    }

    $rowStmt = $db->prepare('SELECT * FROM partner_transactions WHERE id = ?');
    $rowStmt->execute([$transactionId]);
    $after = $rowStmt->fetch();
    if ($after === false) {
        $after = null;
    }
    audit_log($user, 'partner_transactions.create', 'partner_transaction', $transactionId, null, $after);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    error_log('partner_transactions.create failed: ' . $e->getMessage());
    api_error('Failed to create partner transaction', 500, ['detail' => $e->getMessage()]);
}

api_json(['ok' => true, 'id' => $transactionId]);
