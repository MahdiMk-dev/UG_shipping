<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/finance_service.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/services/account_service.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$invoiceId = api_int($input['invoice_id'] ?? ($input['id'] ?? null));
$items = $input['items'] ?? null;
$currencyInput = api_string($input['currency'] ?? null);
$note = api_string($input['note'] ?? null);
$issuedAt = api_string($input['issued_at'] ?? null);

if (!$invoiceId) {
    api_error('invoice_id is required', 422);
}
if (!is_array($items) || empty($items)) {
    api_error('Invoice line items are required', 422);
}
if ($issuedAt !== null && strtotime($issuedAt) === false) {
    api_error('Invalid issued_at', 422);
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

$currency = $currencyInput ? strtoupper($currencyInput) : null;
if ($currency !== null && !in_array($currency, ['USD', 'LBP'], true)) {
    api_error('currency must be USD or LBP', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM partner_invoices WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$invoiceId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Partner invoice not found', 404);
}
if (($before['status'] ?? '') === 'void') {
    api_error('Cannot edit a void invoice', 409);
}

$paidTotal = (float) ($before['paid_total'] ?? 0);
$dueTotal = max(0.0, round($total - $paidTotal, 2));
$status = invoice_status_from_totals($paidTotal, $total);
$currencyFinal = $currency ?: strtoupper((string) ($before['currency'] ?? 'USD'));
$issuedAtFinal = $issuedAt ?: ($before['issued_at'] ?? null);
$shipmentId = !empty($before['shipment_id']) ? (int) $before['shipment_id'] : null;
$partnerId = (int) ($before['partner_id'] ?? 0);

$insertItem = $db->prepare(
    'INSERT INTO partner_invoice_items (invoice_id, description, amount) VALUES (?, ?, ?)'
);

$expenseLookup = $db->prepare(
    'SELECT id, account_transfer_id, expense_date, note FROM general_expenses '
    . 'WHERE reference_type = ? AND reference_id = ? AND deleted_at IS NULL LIMIT 1'
);

$db->beginTransaction();
try {
    $delta = $total - (float) ($before['total'] ?? 0);

    $db->prepare('DELETE FROM partner_invoice_items WHERE invoice_id = ?')->execute([$invoiceId]);
    foreach ($cleanItems as $item) {
        $insertItem->execute([$invoiceId, $item['description'], $item['amount']]);
    }

    $fields = [
        'currency = ?',
        'total = ?',
        'due_total = ?',
        'status = ?',
        'updated_at = NOW()',
        'updated_by_user_id = ?',
    ];
    $params = [
        $currencyFinal,
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
    $sql = 'UPDATE partner_invoices SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $db->prepare($sql)->execute($params);

    if ($delta != 0.0 && $partnerId) {
        $db->prepare('UPDATE partner_profiles SET balance = balance + ? WHERE id = ?')
            ->execute([$delta, $partnerId]);
    }

    $expenseLookup->execute(['partner_invoice', $invoiceId]);
    $expense = $expenseLookup->fetch();
    if ($expense) {
        $expenseDate = $issuedAtFinal ? date('Y-m-d', strtotime($issuedAtFinal)) : ($expense['expense_date'] ?? null);
        $db->prepare(
            'UPDATE general_expenses SET amount = ?, expense_date = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE id = ?'
        )->execute([$total, $expenseDate, $user['id'] ?? null, $expense['id']]);

        $currentTransferId = !empty($expense['account_transfer_id']) ? (int) $expense['account_transfer_id'] : null;
        if ($currentTransferId) {
            $fromAccountStmt = $db->prepare('SELECT from_account_id FROM account_transfers WHERE id = ?');
            $fromAccountStmt->execute([$currentTransferId]);
            $fromAccountId = (int) $fromAccountStmt->fetchColumn();
            if ($fromAccountId) {
                cancel_account_transfer($db, $currentTransferId, 'Partner invoice updated', $user['id'] ?? null);
                $entryType = $shipmentId ? 'shipment_expense' : 'general_expense';
                $transferNote = $expense['note'] ?? null;
                $transferId = create_account_transfer(
                    $db,
                    $fromAccountId,
                    null,
                    $total,
                    $entryType,
                    $expenseDate,
                    $transferNote,
                    'general_expense',
                    (int) $expense['id'],
                    $user['id'] ?? null
                );
                $db->prepare('UPDATE general_expenses SET account_transfer_id = ? WHERE id = ?')
                    ->execute([$transferId, $expense['id']]);
            }
        }
    }

    $afterStmt = $db->prepare('SELECT * FROM partner_invoices WHERE id = ?');
    $afterStmt->execute([$invoiceId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'partner_invoices.update', 'partner_invoice', $invoiceId, $before, $after);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    api_error('Failed to update partner invoice', 500);
}

if ($shipmentId) {
    update_shipment_cost_per_unit($shipmentId);
}

api_json(['ok' => true]);
