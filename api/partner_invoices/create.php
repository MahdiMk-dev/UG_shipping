<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
require_once __DIR__ . '/../../app/services/account_service.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$partnerId = api_int($input['partner_id'] ?? null);
$shipmentId = api_int($input['shipment_id'] ?? null);
$issuedAt = api_string($input['issued_at'] ?? null);
$note = api_string($input['note'] ?? null);
$currency = strtoupper(api_string($input['currency'] ?? 'USD') ?? 'USD');
$items = $input['items'] ?? [];
$adminAccountId = api_int($input['admin_account_id'] ?? null);

if (!$partnerId) {
    api_error('partner_id is required', 422);
}
if (!$adminAccountId) {
    api_error('admin_account_id is required', 422);
}
if (!is_array($items) || empty($items)) {
    api_error('Invoice line items are required', 422);
}
if ($issuedAt !== null && strtotime($issuedAt) === false) {
    api_error('Invalid issued_at', 422);
}
if (!in_array($currency, ['USD', 'LBP'], true)) {
    api_error('currency must be USD or LBP', 422);
}

$cleanItems = [];
$total = 0.0;
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $description = api_string($item['description'] ?? null);
    $amount = api_float($item['amount'] ?? null);
    if (!$description) {
        api_error('Each line item must include a description', 422);
    }
    if ($amount === null || (float) $amount === 0.0) {
        api_error('Each line item must include a non-zero amount', 422);
    }
    $cleanItems[] = [
        'description' => $description,
        'amount' => (float) $amount,
    ];
    $total += (float) $amount;
}

$total = round($total, 2);
if ($total <= 0.0) {
    api_error('Invoice total must be greater than zero', 422);
}

$db = db();
$fromAccount = fetch_account($db, $adminAccountId);
if (($fromAccount['owner_type'] ?? '') !== 'admin') {
    api_error('Partner invoices must be paid from an admin account', 422);
}
$partnerStmt = $db->prepare('SELECT id, name, type FROM partner_profiles WHERE id = ? AND deleted_at IS NULL');
$partnerStmt->execute([$partnerId]);
$partner = $partnerStmt->fetch();
if (!$partner) {
    api_error('Partner profile not found', 404);
}

if ($shipmentId) {
    $shipmentStmt = $db->prepare(
        'SELECT id, shipment_number FROM shipments '
        . 'WHERE id = ? AND deleted_at IS NULL AND (shipper_profile_id = ? OR consignee_profile_id = ?)'
    );
    $shipmentStmt->execute([$shipmentId, $partnerId, $partnerId]);
    $shipment = $shipmentStmt->fetch();
    if (!$shipment) {
        api_error('Shipment not found for this partner', 404);
    }
    $existingStmt = $db->prepare(
        'SELECT id FROM partner_invoices WHERE partner_id = ? AND shipment_id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $existingStmt->execute([$partnerId, $shipmentId]);
    if ($existingStmt->fetch()) {
        api_error('An active invoice already exists for this shipment', 409);
    }
} else {
    $shipment = null;
}

$issuedAtValue = $issuedAt ?: date('Y-m-d H:i:s');

$insertInvoice = $db->prepare(
    'INSERT INTO partner_invoices '
    . '(partner_id, shipment_id, invoice_no, status, currency, total, paid_total, due_total, issued_at, issued_by_user_id, note) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$insertItem = $db->prepare(
    'INSERT INTO partner_invoice_items (invoice_id, description, amount) VALUES (?, ?, ?)'
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
        $candidate = 'PINV-' . date('Ymd-His') . '-' . random_int(100, 999);
        try {
            $insertInvoice->execute([
                $partnerId,
                $shipmentId,
                $candidate,
                'open',
                $currency,
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
    foreach ($cleanItems as $item) {
        $insertItem->execute([$invoiceId, $item['description'], $item['amount']]);
    }

    $db->prepare('UPDATE partner_profiles SET balance = balance + ? WHERE id = ?')
        ->execute([$total, $partnerId]);

    $expenseDate = date('Y-m-d', strtotime($issuedAtValue));
    $partnerLabel = $partner['name'] ?? 'Partner';
    $title = sprintf('Partner invoice %s - %s', $finalInvoiceNo, $partnerLabel);
    $expenseNote = $shipment
        ? sprintf('Shipment %s (%s)', $shipment['shipment_number'], $partner['type'] ?? 'partner')
        : null;
    $insertExpense->execute([
        null,
        $shipmentId,
        $title,
        $total,
        $expenseDate,
        $expenseNote,
        'partner_invoice',
        $invoiceId,
        $user['id'] ?? null,
    ]);
    $expenseId = (int) $db->lastInsertId();
    $entryType = $shipmentId ? 'shipment_expense' : 'general_expense';
    $transferId = create_account_transfer(
        $db,
        $adminAccountId,
        null,
        (float) $total,
        $entryType,
        $expenseDate,
        $expenseNote,
        'general_expense',
        $expenseId,
        $user['id'] ?? null
    );
    $db->prepare('UPDATE general_expenses SET account_transfer_id = ? WHERE id = ?')
        ->execute([$transferId, $expenseId]);

    $rowStmt = $db->prepare('SELECT * FROM partner_invoices WHERE id = ?');
    $rowStmt->execute([$invoiceId]);
    $after = $rowStmt->fetch();
    audit_log($user, 'partner_invoices.create', 'partner_invoice', $invoiceId, null, $after);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    api_error('Failed to create partner invoice', 500);
}

if ($shipmentId) {
    update_shipment_cost_per_unit($shipmentId);
}

api_json([
    'ok' => true,
    'id' => $invoiceId,
    'invoice_no' => $finalInvoiceNo,
    'total' => $total,
]);
