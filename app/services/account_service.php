<?php
declare(strict_types=1);

function fetch_account(PDO $db, int $accountId): array
{
    $stmt = $db->prepare(
        'SELECT id, owner_type, owner_id, name, payment_method_id, currency, balance, is_active '
        . 'FROM accounts WHERE id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();
    if (!$account) {
        api_error('Account not found', 404);
    }
    if (!(bool) ($account['is_active'] ?? false)) {
        api_error('Account is inactive', 409);
    }
    return $account;
}

function create_account_transfer(
    PDO $db,
    ?int $fromAccountId,
    ?int $toAccountId,
    float $amount,
    string $entryType,
    ?string $transferDate,
    ?string $note,
    ?string $referenceType,
    ?int $referenceId,
    ?int $userId
): int {
    if (!$fromAccountId && !$toAccountId) {
        api_error('Transfer must include at least one account', 422);
    }
    if ($fromAccountId && $toAccountId && $fromAccountId === $toAccountId) {
        api_error('Transfer accounts must differ', 422);
    }
    $amountValue = round(abs($amount), 2);
    if ($amountValue <= 0.0) {
        api_error('Transfer amount must be greater than zero', 422);
    }

    $fromAccount = null;
    $toAccount = null;
    if ($fromAccountId) {
        $fromAccount = fetch_account($db, $fromAccountId);
    }
    if ($toAccountId) {
        $toAccount = fetch_account($db, $toAccountId);
    }
    if ($fromAccount && $toAccount) {
        $fromMethod = (int) ($fromAccount['payment_method_id'] ?? 0);
        $toMethod = (int) ($toAccount['payment_method_id'] ?? 0);
        if ($fromMethod <= 0 || $toMethod <= 0 || $fromMethod !== $toMethod) {
            api_error('Transfer accounts must use the same payment method', 422);
        }
        $fromCurrency = strtoupper((string) ($fromAccount['currency'] ?? ''));
        $toCurrency = strtoupper((string) ($toAccount['currency'] ?? ''));
        if ($fromCurrency !== '' && $toCurrency !== '' && $fromCurrency !== $toCurrency) {
            api_error('Transfer accounts must use the same currency', 422);
        }
    }

    $insertTransfer = $db->prepare(
        'INSERT INTO account_transfers '
        . '(from_account_id, to_account_id, amount, entry_type, transfer_date, note, reference_type, reference_id, '
        . 'created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertTransfer->execute([
        $fromAccountId,
        $toAccountId,
        $amountValue,
        $entryType,
        $transferDate,
        $note,
        $referenceType,
        $referenceId,
        $userId,
    ]);

    $transferId = (int) $db->lastInsertId();
    $insertEntry = $db->prepare(
        'INSERT INTO account_entries '
        . '(account_id, transfer_id, entry_type, amount, entry_date, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?)'
    );
    $updateBalance = $db->prepare('UPDATE accounts SET balance = balance + ? WHERE id = ?');

    if ($fromAccountId) {
        $entryAmount = -$amountValue;
        $insertEntry->execute([
            $fromAccountId,
            $transferId,
            $entryType,
            $entryAmount,
            $transferDate,
            $userId,
        ]);
        $updateBalance->execute([$entryAmount, $fromAccountId]);
    }

    if ($toAccountId) {
        $entryAmount = $amountValue;
        $insertEntry->execute([
            $toAccountId,
            $transferId,
            $entryType,
            $entryAmount,
            $transferDate,
            $userId,
        ]);
        $updateBalance->execute([$entryAmount, $toAccountId]);
    }

    return $transferId;
}

function cancel_account_transfer(PDO $db, int $transferId, string $reason, ?int $userId): void
{
    $transferStmt = $db->prepare(
        'SELECT id, status FROM account_transfers WHERE id = ?'
    );
    $transferStmt->execute([$transferId]);
    $transfer = $transferStmt->fetch();
    if (!$transfer) {
        api_error('Account transfer not found', 404);
    }
    if (($transfer['status'] ?? '') !== 'active') {
        api_error('Account transfer already canceled', 409);
    }

    $entryStmt = $db->prepare(
        'SELECT id, account_id, amount FROM account_entries WHERE transfer_id = ? AND status = ?'
    );
    $entryStmt->execute([$transferId, 'active']);
    $entries = $entryStmt->fetchAll();

    $updateTransfer = $db->prepare(
        'UPDATE account_transfers SET status = ?, canceled_at = NOW(), canceled_reason = ?, '
        . 'canceled_by_user_id = ?, updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
    );
    $updateTransfer->execute([
        'canceled',
        $reason,
        $userId,
        $userId,
        $transferId,
    ]);

    $updateEntry = $db->prepare(
        'UPDATE account_entries SET status = ?, canceled_at = NOW(), canceled_reason = ?, '
        . 'canceled_by_user_id = ? WHERE id = ?'
    );
    $updateBalance = $db->prepare('UPDATE accounts SET balance = balance - ? WHERE id = ?');

    foreach ($entries as $entry) {
        $entryId = (int) $entry['id'];
        $amount = (float) ($entry['amount'] ?? 0);
        $accountId = (int) $entry['account_id'];
        $updateEntry->execute([
            'canceled',
            $reason,
            $userId,
            $entryId,
        ]);
        if ($accountId) {
            $updateBalance->execute([$amount, $accountId]);
        }
    }
}
