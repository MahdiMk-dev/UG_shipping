<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/api.php';
require_once __DIR__ . '/../../../app/permissions.php';
require_once __DIR__ . '/../../../app/services/account_service.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    api_error('User context missing', 403);
}

$txId = api_int($input['id'] ?? ($input['tx_id'] ?? null));
$reason = api_string($input['reason'] ?? null);

if (!$txId) {
    api_error('tx_id is required', 422);
}
if (!$reason) {
    api_error('Void reason is required', 422);
}

$db = db();

$txStmt = $db->prepare('SELECT * FROM partner_transactions WHERE id = ?');
$txStmt->execute([$txId]);
$transaction = $txStmt->fetch();
if (!$transaction) {
    api_error('Transaction not found', 404);
}
if (($transaction['status'] ?? '') !== 'posted') {
    api_error('Transaction already voided', 409);
}
if (($transaction['tx_type'] ?? '') === 'REVERSAL') {
    api_error('Reversal transactions cannot be voided', 422);
}

$txType = (string) ($transaction['tx_type'] ?? '');
$amountValue = round(abs((float) ($transaction['amount'] ?? 0)), 2);
$currencyCode = strtoupper((string) ($transaction['currency_code'] ?? ''));

$partnerId = api_int($transaction['partner_id'] ?? null);
$fromPartnerId = api_int($transaction['from_partner_id'] ?? null);
$toPartnerId = api_int($transaction['to_partner_id'] ?? null);
$fromAdminAccountId = api_int($transaction['from_admin_account_id'] ?? null);
$toAdminAccountId = api_int($transaction['to_admin_account_id'] ?? null);

$partnerDelta = 0.0;
$fromDelta = 0.0;
$toDelta = 0.0;

switch ($txType) {
    case 'WE_OWE_PARTNER':
    case 'ADJUST_PLUS':
        $partnerDelta = $amountValue;
        break;
    case 'PARTNER_OWES_US':
    case 'ADJUST_MINUS':
        $partnerDelta = -$amountValue;
        break;
    case 'WE_PAY_PARTNER':
        $partnerDelta = -$amountValue;
        break;
    case 'PARTNER_PAYS_US':
        $partnerDelta = $amountValue;
        break;
    case 'PARTNER_TO_PARTNER_TRANSFER':
        $fromDelta = $amountValue;
        $toDelta = -$amountValue;
        break;
}

$reverseMeta = json_encode([
    'reverse_of' => $txId,
    'original_type' => $txType,
]);

$insertTx = $db->prepare(
    'INSERT INTO partner_transactions '
    . '(tx_date, tx_type, currency_code, amount, description, partner_id, from_partner_id, to_partner_id, '
    . 'from_admin_account_id, to_admin_account_id, created_by_user_id, branch_id, reference_no, status, meta) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$db->beginTransaction();
try {
    $db->prepare(
        'UPDATE partner_transactions SET status = ?, void_reason = ?, voided_by_user_id = ?, voided_at = NOW() WHERE id = ?'
    )->execute([
        'voided',
        $reason,
        $userId,
        $txId,
    ]);

    $insertTx->execute([
        date('Y-m-d H:i:s'),
        'REVERSAL',
        $currencyCode,
        $amountValue,
        'Reversal for transaction #' . $txId,
        $partnerId,
        $fromPartnerId,
        $toPartnerId,
        $fromAdminAccountId,
        $toAdminAccountId,
        $userId,
        $transaction['branch_id'] ?? null,
        null,
        'posted',
        $reverseMeta,
    ]);

    $reverseId = (int) $db->lastInsertId();

    if ($partnerId) {
        $db->prepare('UPDATE partners SET current_balance = current_balance - ? WHERE id = ?')
            ->execute([$partnerDelta, $partnerId]);
    }
    if ($fromPartnerId) {
        $db->prepare('UPDATE partners SET current_balance = current_balance - ? WHERE id = ?')
            ->execute([$fromDelta, $fromPartnerId]);
    }
    if ($toPartnerId) {
        $db->prepare('UPDATE partners SET current_balance = current_balance - ? WHERE id = ?')
            ->execute([$toDelta, $toPartnerId]);
    }

    $transferDate = date('Y-m-d');
    if ($txType === 'WE_PAY_PARTNER' && $fromAdminAccountId) {
        create_account_transfer(
            $db,
            null,
            $fromAdminAccountId,
            $amountValue,
            'partner_transaction',
            $transferDate,
            'Reversal for partner transaction',
            'partner_transaction',
            $reverseId,
            $userId
        );
    }
    if ($txType === 'PARTNER_PAYS_US' && $toAdminAccountId) {
        create_account_transfer(
            $db,
            $toAdminAccountId,
            null,
            $amountValue,
            'partner_transaction',
            $transferDate,
            'Reversal for partner transaction',
            'partner_transaction',
            $reverseId,
            $userId
        );
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    api_error('Failed to void partner transaction', 500, ['detail' => $e->getMessage()]);
}

api_json(['ok' => true]);
