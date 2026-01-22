<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/services/account_service.php';
require_once __DIR__ . '/../../app/services/finance_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$input = api_read_input();

$branchId = api_int($input['branch_id'] ?? null);
$customerId = api_int($input['customer_id'] ?? null);
$paymentMethodId = api_int($input['payment_method_id'] ?? null);
$fromAccountId = api_int($input['from_account_id'] ?? null);
$toAccountId = api_int($input['to_account_id'] ?? null);
$type = api_string($input['type'] ?? 'payment') ?? 'payment';
$amount = api_float($input['amount'] ?? null);
$paymentDate = api_string($input['payment_date'] ?? null);
$whishPhone = api_string($input['whish_phone'] ?? null);
$reason = api_string($input['reason'] ?? null);
$note = api_string($input['note'] ?? null);
$invoiceId = api_int($input['invoice_id'] ?? null);
$currency = null;

$role = $user['role'] ?? '';
$isBranchRole = in_array($role, ['Sub Branch', 'Main Branch'], true);
$canBalanceAdjust = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
$allowedTypes = ['payment', 'deposit', 'refund', 'adjustment', 'admin_settlement', 'charge', 'discount'];
if (!in_array($type, $allowedTypes, true)) {
    api_error('Invalid transaction type', 422);
}
$isBalanceAdjust = in_array($type, ['charge', 'discount'], true);
if ($isBalanceAdjust && !$canBalanceAdjust) {
    api_error('Balance adjustments require Admin, Owner, or Main Branch access', 403);
}

if ($isBranchRole) {
    $branchId = api_int($user['branch_id'] ?? null);
}
if ($isBranchRole && !$isBalanceAdjust) {
    $fromAccountId = $fromAccountId ?: $toAccountId;
}

if (!$customerId || $amount === null) {
    api_error('customer_id and amount are required', 422);
}
if ((float) $amount === 0.0) {
    api_error('amount cannot be zero', 422);
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
if ($type === 'payment' && !$isBranchRole) {
    api_error('Customer payments can only be recorded by branch users', 403);
}
if (!$isBranchRole && $type === 'refund') {
    api_error('Customer refunds can only be recorded by branch users', 403);
}
if ($isBranchRole && !in_array($type, ['payment', 'refund'], true) && !$canBalanceAdjust) {
    api_error('Branch users can only record customer payments or refunds', 403);
}
if ($invoiceId && $type !== 'payment') {
    api_error('invoice_id can only be used for payment transactions', 422);
}
if ($isBalanceAdjust && $paymentMethodId) {
    api_error('Payment method is not allowed for balance adjustments', 422);
}

$customerStmt = db()->prepare(
    'SELECT id, is_system, sub_branch_id FROM customers WHERE id = ? AND deleted_at IS NULL'
);
$customerStmt->execute([$customerId]);
$customer = $customerStmt->fetch();
if (!$customer) {
    api_error('Customer not found', 404);
}
if ((int) $customer['is_system'] === 1) {
    api_error('Transactions are not allowed for system customers', 422);
}
if ($isBranchRole && (int) ($customer['sub_branch_id'] ?? 0) !== (int) $branchId) {
    api_error('Customer does not belong to this branch', 403);
}

$db = db();
$branchId = $branchId ?: (int) ($customer['sub_branch_id'] ?? 0);
if (!$branchId) {
    api_error('branch_id is required', 422);
}
$branchExistsStmt = $db->prepare('SELECT 1 FROM branches WHERE id = ? AND deleted_at IS NULL');
$branchExistsStmt->execute([$branchId]);
if (!$branchExistsStmt->fetchColumn()) {
    api_error('Branch not found', 404);
}

$accountMethodId = 0;
if (!$isBalanceAdjust) {
    if ($isBranchRole) {
        if (!$fromAccountId) {
            api_error('account_id is required', 422);
        }
        $account = fetch_account($db, $fromAccountId);
        if ($account['owner_type'] !== 'branch' || (int) $account['owner_id'] !== $branchId) {
            api_error('Customer transactions must use the branch account', 422);
        }
        if ($type === 'refund') {
            $toAccountId = null;
        } else {
            $toAccountId = $fromAccountId;
            $fromAccountId = null;
        }
        $accountMethodId = (int) ($account['payment_method_id'] ?? 0);
        $currency = strtoupper((string) ($account['currency'] ?? ''));
    } else {
        if (!$fromAccountId || !$toAccountId) {
            api_error('from_account_id and to_account_id are required', 422);
        }
        $fromAccount = fetch_account($db, $fromAccountId);
        $toAccount = fetch_account($db, $toAccountId);
        $expectsAdminOut = in_array($type, ['refund', 'adjustment'], true);
        if ($expectsAdminOut) {
            if ($fromAccount['owner_type'] !== 'admin') {
                api_error('Refunds and adjustments must be paid from an admin account', 422);
            }
            if ($toAccount['owner_type'] !== 'branch' || (int) $toAccount['owner_id'] !== $branchId) {
                api_error('Refunds and adjustments must be paid to the branch account', 422);
            }
        } else {
            if ($fromAccount['owner_type'] !== 'branch' || (int) $fromAccount['owner_id'] !== $branchId) {
                api_error('Customer payments must be paid from the branch account', 422);
            }
            if ($toAccount['owner_type'] !== 'admin') {
                api_error('Customer payments must be paid to an admin account', 422);
            }
        }
        $accountMethodId = (int) ($fromAccount['payment_method_id'] ?? 0);
        $fromCurrency = strtoupper((string) ($fromAccount['currency'] ?? ''));
        $toCurrency = strtoupper((string) ($toAccount['currency'] ?? ''));
        if ($fromCurrency !== $toCurrency) {
            api_error('Payment accounts must use the same currency', 422);
        }
        $currency = $fromCurrency;
    }
    if ($accountMethodId <= 0) {
        api_error('Payment accounts must have a payment method', 422);
    }
    if ($paymentMethodId && $paymentMethodId !== $accountMethodId) {
        api_error('Payment method must match the selected accounts', 422);
    }
}

$normalizedAmount = abs((float) $amount);
$invoiceForAlloc = null;
if ($invoiceId) {
    $invoiceStmt = $db->prepare(
        'SELECT id, customer_id, branch_id, total, points_discount, paid_total, due_total, status '
        . 'FROM invoices WHERE id = ? AND deleted_at IS NULL'
    );
    $invoiceStmt->execute([$invoiceId]);
    $invoiceForAlloc = $invoiceStmt->fetch();
    if (!$invoiceForAlloc) {
        api_error('Invoice not found', 404);
    }
    if ($invoiceForAlloc['status'] === 'void') {
        api_error('Cannot allocate to a void invoice', 422);
    }
    if ((int) $invoiceForAlloc['customer_id'] !== (int) $customerId) {
        api_error('Invoice customer does not match transaction customer', 422);
    }
    if ((int) $invoiceForAlloc['branch_id'] !== (int) $branchId) {
        api_error('Invoice does not belong to this branch', 403);
    }
    if ((float) $invoiceForAlloc['due_total'] + 0.0001 < $normalizedAmount) {
        api_error('Allocation exceeds invoice due_total', 422);
    }
}

$db->beginTransaction();

try {
    $stmt = $db->prepare(
        'INSERT INTO transactions '
        . '(branch_id, customer_id, type, payment_method_id, amount, currency, payment_date, whish_phone, reason, note, '
        . 'created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $branchId,
        $customerId,
        $type,
        $accountMethodId ?: null,
        $amount,
        $currency ?: 'USD',
        $paymentDate,
        $whishPhone,
        $reason ?: null,
        $note,
        $user['id'] ?? null,
    ]);
    $transactionId = (int) $db->lastInsertId();

    $balanceDelta = -$normalizedAmount;
    if (in_array($type, ['refund', 'adjustment', 'charge'], true)) {
        $balanceDelta = $normalizedAmount;
    }
    if ($type === 'discount') {
        $balanceDelta = -$normalizedAmount;
    }
    adjust_customer_balance($db, $customerId, $balanceDelta);
    record_customer_balance(
        $db,
        $customerId,
        $branchId,
        $balanceDelta,
        $type,
        'transaction',
        $transactionId,
        $user['id'] ?? null,
        $note
    );

    if (!$isBalanceAdjust) {
        $transferId = create_account_transfer(
            $db,
            $fromAccountId,
            $toAccountId,
            $normalizedAmount,
            'customer_payment',
            $paymentDate,
            $note,
            'transaction',
            $transactionId,
            $user['id'] ?? null
        );
        $db->prepare('UPDATE transactions SET account_transfer_id = ? WHERE id = ?')
            ->execute([$transferId, $transactionId]);
    }

    if ($invoiceForAlloc) {
        $allocStmt = $db->prepare(
            'INSERT INTO transaction_allocations (transaction_id, invoice_id, amount_allocated) '
            . 'VALUES (?, ?, ?)'
        );
        $allocStmt->execute([$transactionId, $invoiceId, $normalizedAmount]);

        $sumStmt = $db->prepare(
            'SELECT SUM(ta.amount_allocated) AS total_allocated '
            . 'FROM transaction_allocations ta '
            . 'JOIN transactions t ON t.id = ta.transaction_id '
            . 'WHERE ta.invoice_id = ? AND t.deleted_at IS NULL AND t.status = ?'
        );
        $sumStmt->execute([$invoiceId, 'active']);
        $sumRow = $sumStmt->fetch();
        $paidTotal = (float) ($sumRow['total_allocated'] ?? 0);
        $netTotal = (float) $invoiceForAlloc['total'] - (float) ($invoiceForAlloc['points_discount'] ?? 0);
        if ($netTotal < 0) {
            $netTotal = 0.0;
        }
        $dueTotal = $netTotal - $paidTotal;
        if ($dueTotal < 0) {
            $dueTotal = 0.0;
        }
        $status = invoice_status_from_totals($paidTotal, $netTotal);
        $updateInvoice = $db->prepare(
            'UPDATE invoices SET paid_total = ?, due_total = ?, status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE id = ?'
        );
        $updateInvoice->execute([$paidTotal, $dueTotal, $status, $user['id'] ?? null, $invoiceId]);
    }
    $rowStmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $rowStmt->execute([$transactionId]);
    $after = $rowStmt->fetch();
        audit_log($user, 'transactions.create', 'transaction', $transactionId, null, $after === false ? null : $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    error_log('transactions.create failed: ' . $e->getMessage());
    api_error('Failed to create transaction', 500, ['details' => $e->getMessage()]);
}

api_json(['ok' => true, 'id' => $transactionId]);
