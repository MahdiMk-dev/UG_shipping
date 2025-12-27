<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$invoiceId = api_int($input['invoice_id'] ?? ($input['id'] ?? null));
if (!$invoiceId) {
    api_error('invoice_id is required', 422);
}

$db = db();
$db->beginTransaction();

try {
    $stmt = $db->prepare('SELECT id, customer_id, total, status FROM invoices WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        api_error('Invoice not found', 404);
    }

    $db->prepare(
        'UPDATE invoices SET status = ?, due_total = 0, deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ?'
    )->execute(['void', $user['id'] ?? null, $invoiceId]);

    $afterStmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $afterStmt->execute([$invoiceId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'invoices.delete', 'invoice', $invoiceId, $invoice, $after);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to delete invoice', 500);
}

api_json(['ok' => true]);
