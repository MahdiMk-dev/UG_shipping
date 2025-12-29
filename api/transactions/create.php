<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$input = api_read_input();

$branchId = api_int($input['branch_id'] ?? null);
$customerId = api_int($input['customer_id'] ?? null);
$paymentMethodId = api_int($input['payment_method_id'] ?? null);
$type = api_string($input['type'] ?? 'payment') ?? 'payment';
$amount = api_float($input['amount'] ?? null);
$paymentDate = api_string($input['payment_date'] ?? null);
$whishPhone = api_string($input['whish_phone'] ?? null);
$note = api_string($input['note'] ?? null);

$role = $user['role'] ?? '';

if ($role === 'Sub Branch') {
    $userBranchId = api_int($user['branch_id'] ?? null);
    if (!$userBranchId) {
        api_error('Branch scope required', 403);
    }
    if ($branchId && $branchId !== $userBranchId) {
        api_error('Forbidden', 403);
    }
    if (!$branchId) {
        $branchId = $userBranchId;
    }
}

if (!$branchId || !$customerId || !$paymentMethodId || $amount === null) {
    api_error('branch_id, customer_id, payment_method_id, and amount are required', 422);
}
if ((float) $amount === 0.0) {
    api_error('amount cannot be zero', 422);
}

$allowedTypes = ['payment', 'deposit', 'refund', 'adjustment', 'admin_settlement'];
if (!in_array($type, $allowedTypes, true)) {
    api_error('Invalid transaction type', 422);
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
if ($role === 'Sub Branch' && (int) ($customer['sub_branch_id'] ?? 0) !== (int) $branchId) {
    api_error('Customer does not belong to this branch', 403);
}

$db = db();
$branchTypeStmt = $db->prepare('SELECT type FROM branches WHERE id = ? AND deleted_at IS NULL');
$branchTypeStmt->execute([$branchId]);
$branchType = $branchTypeStmt->fetchColumn();
if (!$branchType) {
    api_error('Branch not found', 404);
}

$db->beginTransaction();

try {
    $stmt = $db->prepare(
        'INSERT INTO transactions '
        . '(branch_id, customer_id, type, payment_method_id, amount, payment_date, whish_phone, note, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $branchId,
        $customerId,
        $type,
        $paymentMethodId,
        $amount,
        $paymentDate,
        $whishPhone,
        $note,
        $user['id'] ?? null,
    ]);

    $normalizedAmount = abs((float) $amount);
    $balanceDelta = -$normalizedAmount;
    if (in_array($type, ['refund', 'adjustment'], true)) {
        $balanceDelta = $normalizedAmount;
    }
    adjust_customer_balance($db, $customerId, $balanceDelta);

    $transactionId = (int) $db->lastInsertId();
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

    if (in_array($role, ['Admin', 'Owner', 'Main Branch'], true)
        && $branchType === 'sub'
        && in_array($type, ['payment', 'deposit', 'admin_settlement'], true)
    ) {
        record_branch_balance(
            $db,
            $branchId,
            -$normalizedAmount,
            'customer_payment',
            'transaction',
            $transactionId,
            $user['id'] ?? null,
            'Customer payment'
        );
    }
    $rowStmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $rowStmt->execute([$transactionId]);
    $after = $rowStmt->fetch();
        audit_log($user, 'transactions.create', 'transaction', $transactionId, null, $after === false ? null : $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to create transaction', 500);
}

api_json(['ok' => true, 'id' => $transactionId]);
