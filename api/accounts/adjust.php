<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/account_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$accountId = api_int($input['account_id'] ?? null);
$type = api_string($input['type'] ?? null);
$amount = api_float($input['amount'] ?? null);
$title = api_string($input['title'] ?? null);
$note = api_string($input['note'] ?? null);
$adjustmentDate = api_string($input['adjustment_date'] ?? null);

if (!$accountId || !$type || $amount === null || !$title) {
    api_error('account_id, type, amount, and title are required', 422);
}
if (!in_array($type, ['deposit', 'withdrawal'], true)) {
    api_error('Invalid type', 422);
}
if ($amount <= 0) {
    api_error('amount must be greater than zero', 422);
}
if ($adjustmentDate !== null && strtotime($adjustmentDate) === false) {
    api_error('Invalid adjustment_date', 422);
}

$db = db();
fetch_account($db, $accountId);

$db->beginTransaction();
try {
    $insertStmt = $db->prepare(
        'INSERT INTO account_adjustments '
        . '(account_id, type, amount, title, note, adjustment_date, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insertStmt->execute([
        $accountId,
        $type,
        $amount,
        $title,
        $note,
        $adjustmentDate,
        $user['id'] ?? null,
    ]);

    $adjustmentId = (int) $db->lastInsertId();
    $fromAccountId = $type === 'withdrawal' ? $accountId : null;
    $toAccountId = $type === 'deposit' ? $accountId : null;

    $transferId = create_account_transfer(
        $db,
        $fromAccountId,
        $toAccountId,
        (float) $amount,
        'adjustment',
        $adjustmentDate,
        $note,
        'account_adjustment',
        $adjustmentId,
        $user['id'] ?? null
    );

    $db->prepare('UPDATE account_adjustments SET account_transfer_id = ? WHERE id = ?')
        ->execute([$transferId, $adjustmentId]);

    $afterStmt = $db->prepare('SELECT * FROM account_adjustments WHERE id = ?');
    $afterStmt->execute([$adjustmentId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'accounts.adjust', 'account_adjustment', $adjustmentId, null, $after);

    $db->commit();
    api_json(['ok' => true, 'id' => $adjustmentId]);
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to record account adjustment', 500);
}
