<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$fromBranchId = api_int($input['from_branch_id'] ?? null);
$toBranchId = api_int($input['to_branch_id'] ?? null);
$amount = api_float($input['amount'] ?? null);
$transferDate = api_string($input['transfer_date'] ?? null);
$note = api_string($input['note'] ?? null);

if (!$fromBranchId || !$toBranchId || $amount === null) {
    api_error('from_branch_id, to_branch_id, and amount are required', 422);
}
if ($fromBranchId === $toBranchId) {
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
$branchStmt->execute([$toBranchId]);
if (!$branchStmt->fetch()) {
    api_error('To branch not found', 404);
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
    record_branch_balance(
        $db,
        $fromBranchId,
        -$amount,
        'transfer_out',
        'branch_transfer',
        $transferId,
        $user['id'] ?? null,
        $note
    );
    record_branch_balance(
        $db,
        $toBranchId,
        $amount,
        'transfer_in',
        'branch_transfer',
        $transferId,
        $user['id'] ?? null,
        $note
    );

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
