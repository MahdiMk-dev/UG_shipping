<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$accountId = api_int($input['id'] ?? ($input['account_id'] ?? null));
$name = api_string($input['name'] ?? null);
$isActive = isset($input['is_active']) ? api_int($input['is_active']) : null;
$currency = api_string($input['currency'] ?? null);

if (!$accountId) {
    api_error('account_id is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM accounts WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$accountId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Account not found', 404);
}

$ownerType = $before['owner_type'] ?? null;
if (!in_array($ownerType, ['admin', 'branch'], true)) {
    api_error('Account type is not supported', 422);
}
if ($ownerType === 'branch') {
    $branchStmt = $db->prepare('SELECT type FROM branches WHERE id = ? AND deleted_at IS NULL');
    $branchStmt->execute([(int) $before['owner_id']]);
    $branchType = $branchStmt->fetchColumn();
    if (!$branchType) {
        api_error('Branch not found', 404);
    }
}

$fields = [];
$params = [];
if ($ownerType === 'branch' && $name !== null && $name !== '') {
    api_error('Branch account names cannot be edited', 403);
}
if ($name !== null && $name !== '') {
    $fields[] = 'name = ?';
    $params[] = $name;
}
if ($isActive !== null) {
    if (!$isActive) {
        $balance = (float) ($before['balance'] ?? 0);
        if (abs($balance) > 0.0001) {
            api_error('Account balance must be zero before deactivation', 409);
        }
    }
    $fields[] = 'is_active = ?';
    $params[] = $isActive ? 1 : 0;
}
if ($currency !== null && $currency !== '') {
    $currency = strtoupper($currency);
    if (!in_array($currency, ['USD', 'LBP'], true)) {
        api_error('currency must be USD or LBP', 422);
    }
    $currentCurrency = strtoupper((string) ($before['currency'] ?? ''));
    if ($currentCurrency !== $currency) {
        $balance = (float) ($before['balance'] ?? 0);
        if (abs($balance) > 0.0001) {
            api_error('Account balance must be zero before changing currency', 409);
        }
        $fields[] = 'currency = ?';
        $params[] = $currency;
    }
}

if (empty($fields)) {
    api_error('No changes provided', 422);
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;
$params[] = $accountId;

$stmt = $db->prepare('UPDATE accounts SET ' . implode(', ', $fields) . ' WHERE id = ?');
$stmt->execute($params);

$afterStmt = $db->prepare('SELECT * FROM accounts WHERE id = ?');
$afterStmt->execute([$accountId]);
$after = $afterStmt->fetch();
audit_log($user, 'accounts.update', 'account', $accountId, $before, $after);

api_json(['ok' => true, 'id' => $accountId]);
