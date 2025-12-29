<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$invoiceId = api_int($input['id'] ?? ($input['invoice_id'] ?? null));
if (!$invoiceId) {
    api_error('invoice_id is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM partner_invoices WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$invoiceId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Partner invoice not found', 404);
}

$paidTotal = (float) ($before['paid_total'] ?? 0);
if ($paidTotal > 0.0) {
    api_error('Cannot delete a paid invoice', 409);
}

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'UPDATE partner_invoices SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$user['id'] ?? null, $invoiceId]);

    $total = (float) ($before['total'] ?? 0);
    if ($total !== 0.0 && !empty($before['partner_id'])) {
        $db->prepare('UPDATE partner_profiles SET balance = balance + ? WHERE id = ?')
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
    audit_log($user, 'partner_invoices.delete', 'partner_invoice', $invoiceId, $before, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to delete partner invoice', 500);
}

api_json(['ok' => true]);
