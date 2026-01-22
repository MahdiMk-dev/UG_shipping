<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch']);

$invoiceId = api_int($_GET['id'] ?? null);
if (!$invoiceId) {
    api_error('id is required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT i.*, s.shipment_number, s.weight AS shipment_weight, s.size AS shipment_volume '
    . 'FROM supplier_invoices i '
    . 'LEFT JOIN shipments s ON s.id = i.shipment_id '
    . 'WHERE i.id = ? AND i.deleted_at IS NULL'
);
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();
if (!$invoice) {
    api_error('Supplier invoice not found', 404);
}

api_json(['ok' => true, 'invoice' => $invoice]);
