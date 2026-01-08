<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$input = api_read_input();

$invoiceId = api_int($input['invoice_id'] ?? ($input['id'] ?? null));
$reason = api_string($input['reason'] ?? null);
if (!$invoiceId) {
    api_error('invoice_id is required', 422);
}
if (!$reason) {
    api_error('Cancellation reason is required', 422);
}

$db = db();
$db->beginTransaction();

try {
    $stmt = $db->prepare(
        'SELECT id, customer_id, branch_id, total, status FROM invoices WHERE id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        api_error('Invoice not found', 404);
    }
    if (($invoice['status'] ?? '') === 'void') {
        api_error('Invoice already void', 409);
    }

    $role = $user['role'] ?? '';
    if ($role === 'Sub Branch') {
        $userBranchId = api_int($user['branch_id'] ?? null);
        if (!$userBranchId) {
            api_error('Branch scope required', 403);
        }
        if ((int) $invoice['branch_id'] !== $userBranchId) {
            api_error('Invoice does not belong to this branch', 403);
        }
    }

    $receiptStmt = $db->prepare(
        'SELECT 1 FROM transaction_allocations ta '
        . 'JOIN transactions t ON t.id = ta.transaction_id '
        . 'WHERE ta.invoice_id = ? AND t.deleted_at IS NULL AND t.status = ? LIMIT 1'
    );
    $receiptStmt->execute([$invoiceId, 'active']);
    if ($receiptStmt->fetchColumn()) {
        api_error('Cannot cancel an invoice with active receipts', 409);
    }

    $db->prepare(
        'UPDATE invoices SET status = ?, paid_total = 0, due_total = 0, canceled_at = NOW(), '
        . 'canceled_reason = ?, canceled_by_user_id = ?, updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ?'
    )->execute([
        'void',
        $reason,
        $user['id'] ?? null,
        $user['id'] ?? null,
        $invoiceId,
    ]);

    $afterStmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $afterStmt->execute([$invoiceId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'invoices.cancel', 'invoice', $invoiceId, $invoice, $after, [
        'reason' => $reason,
    ]);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to cancel invoice', 500);
}

api_json(['ok' => true]);
