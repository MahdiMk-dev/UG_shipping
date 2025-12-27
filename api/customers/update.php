<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch', 'Warehouse']);
$input = api_read_input();

$customerId = api_int($input['customer_id'] ?? ($input['id'] ?? null));
if (!$customerId) {
    api_error('customer_id is required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT c.id, c.account_id, c.is_system, c.sub_branch_id, c.name, c.code, c.phone, c.address, '
    . 'c.profile_country_id, ca.username AS portal_username, ca.phone AS portal_phone, '
    . 'ca.sub_branch_id AS account_branch_id '
    . 'FROM customers c '
    . 'LEFT JOIN customer_accounts ca ON ca.id = c.account_id '
    . 'WHERE c.id = ? AND c.deleted_at IS NULL'
);
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    api_error('Customer not found', 404);
}
if ((int) $customer['is_system'] === 1) {
    api_error('System customer cannot be edited', 422);
}

$role = $user['role'] ?? '';
$fullAccess = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
if (!$fullAccess) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId || (int) $customer['sub_branch_id'] !== (int) $branchId) {
        api_error('Forbidden', 403);
    }
}

$fields = [];
$params = [];
$accountFields = [];
$accountParams = [];
$portalUsername = null;
$portalPassword = null;
$name = null;
$code = null;
$subBranchId = null;
$profileCountryId = null;
$phone = null;

if (array_key_exists('portal_username', $input)) {
    $portalUsername = api_string($input['portal_username'] ?? null);
    if (!$portalUsername) {
        api_error('portal_username cannot be empty', 422);
    }
}

if (array_key_exists('portal_password', $input)) {
    $portalPassword = api_string($input['portal_password'] ?? null);
    if (!$portalPassword) {
        api_error('portal_password cannot be empty', 422);
    }
}

if (array_key_exists('name', $input)) {
    $name = api_string($input['name'] ?? null);
    if (!$name) {
        api_error('name cannot be empty', 422);
    }
    $fields[] = 'name = ?';
    $params[] = $name;
}

if (array_key_exists('code', $input)) {
    $code = api_string($input['code'] ?? null);
    if (!$code) {
        api_error('code cannot be empty', 422);
    }
    $fields[] = 'code = ?';
    $params[] = $code;
}

if (array_key_exists('phone', $input)) {
    $phone = api_string($input['phone'] ?? null);
    if ($phone && strlen($phone) < 8) {
        api_error('phone must be at least 8 characters', 422);
    }
    $fields[] = 'phone = ?';
    $params[] = $phone;
}

if (array_key_exists('profile_country_id', $input)) {
    $profileCountryId = api_int($input['profile_country_id'] ?? null);
    if (!$profileCountryId) {
        api_error('profile_country_id cannot be empty', 422);
    }
    $fields[] = 'profile_country_id = ?';
    $params[] = $profileCountryId;
}

if (array_key_exists('address', $input)) {
    $fields[] = 'address = ?';
    $params[] = api_string($input['address'] ?? null);
}

if (array_key_exists('sub_branch_id', $input)) {
    if ($fullAccess) {
        $subBranchId = api_int($input['sub_branch_id'] ?? null);
        $fields[] = 'sub_branch_id = ?';
        $params[] = $subBranchId;
    }
}

if (empty($fields)) {
    if (!$portalUsername && !$portalPassword) {
        api_error('No fields to update', 422);
    }
}

$phoneProvided = array_key_exists('phone', $input);
$accountId = $customer['account_id'] ? (int) $customer['account_id'] : null;
$needsAccount = !$accountId && ($portalUsername || $portalPassword);
$effectivePhone = $phoneProvided ? $phone : ($customer['phone'] ?? null);
$subBranchUpdated = $subBranchId !== null;

if ($needsAccount) {
    if (!$portalUsername || !$portalPassword) {
        api_error('Portal username and password are required to create login', 422);
    }
    if (!$effectivePhone) {
        api_error('phone is required to create a portal account', 422);
    }
    if (strlen($effectivePhone) < 8) {
        api_error('phone must be at least 8 characters', 422);
    }
}

if ($portalUsername && $accountId && $portalUsername !== ($customer['portal_username'] ?? null)) {
    $checkStmt = $db->prepare('SELECT id FROM customer_accounts WHERE username = ? AND id <> ? LIMIT 1');
    $checkStmt->execute([$portalUsername, $accountId]);
    if ($checkStmt->fetch()) {
        api_error('Portal username already exists', 409);
    }
}

if (($phoneProvided || $needsAccount) && $effectivePhone) {
    $checkStmt = $db->prepare('SELECT id FROM customer_accounts WHERE phone = ? AND id <> ? LIMIT 1');
    $checkStmt->execute([$effectivePhone, $accountId ?? 0]);
    if ($checkStmt->fetch()) {
        api_error('Portal phone already exists', 409);
    }
}

if ($profileCountryId && $accountId) {
    $checkStmt = $db->prepare(
        'SELECT id FROM customers WHERE account_id = ? AND profile_country_id = ? AND deleted_at IS NULL AND id <> ? LIMIT 1'
    );
    $checkStmt->execute([$accountId, $profileCountryId, $customerId]);
    if ($checkStmt->fetch()) {
        api_error('Profile already exists for this country', 409);
    }
}

try {
    $db->beginTransaction();

    if ($needsAccount) {
        $hash = password_hash($portalPassword, PASSWORD_DEFAULT);
        $accountBranchId = $subBranchId ?? $customer['sub_branch_id'];
        $accountInsert = $db->prepare(
            'INSERT INTO customer_accounts (phone, username, password_hash, sub_branch_id, created_by_user_id) '
            . 'VALUES (?, ?, ?, ?, ?)'
        );
        $accountInsert->execute([
            $effectivePhone,
            $portalUsername,
            $hash,
            $accountBranchId,
            $user['id'] ?? null,
        ]);
        $accountId = (int) $db->lastInsertId();
        $fields[] = 'account_id = ?';
        $params[] = $accountId;
        if (!$phoneProvided) {
            $fields[] = 'phone = ?';
            $params[] = $effectivePhone;
        }
    }

    if (!empty($fields)) {
        $fields[] = 'updated_at = NOW()';
        $fields[] = 'updated_by_user_id = ?';
        $params[] = $user['id'] ?? null;
        $params[] = $customerId;
        $sql = 'UPDATE customers SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
        $update = $db->prepare($sql);
        $update->execute($params);
    }

    if ($accountId) {
        if ($portalUsername && $portalUsername !== ($customer['portal_username'] ?? null)) {
            $accountFields[] = 'username = ?';
            $accountParams[] = $portalUsername;
        }
        if ($portalPassword && !$needsAccount) {
            $accountFields[] = 'password_hash = ?';
            $accountParams[] = password_hash($portalPassword, PASSWORD_DEFAULT);
        }
        if ($phoneProvided || (!$customer['portal_phone'] && $effectivePhone)) {
            $accountFields[] = 'phone = ?';
            $accountParams[] = $effectivePhone;
        }
        if ($subBranchUpdated) {
            $accountFields[] = 'sub_branch_id = ?';
            $accountParams[] = $subBranchId;
        }
        if (!empty($accountFields)) {
            $accountFields[] = 'updated_at = NOW()';
            $accountFields[] = 'updated_by_user_id = ?';
            $accountParams[] = $user['id'] ?? null;
            $accountParams[] = $accountId;
            $accountSql = 'UPDATE customer_accounts SET ' . implode(', ', $accountFields) . ' WHERE id = ?';
            $accountUpdate = $db->prepare($accountSql);
            $accountUpdate->execute($accountParams);
        }

        if ($subBranchUpdated) {
            $branchUpdate = $db->prepare(
                'UPDATE customers SET sub_branch_id = ?, updated_at = NOW(), updated_by_user_id = ? '
                . 'WHERE account_id = ? AND deleted_at IS NULL'
            );
            $branchUpdate->execute([$subBranchId, $user['id'] ?? null, $accountId]);
        }
        if ($phoneProvided || (!$customer['portal_phone'] && $effectivePhone)) {
            $phoneUpdate = $db->prepare(
                'UPDATE customers SET phone = ?, updated_at = NOW(), updated_by_user_id = ? '
                . 'WHERE account_id = ? AND deleted_at IS NULL'
            );
            $phoneUpdate->execute([$effectivePhone, $user['id'] ?? null, $accountId]);
        }
    }

    $db->commit();

    $after = array_merge($customer, [
        'account_id' => $accountId ?? $customer['account_id'],
        'name' => $name ?? $customer['name'],
        'code' => $code ?? $customer['code'],
        'phone' => array_key_exists('phone', $input) ? api_string($input['phone'] ?? null) : $customer['phone'],
        'address' => array_key_exists('address', $input) ? api_string($input['address'] ?? null) : $customer['address'],
        'sub_branch_id' => $subBranchId ?? $customer['sub_branch_id'],
        'profile_country_id' => $profileCountryId ?? $customer['profile_country_id'],
        'portal_username' => $portalUsername ?? $customer['portal_username'],
    ]);

    audit_log($user, 'customer.update', 'customer', $customerId, $customer, $after);
} catch (PDOException $e) {
    $db->rollBack();
    if ((int) $e->getCode() === 23000) {
        api_error('Customer code or profile already exists', 409);
    }
    api_error('Failed to update customer', 500);
}

api_json(['ok' => true]);
