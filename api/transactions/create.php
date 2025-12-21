<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$branchId = api_int($input['branch_id'] ?? null);
$customerId = api_int($input['customer_id'] ?? null);
$paymentMethodId = api_int($input['payment_method_id'] ?? null);
$type = api_string($input['type'] ?? 'payment') ?? 'payment';
$amount = api_float($input['amount'] ?? null);
$paymentDate = api_string($input['payment_date'] ?? null);
$whishPhone = api_string($input['whish_phone'] ?? null);
$note = api_string($input['note'] ?? null);

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

$customerStmt = db()->prepare('SELECT id, is_system FROM customers WHERE id = ? AND deleted_at IS NULL');
$customerStmt->execute([$customerId]);
$customer = $customerStmt->fetch();
if (!$customer) {
    api_error('Customer not found', 404);
}
if ((int) $customer['is_system'] === 1) {
    api_error('Transactions are not allowed for system customers', 422);
}

$db = db();
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

    $db->prepare('UPDATE customers SET balance = balance + ? WHERE id = ?')
        ->execute([$amount, $customerId]);

    $transactionId = (int) $db->lastInsertId();
    $rowStmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $rowStmt->execute([$transactionId]);
    $after = $rowStmt->fetch();
    audit_log($user, 'transactions.create', 'transaction', $transactionId, null, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to create transaction', 500);
}

api_json(['ok' => true, 'id' => $transactionId]);
