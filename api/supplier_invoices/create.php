<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$supplierId = api_int($input['supplier_id'] ?? null);
$shipmentId = api_int($input['shipment_id'] ?? null);
$issuedAt = api_string($input['issued_at'] ?? null);
$note = api_string($input['note'] ?? null);
$currency = strtoupper(api_string($input['currency'] ?? 'USD') ?? 'USD');
$rateKg = api_float($input['rate_kg'] ?? null);
$rateCbm = api_float($input['rate_cbm'] ?? null);

if (!$supplierId) {
    api_error('supplier_id is required', 422);
}
if (!$shipmentId) {
    api_error('shipment_id is required', 422);
}
if ($rateKg === null || $rateCbm === null) {
    api_error('rate_kg and rate_cbm are required', 422);
}
$rateKg = (float) $rateKg;
$rateCbm = (float) $rateCbm;
if ($rateKg < 0 || $rateCbm < 0) {
    api_error('Rates must be zero or greater', 422);
}
if ($rateKg <= 0.0 && $rateCbm <= 0.0) {
    api_error('At least one rate must be greater than zero', 422);
}
if ($issuedAt !== null && strtotime($issuedAt) === false) {
    api_error('Invalid issued_at', 422);
}
if (!in_array($currency, ['USD', 'LBP'], true)) {
    api_error('currency must be USD or LBP', 422);
}

$db = db();

$supplierStmt = $db->prepare('SELECT id, name, type FROM supplier_profiles WHERE id = ? AND deleted_at IS NULL');
$supplierStmt->execute([$supplierId]);
$supplier = $supplierStmt->fetch();
if (!$supplier) {
    api_error('Supplier profile not found', 404);
}

$shipmentStmt = $db->prepare(
    'SELECT id, shipment_number, weight, size FROM shipments '
    . 'WHERE id = ? AND deleted_at IS NULL AND (shipper_profile_id = ? OR consignee_profile_id = ?)'
);
$shipmentStmt->execute([$shipmentId, $supplierId, $supplierId]);
$shipment = $shipmentStmt->fetch();
if (!$shipment) {
    api_error('Shipment not found for this supplier', 404);
}
$existingStmt = $db->prepare(
    'SELECT id FROM supplier_invoices WHERE supplier_id = ? AND shipment_id = ? AND deleted_at IS NULL LIMIT 1'
);
$existingStmt->execute([$supplierId, $shipmentId]);
if ($existingStmt->fetch()) {
    api_error('An active invoice already exists for this shipment', 409);
}

$totalWeight = round((float) ($shipment['weight'] ?? 0), 3);
$totalVolume = round((float) ($shipment['size'] ?? 0), 3);
$total = round(($rateKg * $totalWeight) + ($rateCbm * $totalVolume), 2);
$issuedAtValue = $issuedAt ?: date('Y-m-d H:i:s');

$insertInvoice = $db->prepare(
    'INSERT INTO supplier_invoices '
    . '(supplier_id, shipment_id, invoice_no, status, currency, rate_kg, rate_cbm, total_weight, total_volume, '
    . 'total, paid_total, due_total, issued_at, issued_by_user_id, note) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$insertSupplierTransaction = $db->prepare(
    'INSERT INTO supplier_transactions '
    . '(supplier_id, invoice_id, branch_id, type, payment_method_id, amount, payment_date, note, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$insertExpense = $db->prepare(
    'INSERT INTO general_expenses '
    . '(branch_id, shipment_id, title, amount, expense_date, note, reference_type, reference_id, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$db->beginTransaction();
try {
    $finalInvoiceNo = null;
    $tries = 0;
    while ($tries < 3 && !$finalInvoiceNo) {
        $tries++;
        $candidate = 'SINV-' . date('Ymd-His') . '-' . random_int(100, 999);
        try {
            $insertInvoice->execute([
                $supplierId,
                $shipmentId,
                $candidate,
                'open',
                $currency,
                $rateKg,
                $rateCbm,
                $totalWeight,
                $totalVolume,
                $total,
                0,
                $total,
                $issuedAtValue,
                $user['id'] ?? null,
                $note,
            ]);
            $finalInvoiceNo = $candidate;
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                continue;
            }
            throw $e;
        }
    }

    if (!$finalInvoiceNo) {
        api_error('Unable to generate invoice number', 500);
    }

    $invoiceId = (int) $db->lastInsertId();
    if ($total != 0.0) {
        $db->prepare('UPDATE supplier_profiles SET balance = balance + ? WHERE id = ?')
            ->execute([$total, $supplierId]);
    }

    $expenseDate = date('Y-m-d', strtotime($issuedAtValue));
    $txNote = $note ? sprintf('Invoice created: %s', $note) : 'Invoice created';
    $insertSupplierTransaction->execute([
        $supplierId,
        $invoiceId,
        null,
        'invoice_create',
        null,
        $total,
        $expenseDate,
        $txNote,
        $user['id'] ?? null,
    ]);

    $supplierLabel = $supplier['name'] ?? 'Supplier';
    $title = sprintf('Supplier invoice %s - %s', $finalInvoiceNo, $supplierLabel);
    $expenseNote = sprintf('Shipment %s (%s)', $shipment['shipment_number'], $supplier['type'] ?? 'supplier');
    $insertExpense->execute([
        null,
        $shipmentId,
        $title,
        $total,
        $expenseDate,
        $expenseNote,
        'supplier_invoice',
        $invoiceId,
        $user['id'] ?? null,
    ]);
    $rowStmt = $db->prepare('SELECT * FROM supplier_invoices WHERE id = ?');
    $rowStmt->execute([$invoiceId]);
    $after = $rowStmt->fetch();
    audit_log($user, 'supplier_invoices.create', 'supplier_invoice', $invoiceId, null, $after);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    api_error('Failed to create supplier invoice', 500);
}

update_shipment_cost_per_unit($shipmentId);

api_json([
    'ok' => true,
    'id' => $invoiceId,
    'invoice_no' => $finalInvoiceNo,
    'total' => $total,
]);
