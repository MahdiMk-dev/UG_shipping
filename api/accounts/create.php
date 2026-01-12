<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$ownerType = api_string($input['owner_type'] ?? null);
$ownerId = api_int($input['owner_id'] ?? null);
$name = api_string($input['name'] ?? null);
$paymentMethodId = api_int($input['payment_method_id'] ?? null);
$accountType = api_string($input['account_type'] ?? null);
$currency = strtoupper(api_string($input['currency'] ?? 'USD') ?? 'USD');
$isActive = isset($input['is_active']) ? api_int($input['is_active']) : 1;

$allowedOwnerTypes = ['admin', 'branch'];
if (!$ownerType || !in_array($ownerType, $allowedOwnerTypes, true)) {
    api_error('owner_type is required', 422);
}
if ($ownerType !== 'admin' && !$ownerId) {
    api_error('owner_id is required for this owner_type', 422);
}
if (!$paymentMethodId) {
    api_error('payment_method_id is required', 422);
}
if (!in_array($currency, ['USD', 'LBP'], true)) {
    api_error('currency must be USD or LBP', 422);
}

$db = db();
$methodStmt = $db->prepare('SELECT name FROM payment_methods WHERE id = ?');
$methodStmt->execute([$paymentMethodId]);
$methodName = $methodStmt->fetchColumn();
if (!$methodName) {
    api_error('Payment method not found', 404);
}

if (!$accountType) {
    $accountType = (string) $methodName;
}
if (!$name) {
    $name = $ownerType === 'admin' ? "Admin {$methodName}" : "{$ownerType} {$methodName}";
}

if ($ownerType === 'branch') {
    $ownerStmt = $db->prepare('SELECT 1 FROM branches WHERE id = ? AND deleted_at IS NULL');
    $ownerStmt->execute([$ownerId]);
    if (!$ownerStmt->fetchColumn()) {
        api_error('Branch not found', 404);
    }
}

$stmt = $db->prepare(
    'INSERT INTO accounts '
    . '(owner_type, owner_id, name, account_type, currency, payment_method_id, is_active, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $ownerType,
    $ownerType === 'admin' ? null : $ownerId,
    $name,
    $accountType,
    $currency,
    $paymentMethodId,
    $isActive ? 1 : 0,
    $user['id'] ?? null,
]);

$accountId = (int) $db->lastInsertId();
$rowStmt = $db->prepare('SELECT * FROM accounts WHERE id = ?');
$rowStmt->execute([$accountId]);
$after = $rowStmt->fetch();
audit_log($user, 'accounts.create', 'account', $accountId, null, $after);

api_json(['ok' => true, 'id' => $accountId]);
