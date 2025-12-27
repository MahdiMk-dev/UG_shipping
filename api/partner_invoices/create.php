<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$partnerId = api_int($input['partner_id'] ?? null);
$shipmentId = api_int($input['shipment_id'] ?? null);
$invoiceNo = api_string($input['invoice_no'] ?? null);
$total = api_float($input['total'] ?? null);
$issuedAt = api_string($input['issued_at'] ?? null);
$note = api_string($input['note'] ?? null);

if (!$partnerId || $total === null) {
    api_error('partner_id and total are required', 422);
}
if ((float) $total <= 0.0) {
    api_error('total must be greater than zero', 422);
}
if ($issuedAt !== null && strtotime($issuedAt) === false) {
    api_error('Invalid issued_at', 422);
}

$db = db();
$partnerStmt = $db->prepare('SELECT id FROM partner_profiles WHERE id = ? AND deleted_at IS NULL');
$partnerStmt->execute([$partnerId]);
if (!$partnerStmt->fetch()) {
    api_error('Partner profile not found', 404);
}
if ($shipmentId) {
    $shipmentStmt = $db->prepare(
        'SELECT id FROM shipments '
        . 'WHERE id = ? AND deleted_at IS NULL AND (shipper_profile_id = ? OR consignee_profile_id = ?)'
    );
    $shipmentStmt->execute([$shipmentId, $partnerId, $partnerId]);
    if (!$shipmentStmt->fetch()) {
        api_error('Shipment not found for this partner', 404);
    }
}

$issuedAtValue = $issuedAt ?: date('Y-m-d H:i:s');

$insertInvoice = $db->prepare(
    'INSERT INTO partner_invoices '
    . '(partner_id, shipment_id, invoice_no, status, total, paid_total, due_total, issued_at, issued_by_user_id, note) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$db->beginTransaction();
try {
    $finalInvoiceNo = $invoiceNo;
    $tries = 0;
    $inserted = false;
    while (!$inserted && $tries < 3) {
        $tries++;
        if (!$finalInvoiceNo) {
            $finalInvoiceNo = 'PINV-' . date('Ymd-His') . '-' . random_int(100, 999);
        }
        try {
            $insertInvoice->execute([
                $partnerId,
                $shipmentId,
                $finalInvoiceNo,
                'open',
                $total,
                0,
                $total,
                $issuedAtValue,
                $user['id'] ?? null,
                $note,
            ]);
            $inserted = true;
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                if ($invoiceNo) {
                    api_error('Invoice number already exists', 409);
                }
                $finalInvoiceNo = null;
                continue;
            }
            throw $e;
        }
    }

    if (!$inserted) {
        api_error('Unable to generate invoice number', 500);
    }

    $invoiceId = (int) $db->lastInsertId();
    $db->prepare('UPDATE partner_profiles SET balance = balance - ? WHERE id = ?')
        ->execute([$total, $partnerId]);

    $rowStmt = $db->prepare('SELECT * FROM partner_invoices WHERE id = ?');
    $rowStmt->execute([$invoiceId]);
    $after = $rowStmt->fetch();
    audit_log($user, 'partner_invoices.create', 'partner_invoice', $invoiceId, null, $after);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    api_error('Failed to create partner invoice', 500);
}

api_json([
    'ok' => true,
    'id' => $invoiceId,
    'invoice_no' => $finalInvoiceNo,
    'total' => $total,
]);
