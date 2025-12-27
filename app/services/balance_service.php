<?php
declare(strict_types=1);

function adjust_customer_balance(PDO $db, int $customerId, float $delta): void
{
    if ($customerId <= 0 || abs($delta) < 0.0001) {
        return;
    }

    $stmt = $db->prepare('UPDATE customers SET balance = balance + ? WHERE id = ?');
    $stmt->execute([$delta, $customerId]);
}

function record_branch_balance(
    PDO $db,
    int $branchId,
    float $amount,
    string $type,
    ?string $referenceType,
    ?int $referenceId,
    ?int $userId,
    ?string $note = null
): void {
    if ($branchId <= 0 || abs($amount) < 0.0001) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO branch_balance_entries '
        . '(branch_id, entry_type, amount, reference_type, reference_id, note, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $branchId,
        $type,
        $amount,
        $referenceType,
        $referenceId,
        $note,
        $userId,
    ]);
}

function record_customer_balance(
    PDO $db,
    int $customerId,
    ?int $branchId,
    float $amount,
    string $type,
    ?string $referenceType,
    ?int $referenceId,
    ?int $userId,
    ?string $note = null
): void {
    if ($customerId <= 0 || abs($amount) < 0.0001) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO customer_balance_entries '
        . '(customer_id, branch_id, entry_type, amount, reference_type, reference_id, note, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $customerId,
        $branchId ?: null,
        $type,
        $amount,
        $referenceType,
        $referenceId,
        $note,
        $userId,
    ]);
}
