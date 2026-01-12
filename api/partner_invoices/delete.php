<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';

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
$beforeStmt = $db->prepare('SELECT * FROM partner_invoices WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$invoiceId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Partner invoice not found', 404);
}
if (($before['status'] ?? '') === 'void') {
    api_error('Invoice already void', 409);
}
$shipmentId = !empty($before['shipment_id']) ? (int) $before['shipment_id'] : null;

$receiptStmt = $db->prepare(
    'SELECT 1 FROM partner_transactions '
    . 'WHERE invoice_id = ? AND deleted_at IS NULL AND status = ? AND type = ? LIMIT 1'
);
$receiptStmt->execute([$invoiceId, 'active', 'receipt']);
if ($receiptStmt->fetchColumn()) {
    api_error('Cannot cancel an invoice with active receipts', 409);
}

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'UPDATE partner_invoices SET status = ?, paid_total = 0, due_total = 0, canceled_at = NOW(), '
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
    if ($total !== 0.0 && !empty($before['partner_id'])) {
        $db->prepare('UPDATE partner_profiles SET balance = balance - ? WHERE id = ?')
            ->execute([$total, $before['partner_id']]);
    }

    $expenseStmt = $db->prepare(
        'UPDATE general_expenses SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE reference_type = ? AND reference_id = ? AND deleted_at IS NULL'
    );
    $expenseStmt->execute([$user['id'] ?? null, 'partner_invoice', $invoiceId]);

    $afterStmt = $db->prepare('SELECT * FROM partner_invoices WHERE id = ?');
    $afterStmt->execute([$invoiceId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'partner_invoices.cancel', 'partner_invoice', $invoiceId, $before, $after, [
        'reason' => $reason,
    ]);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to cancel partner invoice', 500);
}

if ($shipmentId) {
    update_shipment_cost_per_unit($shipmentId);
}

api_json(['ok' => true]);
