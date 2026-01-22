<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/finance_service.php';
require_once __DIR__ . '/../../app/services/account_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$supplierId = api_int($input['supplier_id'] ?? null);
$invoiceId = api_int($input['invoice_id'] ?? null);
$paymentMethodId = api_int($input['payment_method_id'] ?? null);
$adminAccountId = api_int($input['admin_account_id'] ?? ($input['account_id'] ?? null));
$type = api_string($input['type'] ?? 'payment') ?? 'payment';
$paymentDate = api_string($input['payment_date'] ?? null);
$reason = api_string($input['reason'] ?? null);
$note = api_string($input['note'] ?? null);
$items = $input['items'] ?? [];

if (!$supplierId) {
    api_error('supplier_id is required', 422);
}
if ($paymentDate !== null && strtotime($paymentDate) === false) {
    api_error('Invalid payment_date', 422);
}

$allowedTypes = ['payment', 'refund', 'adjustment', 'charge', 'discount'];
if (!in_array($type, $allowedTypes, true)) {
    api_error('Invalid transaction type', 422);
}
$isBalanceAdjust = in_array($type, ['charge', 'discount'], true);
$requiresAccount = !$isBalanceAdjust;
if ($requiresAccount && !$adminAccountId) {
    api_error('admin_account_id is required', 422);
}
if ($isBalanceAdjust && $paymentMethodId) {
    api_error('Payment method is not allowed for balance adjustments', 422);
}
if ($reason !== null) {
    $reason = trim($reason);
}
$allowedReasons = [
    'Damaged item',
    'Duplicate payment',
    'Order canceled',
    'Overcharge correction',
    'Service issue',
    'Other',
];
if ($reason !== null && $reason !== '' && !in_array($reason, $allowedReasons, true)) {
    api_error('Invalid refund reason', 422);
}
if ($type === 'refund' && (!$reason || $reason === '')) {
    api_error('Refund reason is required', 422);
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
if ($type === 'payment' && $amount <= 0.0) {
    api_error('payment amount must be greater than zero', 422);
}

$db = db();
$supplierStmt = $db->prepare('SELECT id FROM supplier_profiles WHERE id = ? AND deleted_at IS NULL');
$supplierStmt->execute([$supplierId]);
if (!$supplierStmt->fetch()) {
    api_error('Supplier profile not found', 404);
}

$accountMethodId = null;
if ($requiresAccount) {
    $methodStmt = $db->prepare('SELECT id FROM payment_methods WHERE id = ?');
    $methodStmt->execute([$paymentMethodId]);
    if ($paymentMethodId && !$methodStmt->fetch()) {
        api_error('Payment method not found', 404);
    }

    $adminAccount = fetch_account($db, $adminAccountId);
    if ($adminAccount['owner_type'] !== 'admin') {
        api_error('Supplier transactions must use an admin account', 422);
    }
    $accountMethodId = (int) ($adminAccount['payment_method_id'] ?? 0);
    if ($accountMethodId <= 0) {
        api_error('Payment accounts must have a payment method', 422);
    }
    if ($paymentMethodId && $paymentMethodId !== $accountMethodId) {
        api_error('Payment method must match the selected accounts', 422);
    }
}

$invoice = null;
if ($invoiceId) {
    $invStmt = $db->prepare(
        'SELECT id, supplier_id, total, paid_total, due_total, status '
        . 'FROM supplier_invoices WHERE id = ? AND deleted_at IS NULL'
    );
    $invStmt->execute([$invoiceId]);
    $invoice = $invStmt->fetch();
    if (!$invoice) {
        api_error('Invoice not found', 404);
    }
    if ((int) $invoice['supplier_id'] !== $supplierId) {
        api_error('Invoice does not belong to supplier', 422);
    }
    if (($invoice['status'] ?? '') === 'void') {
        api_error('Cannot apply payment to a void invoice', 422);
    }
    if ($type !== 'payment') {
        api_error('Only payment transactions can be applied to an invoice', 422);
    }
    $remaining = (float) ($invoice['due_total'] ?? 0);
    if ($amount > $remaining) {
        api_error('Amount exceeds invoice due total', 422);
    }
}
if ($isBalanceAdjust && $invoiceId) {
    api_error('Balance adjustments cannot be applied to invoices', 422);
}

$insertTransaction = $db->prepare(
    'INSERT INTO supplier_transactions '
    . '(supplier_id, invoice_id, branch_id, type, payment_method_id, amount, payment_date, reason, note, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$insertItem = $db->prepare(
    'INSERT INTO supplier_transaction_items (transaction_id, description, amount) VALUES (?, ?, ?)'
);

$db->beginTransaction();
try {
    $amount = abs((float) $amount);
    $insertTransaction->execute([
        $supplierId,
        $invoiceId ?: null,
        null,
        $type,
        $accountMethodId,
        $amount,
        $paymentDate,
        $reason ?: null,
        $note,
        $user['id'] ?? null,
    ]);

    $transactionId = (int) $db->lastInsertId();
    foreach ($cleanItems as $item) {
        $insertItem->execute([$transactionId, $item['description'], $item['amount']]);
    }

    $normalizedAmount = abs((float) $amount);
    if ($type === 'payment' || $type === 'charge') {
        $balanceDelta = -$normalizedAmount;
    } else {
        $balanceDelta = $normalizedAmount;
    }
    $db->prepare('UPDATE supplier_profiles SET balance = balance + ? WHERE id = ?')
        ->execute([$balanceDelta, $supplierId]);

    if ($invoice) {
        $paidTotal = (float) $invoice['paid_total'] + $normalizedAmount;
        $total = (float) $invoice['total'];
        $paidTotal = min($paidTotal, $total);
        $dueTotal = round($total - $paidTotal, 2);
        $status = invoice_status_from_totals($paidTotal, $total);
        $updateInvoice = $db->prepare(
            'UPDATE supplier_invoices SET paid_total = ?, due_total = ?, status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE id = ?'
        );
        $updateInvoice->execute([$paidTotal, $dueTotal, $status, $user['id'] ?? null, $invoiceId]);
    }

    if ($requiresAccount) {
        $transferId = create_account_transfer(
            $db,
            $adminAccountId,
            null,
            $normalizedAmount,
            'supplier_transaction',
            $paymentDate,
            $note,
            'supplier_transaction',
            $transactionId,
            $user['id'] ?? null
        );
        $db->prepare('UPDATE supplier_transactions SET account_transfer_id = ? WHERE id = ?')
            ->execute([$transferId, $transactionId]);
    }
    $rowStmt = $db->prepare('SELECT * FROM supplier_transactions WHERE id = ?');
    $rowStmt->execute([$transactionId]);
    $after = $rowStmt->fetch();
    if ($after === false) {
        $after = null;
    }
    audit_log($user, 'supplier_transactions.create', 'supplier_transaction', $transactionId, null, $after);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    error_log('supplier_transactions.create failed: ' . $e->getMessage());
    api_error('Failed to create supplier transaction', 500, ['detail' => $e->getMessage()]);
}

api_json(['ok' => true, 'id' => $transactionId]);
