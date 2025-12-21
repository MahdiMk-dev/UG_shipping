<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$invoiceId = api_int($input['invoice_id'] ?? ($input['id'] ?? null));
if (!$invoiceId) {
    api_error('invoice_id is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM invoices WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$invoiceId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Invoice not found', 404);
}

$fields = [];
$params = [];

if (array_key_exists('note', $input)) {
    $fields[] = 'note = ?';
    $params[] = api_string($input['note'] ?? null);
}

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;

$params[] = $invoiceId;

$sql = 'UPDATE invoices SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
try {
    $db->beginTransaction();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $afterStmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $afterStmt->execute([$invoiceId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'invoices.update', 'invoice', $invoiceId, $before, $after);
    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to update invoice', 500);
}

api_json(['ok' => true]);
