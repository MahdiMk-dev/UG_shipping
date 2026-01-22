<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
require_once __DIR__ . '/../../app/services/account_service.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$invoiceId = api_int($input['id'] ?? ($input['invoice_id'] ?? null));
$reason = api_string($input['reason'] ?? null);
if (!$invoiceId) {
    api_error('invoice_id is required', 422);
}
if (!$reason) {
    api_error('Cancellation reason is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM supplier_invoices WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$invoiceId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Supplier invoice not found', 404);
}
if (($before['status'] ?? '') === 'void') {
    api_error('Invoice already void', 409);
}
$shipmentId = !empty($before['shipment_id']) ? (int) $before['shipment_id'] : null;

$paymentStmt = $db->prepare(
    'SELECT 1 FROM supplier_transactions '
    . 'WHERE invoice_id = ? AND deleted_at IS NULL AND status = ? AND type = ? LIMIT 1'
);
$paymentStmt->execute([$invoiceId, 'active', 'payment']);
if ($paymentStmt->fetchColumn()) {
    api_error('Cannot cancel an invoice with active payments', 409);
}

$expenseLookup = $db->prepare(
    'SELECT id, account_transfer_id FROM general_expenses '
    . 'WHERE reference_type = ? AND reference_id = ? AND deleted_at IS NULL LIMIT 1'
);
$expenseLookup->execute(['supplier_invoice', $invoiceId]);
$expense = $expenseLookup->fetch();

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'UPDATE supplier_invoices SET status = ?, paid_total = 0, due_total = 0, canceled_at = NOW(), '
        . 'canceled_reason = ?, canceled_by_user_id = ?, updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([
        'void',
        $reason,
        $user['id'] ?? null,
        $user['id'] ?? null,
        $invoiceId,
    ]);

    $total = (float) ($before['total'] ?? 0);
    if ($total !== 0.0 && !empty($before['supplier_id'])) {
        $db->prepare('UPDATE supplier_profiles SET balance = balance - ? WHERE id = ?')
            ->execute([$total, $before['supplier_id']]);
    }

    if ($expense) {
        $currentTransferId = !empty($expense['account_transfer_id']) ? (int) $expense['account_transfer_id'] : null;
        if ($currentTransferId) {
            cancel_account_transfer($db, $currentTransferId, 'Supplier invoice canceled', $user['id'] ?? null);
        }
        $expenseStmt = $db->prepare(
            'UPDATE general_expenses SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
        );
        $expenseStmt->execute([$user['id'] ?? null, $expense['id']]);
    }

    $afterStmt = $db->prepare('SELECT * FROM supplier_invoices WHERE id = ?');
    $afterStmt->execute([$invoiceId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'supplier_invoices.cancel', 'supplier_invoice', $invoiceId, $before, $after, [
        'reason' => $reason,
    ]);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to cancel Supplier invoice', 500);
}

if ($shipmentId) {
    update_shipment_cost_per_unit($shipmentId);
}

api_json(['ok' => true]);


