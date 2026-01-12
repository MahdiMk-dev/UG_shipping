<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$input = api_read_input();

$name = api_string($input['name'] ?? null);
$code = api_string($input['code'] ?? null);
$phone = api_string($input['phone'] ?? null);
$address = api_string($input['address'] ?? null);
$note = api_string($input['note'] ?? null);
$subBranchId = api_int($input['sub_branch_id'] ?? null);
$profileCountryId = api_int($input['profile_country_id'] ?? null);
$portalUsername = api_string($input['portal_username'] ?? null);
$portalPassword = api_string($input['portal_password'] ?? null);
$accountIdInput = api_int($input['account_id'] ?? null);

$role = $user['role'] ?? '';
$fullAccess = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
if (!$fullAccess) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $subBranchId = $branchId;
}

if (!$code) {
    api_error('code is required', 422);
}
if (!$profileCountryId) {
    api_error('profile_country_id is required', 422);
}

$db = db();
$account = null;
if ($accountIdInput) {
    $accountStmt = $db->prepare(
        'SELECT id, username, phone, sub_branch_id FROM customer_accounts WHERE id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $accountStmt->execute([$accountIdInput]);
    $account = $accountStmt->fetch();
    if (!$account) {
        api_error('Portal account not found', 404);
    }
    $portalUsername = $account['username'] ?? null;
} else {
    if (!$portalUsername) {
        api_error('Portal username is required', 422);
    }
    $accountStmt = $db->prepare(
        'SELECT id, username, phone, sub_branch_id FROM customer_accounts WHERE username = ? AND deleted_at IS NULL LIMIT 1'
    );
    $accountStmt->execute([$portalUsername]);
    $account = $accountStmt->fetch();
    if (!$account) {
        if (!$phone) {
            api_error('phone is required', 422);
        }
        if (strlen($phone) < 8) {
            api_error('phone must be at least 8 characters', 422);
        }
        $phoneCheck = $db->prepare('SELECT id FROM customer_accounts WHERE phone = ? AND deleted_at IS NULL LIMIT 1');
        $phoneCheck->execute([$phone]);
        if ($phoneCheck->fetch()) {
            api_error('Phone already belongs to another portal account', 409);
        }
    }
}

if ($account) {
    if ($phone && !empty($account['phone']) && $account['phone'] !== $phone) {
        api_error('Portal phone does not match the existing account', 409);
    }
    if (!empty($account['sub_branch_id']) && $subBranchId
        && (int) $account['sub_branch_id'] !== (int) $subBranchId
    ) {
        api_error('Branch must match the existing account branch', 409);
    }
    if (!empty($account['sub_branch_id'])) {
        $subBranchId = (int) $account['sub_branch_id'];
    }
    $profileCheck = $db->prepare(
        'SELECT id FROM customers WHERE account_id = ? AND profile_country_id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $profileCheck->execute([(int) $account['id'], $profileCountryId]);
    if ($profileCheck->fetch()) {
        api_error('Profile already exists for this country', 409);
    }
} elseif (!$portalPassword) {
    api_error('Portal password is required for new accounts', 422);
}

if (!$name) {
    if ($account) {
        $nameStmt = $db->prepare(
            'SELECT name FROM customers WHERE account_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $nameStmt->execute([(int) $account['id']]);
        $nameRow = $nameStmt->fetch();
        $name = $nameRow['name'] ?? null;
    }
    if (!$name) {
        api_error('name is required', 422);
    }
}

$profilePhone = $phone;
if (!$profilePhone && $account && !empty($account['phone'])) {
    $profilePhone = $account['phone'];
}

$db->beginTransaction();
try {
    $accountId = null;
    $initialBalance = 0.0;
    $initialPoints = 0.0;
    if ($account) {
        $accountId = (int) $account['id'];
        $shouldUpdateAccount = $portalPassword
            || (!empty($profilePhone) && empty($account['phone']))
            || ($subBranchId && empty($account['sub_branch_id']));
        if ($shouldUpdateAccount) {
            $accountFields = [];
            $accountParams = [];
            if ($portalPassword) {
                $accountFields[] = 'password_hash = ?';
                $accountParams[] = password_hash($portalPassword, PASSWORD_DEFAULT);
            }
            if (!empty($profilePhone) && empty($account['phone'])) {
                $accountFields[] = 'phone = ?';
                $accountParams[] = $profilePhone;
            }
            if ($subBranchId && empty($account['sub_branch_id'])) {
                $accountFields[] = 'sub_branch_id = ?';
                $accountParams[] = $subBranchId;
            }
            if (!empty($accountFields)) {
                $accountFields[] = 'updated_at = NOW()';
                $accountFields[] = 'updated_by_user_id = ?';
                $accountParams[] = $user['id'] ?? null;
                $accountParams[] = $accountId;
                $accountUpdate = $db->prepare(
                    'UPDATE customer_accounts SET ' . implode(', ', $accountFields) . ' WHERE id = ?'
                );
                $accountUpdate->execute($accountParams);
            }
        }
        $balanceStmt = $db->prepare(
            'SELECT balance, points_balance FROM customers '
            . 'WHERE account_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $balanceStmt->execute([$accountId]);
        $balanceRow = $balanceStmt->fetch();
        if ($balanceRow && isset($balanceRow['balance'])) {
            $initialBalance = (float) $balanceRow['balance'];
        }
        if ($balanceRow && isset($balanceRow['points_balance'])) {
            $initialPoints = (float) $balanceRow['points_balance'];
        }
    } else {
        $hash = password_hash($portalPassword, PASSWORD_DEFAULT);
        $accountInsert = $db->prepare(
            'INSERT INTO customer_accounts (phone, username, password_hash, sub_branch_id, created_by_user_id) '
            . 'VALUES (?, ?, ?, ?, ?)'
        );
        $accountInsert->execute([
            $profilePhone,
            $portalUsername,
            $hash,
            $subBranchId,
            $user['id'] ?? null,
        ]);
        $accountId = (int) $db->lastInsertId();
    }

    $stmt = $db->prepare(
        'INSERT INTO customers (account_id, name, code, phone, address, note, sub_branch_id, profile_country_id, '
        . 'balance, points_balance, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $accountId,
        $name,
        $code,
        $profilePhone,
        $address,
        $note,
        $subBranchId,
        $profileCountryId,
        $initialBalance,
        $initialPoints,
        $user['id'] ?? null,
    ]);

    $customerId = (int) $db->lastInsertId();

    $db->commit();

    audit_log(
        $user,
        'customer.create',
        'customer',
        $customerId,
        null,
        [
            'name' => $name,
            'code' => $code,
            'phone' => $profilePhone,
            'address' => $address,
            'note' => $note,
            'sub_branch_id' => $subBranchId,
            'profile_country_id' => $profileCountryId,
            'portal_username' => $portalUsername,
        ]
    );
} catch (PDOException $e) {
    $db->rollBack();
    if ((int) $e->getCode() === 23000) {
        api_error('Customer code or portal account already exists', 409);
    }
    api_error('Failed to create customer', 500);
}

api_json(['ok' => true, 'id' => $customerId]);
