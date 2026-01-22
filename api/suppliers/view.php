<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch']);

$supplierId = api_int($_GET['id'] ?? ($_GET['supplier_id'] ?? null));
if (!$supplierId) {
    api_error('supplier_id is required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT p.* '
    . 'FROM supplier_profiles p '
    . 'WHERE p.id = ? AND p.deleted_at IS NULL'
);
$stmt->execute([$supplierId]);
$supplier = $stmt->fetch();
if (!$supplier) {
    api_error('Supplier profile not found', 404);
}

$shipmentsStmt = $db->prepare(
    'SELECT s.id, s.shipment_number, s.status, s.origin_country_id, c.name AS origin_country, '
    . 'CASE '
    . 'WHEN s.shipper_profile_id = ? THEN \'shipper\' '
    . 'WHEN s.consignee_profile_id = ? THEN \'consignee\' '
    . 'ELSE NULL '
    . 'END AS supplier_role '
    . 'FROM shipments s '
    . 'LEFT JOIN countries c ON c.id = s.origin_country_id '
    . 'WHERE s.deleted_at IS NULL AND (s.shipper_profile_id = ? OR s.consignee_profile_id = ?) '
    . 'ORDER BY s.id DESC LIMIT 50'
);
$shipmentsStmt->execute([$supplierId, $supplierId, $supplierId, $supplierId]);
$shipments = $shipmentsStmt->fetchAll();

$statsStmt = $db->prepare(
    'SELECT '
    . 'COUNT(*) AS shipments_count, '
    . 'SUM(CASE WHEN s.status IN (\'active\',\'departed\',\'airport\',\'arrived\',\'partially_distributed\') '
        . 'THEN 1 ELSE 0 END) AS active_shipments_count '
    . 'FROM shipments s '
    . 'WHERE s.deleted_at IS NULL AND (s.shipper_profile_id = ? OR s.consignee_profile_id = ?)'
);
$statsStmt->execute([$supplierId, $supplierId]);
$shipmentStats = $statsStmt->fetch() ?: [];

$invoiceStatsStmt = $db->prepare(
    'SELECT '
    . 'COUNT(*) AS invoices_count, '
    . 'SUM(CASE WHEN status IN (\'open\',\'partially_paid\') THEN 1 ELSE 0 END) AS open_invoices_count, '
    . 'COALESCE(SUM(total), 0) AS total_invoiced, '
    . 'COALESCE(SUM(paid_total), 0) AS total_paid, '
    . 'COALESCE(SUM(due_total), 0) AS total_due, '
    . 'MAX(issued_at) AS last_invoice_date '
    . 'FROM supplier_invoices '
    . 'WHERE supplier_id = ? AND deleted_at IS NULL'
);
$invoiceStatsStmt->execute([$supplierId]);
$invoiceStats = $invoiceStatsStmt->fetch() ?: [];

$paymentStatsStmt = $db->prepare(
    'SELECT MAX(payment_date) AS last_payment_date '
    . 'FROM supplier_transactions '
    . 'WHERE supplier_id = ? AND deleted_at IS NULL AND status = \'active\' AND type = \'payment\''
);
$paymentStatsStmt->execute([$supplierId]);
$paymentStats = $paymentStatsStmt->fetch() ?: [];

$stats = array_merge($shipmentStats, $invoiceStats, $paymentStats);

api_json([
    'ok' => true,
    'supplier' => $supplier,
    'shipments' => $shipments,
    'stats' => $stats,
]);


