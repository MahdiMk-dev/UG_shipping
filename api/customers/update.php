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
    'SELECT c.id, c.is_system, c.sub_branch_id, c.name, c.code, c.phone, c.address, ca.username AS portal_username '
    . 'FROM customers c '
    . 'LEFT JOIN customer_auth ca ON ca.customer_id = c.id '
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
$authFields = [];
$authParams = [];
$portalUsername = null;
$portalPassword = null;
$name = null;
$code = null;
$subBranchId = null;

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

if (!empty($fields)) {
    $fields[] = 'updated_at = NOW()';
    $fields[] = 'updated_by_user_id = ?';
    $params[] = $user['id'] ?? null;
}

$sql = '';
if (!empty($fields)) {
    $params[] = $customerId;
    $sql = 'UPDATE customers SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
}

try {
    $db->beginTransaction();

    if ($sql) {
        $update = $db->prepare($sql);
        $update->execute($params);
    }

    if ($portalUsername || $portalPassword) {
        $authStmt = $db->prepare('SELECT id, username FROM customer_auth WHERE customer_id = ? LIMIT 1');
        $authStmt->execute([$customerId]);
        $authRow = $authStmt->fetch();

        if ($portalUsername && (!$authRow || $portalUsername !== $authRow['username'])) {
            $checkStmt = $db->prepare('SELECT id FROM customer_auth WHERE username = ? AND customer_id <> ? LIMIT 1');
            $checkStmt->execute([$portalUsername, $customerId]);
            if ($checkStmt->fetch()) {
                $db->rollBack();
                api_error('Portal username already exists', 409);
            }
        }

        if (!$authRow) {
            if (!$portalUsername || !$portalPassword) {
                $db->rollBack();
                api_error('Portal username and password are required to create login', 422);
            }
            $hash = password_hash($portalPassword, PASSWORD_DEFAULT);
            $insertAuth = $db->prepare(
                'INSERT INTO customer_auth (customer_id, username, password_hash, created_by_user_id) '
                . 'VALUES (?, ?, ?, ?)'
            );
            $insertAuth->execute([$customerId, $portalUsername, $hash, $user['id'] ?? null]);
        } else {
            if ($portalUsername) {
                $authFields[] = 'username = ?';
                $authParams[] = $portalUsername;
            }
            if ($portalPassword) {
                $authFields[] = 'password_hash = ?';
                $authParams[] = password_hash($portalPassword, PASSWORD_DEFAULT);
            }
            if (!empty($authFields)) {
                $authFields[] = 'updated_at = NOW()';
                $authFields[] = 'updated_by_user_id = ?';
                $authParams[] = $user['id'] ?? null;
                $authParams[] = $authRow['id'];
                $authSql = 'UPDATE customer_auth SET ' . implode(', ', $authFields) . ' WHERE id = ?';
                $authUpdate = $db->prepare($authSql);
                $authUpdate->execute($authParams);
            }
        }
    }

    $db->commit();

    $after = array_merge($customer, [
        'name' => $name ?? $customer['name'],
        'code' => $code ?? $customer['code'],
        'phone' => array_key_exists('phone', $input) ? api_string($input['phone'] ?? null) : $customer['phone'],
        'address' => array_key_exists('address', $input) ? api_string($input['address'] ?? null) : $customer['address'],
        'sub_branch_id' => $subBranchId ?? $customer['sub_branch_id'],
        'portal_username' => $portalUsername ?? $customer['portal_username'],
    ]);

    audit_log($user, 'customer.update', 'customer', $customerId, $customer, $after);
} catch (PDOException $e) {
    $db->rollBack();
    if ((int) $e->getCode() === 23000) {
        api_error('Customer code already exists', 409);
    }
    api_error('Failed to update customer', 500);
}

api_json(['ok' => true]);
