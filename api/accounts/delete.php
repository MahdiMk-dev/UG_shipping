<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$accountId = api_int($input['id'] ?? ($input['account_id'] ?? null));
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
if ($ownerType !== 'admin') {
    api_error('Only admin accounts can be deleted', 403);
}

$balance = (float) ($before['balance'] ?? 0);
if (abs($balance) > 0.0001) {
    api_error('Account balance must be zero before deletion', 409);
}

$stmt = $db->prepare(
    'UPDATE accounts SET deleted_at = NOW(), is_active = 0, updated_at = NOW(), updated_by_user_id = ? '
    . 'WHERE id = ?'
);
$stmt->execute([$user['id'] ?? null, $accountId]);

$afterStmt = $db->prepare('SELECT * FROM accounts WHERE id = ?');
$afterStmt->execute([$accountId]);
$after = $afterStmt->fetch();
audit_log($user, 'accounts.delete', 'account', $accountId, $before, $after);

api_json(['ok' => true]);
