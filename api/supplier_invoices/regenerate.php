<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/finance_service.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$invoiceId = api_int($input['invoice_id'] ?? ($input['id'] ?? null));
if (!$invoiceId) {
    api_error('invoice_id is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM supplier_invoices WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$invoiceId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Supplier invoice not found', 404);
}
if (($before['status'] ?? '') === 'void') {
    api_error('Cannot regenerate a void invoice', 409);
}

$shipmentId = !empty($before['shipment_id']) ? (int) $before['shipment_id'] : null;
if (!$shipmentId) {
    api_error('Shipment is required to regenerate this invoice', 409);
}

$shipmentStmt = $db->prepare('SELECT weight, size FROM shipments WHERE id = ? AND deleted_at IS NULL');
$shipmentStmt->execute([$shipmentId]);
$shipment = $shipmentStmt->fetch();
if (!$shipment) {
    api_error('Shipment not found for this invoice', 404);
}

$rateKg = (float) ($before['rate_kg'] ?? 0);
$rateCbm = (float) ($before['rate_cbm'] ?? 0);
$totalWeight = round((float) ($shipment['weight'] ?? 0), 3);
$totalVolume = round((float) ($shipment['size'] ?? 0), 3);
$total = round(($rateKg * $totalWeight) + ($rateCbm * $totalVolume), 2);

$paidTotal = (float) ($before['paid_total'] ?? 0);
$dueTotal = max(0.0, round($total - $paidTotal, 2));
$status = invoice_status_from_totals($paidTotal, $total);
$supplierId = (int) ($before['supplier_id'] ?? 0);

$expenseLookup = $db->prepare(
    'SELECT id, account_transfer_id, expense_date, note FROM general_expenses '
    . 'WHERE reference_type = ? AND reference_id = ? AND deleted_at IS NULL LIMIT 1'
);

$insertSupplierTransaction = $db->prepare(
    'INSERT INTO supplier_transactions '
    . '(supplier_id, invoice_id, branch_id, type, payment_method_id, amount, payment_date, note, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$db->beginTransaction();
try {
    $beforeTotal = (float) ($before['total'] ?? 0);
    $delta = $total - $beforeTotal;

    $db->prepare(
        'UPDATE supplier_invoices SET total_weight = ?, total_volume = ?, total = ?, due_total = ?, status = ?, '
        . 'updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
    )->execute([
        $totalWeight,
        $totalVolume,
        $total,
        $dueTotal,
        $status,
        $user['id'] ?? null,
        $invoiceId,
    ]);

    if ($delta != 0.0 && $supplierId) {
        $db->prepare('UPDATE supplier_profiles SET balance = balance + ? WHERE id = ?')
            ->execute([$delta, $supplierId]);
    }

    $txNote = sprintf('Invoice regenerated: %.2f -> %.2f', $beforeTotal, $total);
    $insertSupplierTransaction->execute([
        $supplierId,
        $invoiceId,
        null,
        'invoice_regenerate',
        null,
        $delta,
        date('Y-m-d'),
        $txNote,
        $user['id'] ?? null,
    ]);

    $expenseLookup->execute(['supplier_invoice', $invoiceId]);
    $expense = $expenseLookup->fetch();
    if ($expense) {
        $expenseDate = $expense['expense_date'] ?? null;
        $db->prepare(
            'UPDATE general_expenses SET amount = ?, updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
        )->execute([$total, $user['id'] ?? null, $expense['id']]);
    }

    $afterStmt = $db->prepare('SELECT * FROM supplier_invoices WHERE id = ?');
    $afterStmt->execute([$invoiceId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'supplier_invoices.regenerate', 'supplier_invoice', $invoiceId, $before, $after);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    api_error('Failed to regenerate supplier invoice', 500);
}

update_shipment_cost_per_unit($shipmentId);

api_json(['ok' => true, 'total' => $total, 'total_weight' => $totalWeight, 'total_volume' => $totalVolume]);
