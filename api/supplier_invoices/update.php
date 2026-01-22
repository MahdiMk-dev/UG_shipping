<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/finance_service.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$invoiceId = api_int($input['invoice_id'] ?? ($input['id'] ?? null));
$rateKgInput = api_float($input['rate_kg'] ?? null);
$rateCbmInput = api_float($input['rate_cbm'] ?? null);
$currencyInput = api_string($input['currency'] ?? null);
$note = api_string($input['note'] ?? null);
$issuedAt = api_string($input['issued_at'] ?? null);

if (!$invoiceId) {
    api_error('invoice_id is required', 422);
}
if ($issuedAt !== null && strtotime($issuedAt) === false) {
    api_error('Invalid issued_at', 422);
}

$currency = $currencyInput ? strtoupper($currencyInput) : null;
if ($currency !== null && !in_array($currency, ['USD', 'LBP'], true)) {
    api_error('currency must be USD or LBP', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM supplier_invoices WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$invoiceId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Supplier invoice not found', 404);
}
if (($before['status'] ?? '') === 'void') {
    api_error('Cannot edit a void invoice', 409);
}

$rateKg = $rateKgInput !== null ? (float) $rateKgInput : (float) ($before['rate_kg'] ?? 0);
$rateCbm = $rateCbmInput !== null ? (float) $rateCbmInput : (float) ($before['rate_cbm'] ?? 0);
if ($rateKg < 0 || $rateCbm < 0) {
    api_error('Rates must be zero or greater', 422);
}
if ($rateKg <= 0.0 && $rateCbm <= 0.0) {
    api_error('At least one rate must be greater than zero', 422);
}

$shipmentId = !empty($before['shipment_id']) ? (int) $before['shipment_id'] : null;
$totalWeight = (float) ($before['total_weight'] ?? 0);
$totalVolume = (float) ($before['total_volume'] ?? 0);
if ($shipmentId) {
    $shipmentStmt = $db->prepare('SELECT weight, size FROM shipments WHERE id = ? AND deleted_at IS NULL');
    $shipmentStmt->execute([$shipmentId]);
    $shipment = $shipmentStmt->fetch();
    if (!$shipment) {
        api_error('Shipment not found for this invoice', 404);
    }
    $totalWeight = round((float) ($shipment['weight'] ?? 0), 3);
    $totalVolume = round((float) ($shipment['size'] ?? 0), 3);
}

$total = round(($rateKg * $totalWeight) + ($rateCbm * $totalVolume), 2);
$paidTotal = (float) ($before['paid_total'] ?? 0);
$dueTotal = max(0.0, round($total - $paidTotal, 2));
$status = invoice_status_from_totals($paidTotal, $total);
$currencyFinal = $currency ?: strtoupper((string) ($before['currency'] ?? 'USD'));
$issuedAtFinal = $issuedAt ?: ($before['issued_at'] ?? null);
$supplierId = (int) ($before['supplier_id'] ?? 0);

$expenseLookup = $db->prepare(
    'SELECT id, account_transfer_id, expense_date, note FROM general_expenses '
    . 'WHERE reference_type = ? AND reference_id = ? AND deleted_at IS NULL LIMIT 1'
);

$db->beginTransaction();
try {
    $delta = $total - (float) ($before['total'] ?? 0);

    $fields = [
        'currency = ?',
        'rate_kg = ?',
        'rate_cbm = ?',
        'total_weight = ?',
        'total_volume = ?',
        'total = ?',
        'due_total = ?',
        'status = ?',
        'updated_at = NOW()',
        'updated_by_user_id = ?',
    ];
    $params = [
        $currencyFinal,
        $rateKg,
        $rateCbm,
        $totalWeight,
        $totalVolume,
        $total,
        $dueTotal,
        $status,
        $user['id'] ?? null,
    ];

    if (array_key_exists('note', $input)) {
        $fields[] = 'note = ?';
        $params[] = $note;
    }
    if ($issuedAtFinal !== null && array_key_exists('issued_at', $input)) {
        $fields[] = 'issued_at = ?';
        $params[] = $issuedAtFinal;
    }

    $params[] = $invoiceId;
    $sql = 'UPDATE supplier_invoices SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $db->prepare($sql)->execute($params);

    if ($delta != 0.0 && $supplierId) {
        $db->prepare('UPDATE supplier_profiles SET balance = balance + ? WHERE id = ?')
            ->execute([$delta, $supplierId]);
    }

    $expenseLookup->execute(['supplier_invoice', $invoiceId]);
    $expense = $expenseLookup->fetch();
    if ($expense) {
        $expenseDate = $issuedAtFinal ? date('Y-m-d', strtotime($issuedAtFinal)) : ($expense['expense_date'] ?? null);
        $db->prepare(
            'UPDATE general_expenses SET amount = ?, expense_date = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE id = ?'
        )->execute([$total, $expenseDate, $user['id'] ?? null, $expense['id']]);
    }

    $afterStmt = $db->prepare('SELECT * FROM supplier_invoices WHERE id = ?');
    $afterStmt->execute([$invoiceId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'supplier_invoices.update', 'supplier_invoice', $invoiceId, $before, $after);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    api_error('Failed to update supplier invoice', 500);
}

if ($shipmentId) {
    update_shipment_cost_per_unit($shipmentId);
}

api_json(['ok' => true]);
