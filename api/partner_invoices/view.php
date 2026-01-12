<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);

$invoiceId = api_int($_GET['invoice_id'] ?? ($_GET['id'] ?? null));
if (!$invoiceId) {
    api_error('invoice_id is required', 422);
}

$db = db();
$stmt = $db->prepare('SELECT * FROM partner_invoices WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();
if (!$invoice) {
    api_error('Partner invoice not found', 404);
}

$itemsStmt = $db->prepare(
    'SELECT id, description, amount FROM partner_invoice_items WHERE invoice_id = ? ORDER BY id ASC'
);
$itemsStmt->execute([$invoiceId]);
$items = $itemsStmt->fetchAll();

api_json(['ok' => true, 'invoice' => $invoice, 'items' => $items]);
