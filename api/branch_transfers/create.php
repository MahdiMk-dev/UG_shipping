<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/account_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$fromBranchId = api_int($input['from_branch_id'] ?? null);
$toBranchId = api_int($input['to_branch_id'] ?? null);
$fromAccountId = api_int($input['from_account_id'] ?? null);
$toAccountId = api_int($input['to_account_id'] ?? null);
$amount = api_float($input['amount'] ?? null);
$transferDate = api_string($input['transfer_date'] ?? null);
$note = api_string($input['note'] ?? null);

if (!$fromBranchId || !$fromAccountId || !$toAccountId || $amount === null) {
    api_error('from_branch_id, from_account_id, to_account_id, and amount are required', 422);
}
if ($toBranchId && $fromBranchId === $toBranchId) {
    api_error('from_branch_id and to_branch_id must differ', 422);
}
if ($amount <= 0) {
    api_error('amount must be greater than zero', 422);
}
if ($transferDate !== null && strtotime($transferDate) === false) {
    api_error('Invalid transfer_date', 422);
}

$db = db();
$branchStmt = $db->prepare('SELECT id FROM branches WHERE id = ? AND deleted_at IS NULL');
$branchStmt->execute([$fromBranchId]);
if (!$branchStmt->fetch()) {
    api_error('From branch not found', 404);
}
if ($toBranchId) {
    $branchStmt->execute([$toBranchId]);
    if (!$branchStmt->fetch()) {
        api_error('To branch not found', 404);
    }
}

$fromAccount = fetch_account($db, $fromAccountId);
$toAccount = fetch_account($db, $toAccountId);
if ($fromAccount['owner_type'] !== 'branch' || (int) $fromAccount['owner_id'] !== $fromBranchId) {
    api_error('From account must belong to the from branch', 422);
}
if ($toBranchId) {
    if ($toAccount['owner_type'] !== 'branch' || (int) $toAccount['owner_id'] !== $toBranchId) {
        api_error('To account must belong to the to branch', 422);
    }
} elseif ($toAccount['owner_type'] !== 'admin') {
    api_error('To account must be an admin account', 422);
}

$db->beginTransaction();

try {
    $stmt = $db->prepare(
        'INSERT INTO branch_transfers '
        . '(from_branch_id, to_branch_id, amount, transfer_date, note, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $fromBranchId,
        $toBranchId,
        $amount,
        $transferDate,
        $note,
        $user['id'] ?? null,
    ]);

    $transferId = (int) $db->lastInsertId();
    $transferLedgerId = create_account_transfer(
        $db,
        $fromAccountId,
        $toAccountId,
        (float) $amount,
        'branch_transfer',
        $transferDate,
        $note,
        'branch_transfer',
        $transferId,
        $user['id'] ?? null
    );
    $db->prepare('UPDATE branch_transfers SET account_transfer_id = ? WHERE id = ?')
        ->execute([$transferLedgerId, $transferId]);

    $rowStmt = $db->prepare('SELECT * FROM branch_transfers WHERE id = ?');
    $rowStmt->execute([$transferId]);
    $after = $rowStmt->fetch();
    audit_log($user, 'branch_transfers.create', 'branch_transfer', $transferId, null, $after);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to create branch transfer', 500);
}

api_json(['ok' => true, 'id' => $transferId]);
