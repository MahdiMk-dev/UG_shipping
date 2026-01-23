<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/api.php';
require_once __DIR__ . '/../../../app/permissions.php';
require_once __DIR__ . '/../../../app/services/account_service.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$input = api_read_input();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    api_error('User context missing', 403);
}

$txType = api_string($input['tx_type'] ?? null);
$amount = api_float($input['amount'] ?? null);
$currencyCodeRaw = api_string($input['currency_code'] ?? null);
$description = api_string($input['description'] ?? null);
$txDateInput = api_string($input['tx_date'] ?? null);
$partnerId = api_int($input['partner_id'] ?? null);
$fromPartnerId = api_int($input['from_partner_id'] ?? null);
$toPartnerId = api_int($input['to_partner_id'] ?? null);
$fromAdminAccountId = api_int($input['from_admin_account_id'] ?? null);
$toAdminAccountId = api_int($input['to_admin_account_id'] ?? null);
$referenceNo = api_string($input['reference_no'] ?? null);

$allowedTypes = [
    'WE_PAY_PARTNER',
    'PARTNER_PAYS_US',
    'WE_OWE_PARTNER',
    'PARTNER_OWES_US',
    'PARTNER_TO_PARTNER_TRANSFER',
    'ADJUST_PLUS',
    'ADJUST_MINUS',
];

if (!$txType || !in_array($txType, $allowedTypes, true)) {
    api_error('Invalid tx_type', 422);
}
if ($amount === null) {
    api_error('amount is required', 422);
}
$amountValue = round(abs((float) $amount), 2);
if ($amountValue <= 0) {
    api_error('amount must be greater than zero', 422);
}
if (!$currencyCodeRaw) {
    api_error('currency_code is required', 422);
}
$currencyCode = strtoupper($currencyCodeRaw);
if (strlen($currencyCode) !== 3) {
    api_error('currency_code must be 3 characters', 422);
}

if ($txDateInput !== null && strtotime($txDateInput) === false) {
    api_error('Invalid tx_date', 422);
}
$txDate = $txDateInput ? date('Y-m-d H:i:s', strtotime($txDateInput)) : date('Y-m-d H:i:s');
$transferDate = date('Y-m-d', strtotime($txDate));

$isTransfer = $txType === 'PARTNER_TO_PARTNER_TRANSFER';
$singlePartnerTypes = [
    'WE_PAY_PARTNER',
    'PARTNER_PAYS_US',
    'WE_OWE_PARTNER',
    'PARTNER_OWES_US',
    'ADJUST_PLUS',
    'ADJUST_MINUS',
];

if ($isTransfer) {
    if (!$fromPartnerId || !$toPartnerId) {
        api_error('from_partner_id and to_partner_id are required', 422);
    }
    if ($fromPartnerId === $toPartnerId) {
        api_error('from_partner_id and to_partner_id must differ', 422);
    }
    if ($partnerId) {
        api_error('partner_id must be null for transfers', 422);
    }
} elseif (in_array($txType, $singlePartnerTypes, true)) {
    if (!$partnerId) {
        api_error('partner_id is required', 422);
    }
    if ($fromPartnerId || $toPartnerId) {
        api_error('from_partner_id and to_partner_id must be null for this tx_type', 422);
    }
}

if ($txType === 'WE_PAY_PARTNER') {
    if (!$fromAdminAccountId) {
        api_error('from_admin_account_id is required', 422);
    }
    if ($toAdminAccountId) {
        api_error('to_admin_account_id must be null for WE_PAY_PARTNER', 422);
    }
} elseif ($txType === 'PARTNER_PAYS_US') {
    if (!$toAdminAccountId) {
        api_error('to_admin_account_id is required', 422);
    }
    if ($fromAdminAccountId) {
        api_error('from_admin_account_id must be null for PARTNER_PAYS_US', 422);
    }
} else {
    if ($fromAdminAccountId || $toAdminAccountId) {
        api_error('Admin accounts are not allowed for this tx_type', 422);
    }
}

$db = db();

$partnerCheck = $db->prepare('SELECT id FROM partners WHERE id = ?');
if ($partnerId) {
    $partnerCheck->execute([$partnerId]);
    if (!$partnerCheck->fetch()) {
        api_error('Partner not found', 404);
    }
}
if ($fromPartnerId) {
    $partnerCheck->execute([$fromPartnerId]);
    if (!$partnerCheck->fetch()) {
        api_error('From partner not found', 404);
    }
}
if ($toPartnerId) {
    $partnerCheck->execute([$toPartnerId]);
    if (!$partnerCheck->fetch()) {
        api_error('To partner not found', 404);
    }
}

$fromAccount = null;
$toAccount = null;
if ($fromAdminAccountId) {
    $fromAccount = fetch_account($db, $fromAdminAccountId);
    if (($fromAccount['owner_type'] ?? '') !== 'admin') {
        api_error('from_admin_account_id must be an admin account', 422);
    }
    $accountCurrency = strtoupper((string) ($fromAccount['currency'] ?? ''));
    if ($accountCurrency !== '' && $accountCurrency !== $currencyCode) {
        api_error('from_admin_account currency does not match currency_code', 422);
    }
}
if ($toAdminAccountId) {
    $toAccount = fetch_account($db, $toAdminAccountId);
    if (($toAccount['owner_type'] ?? '') !== 'admin') {
        api_error('to_admin_account_id must be an admin account', 422);
    }
    $accountCurrency = strtoupper((string) ($toAccount['currency'] ?? ''));
    if ($accountCurrency !== '' && $accountCurrency !== $currencyCode) {
        api_error('to_admin_account currency does not match currency_code', 422);
    }
}

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

$insertTx = $db->prepare(
    'INSERT INTO partner_transactions '
    . '(tx_date, tx_type, currency_code, amount, description, partner_id, from_partner_id, to_partner_id, '
    . 'from_admin_account_id, to_admin_account_id, created_by_user_id, branch_id, reference_no, status, meta) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$txId = 0;
$db->beginTransaction();
try {
    $branchId = api_int($user['branch_id'] ?? null);
    $meta = null;

    $insertTx->execute([
        $txDate,
        $txType,
        $currencyCode,
        $amountValue,
        $description,
        $partnerId,
        $fromPartnerId,
        $toPartnerId,
        $fromAdminAccountId,
        $toAdminAccountId,
        $userId,
        $branchId,
        $referenceNo,
        'posted',
        $meta,
    ]);

    $txId = (int) $db->lastInsertId();

    if ($partnerId) {
        $db->prepare('UPDATE partners SET current_balance = current_balance + ? WHERE id = ?')
            ->execute([$partnerDelta, $partnerId]);
    }
    if ($fromPartnerId) {
        $db->prepare('UPDATE partners SET current_balance = current_balance + ? WHERE id = ?')
            ->execute([$fromDelta, $fromPartnerId]);
    }
    if ($toPartnerId) {
        $db->prepare('UPDATE partners SET current_balance = current_balance + ? WHERE id = ?')
            ->execute([$toDelta, $toPartnerId]);
    }

    if ($txType === 'WE_PAY_PARTNER') {
        create_account_transfer(
            $db,
            $fromAdminAccountId,
            null,
            $amountValue,
            'partner_transaction',
            $transferDate,
            $description,
            'partner_transaction',
            $txId,
            $userId
        );
    }
    if ($txType === 'PARTNER_PAYS_US') {
        create_account_transfer(
            $db,
            null,
            $toAdminAccountId,
            $amountValue,
            'partner_transaction',
            $transferDate,
            $description,
            'partner_transaction',
            $txId,
            $userId
        );
    }

    $balances = [];
    $balanceStmt = $db->prepare('SELECT id, current_balance FROM partners WHERE id = ?');
    foreach (array_filter([$partnerId, $fromPartnerId, $toPartnerId]) as $id) {
        $balanceStmt->execute([$id]);
        if ($row = $balanceStmt->fetch()) {
            $balances[] = $row;
        }
    }

    $accountBalances = [];
    if ($fromAdminAccountId) {
        $accountBalances[] = fetch_account($db, $fromAdminAccountId);
    }
    if ($toAdminAccountId) {
        $accountBalances[] = fetch_account($db, $toAdminAccountId);
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    api_error('Failed to create partner transaction', 500, ['detail' => $e->getMessage()]);
}

api_json([
    'ok' => true,
    'tx_id' => $txId,
    'partner_balances' => $balances,
    'account_balances' => $accountBalances,
]);
