<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = auth_require_user();

$accountId = api_int($_GET['account_id'] ?? null);
$customerId = api_int($_GET['customer_id'] ?? null);

if (!$accountId && !$customerId) {
    api_error('account_id or customer_id is required', 422);
}

$role = $user['role'] ?? '';
$isWarehouse = $role === 'Warehouse';
$fullAccess = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
$branchId = $user['branch_id'] ?? null;

$where = ['c.deleted_at IS NULL'];
$params = [];

if ($accountId) {
    $where[] = 'c.account_id = ?';
    $params[] = $accountId;
}

if ($customerId) {
    $where[] = 'c.id = ?';
    $params[] = $customerId;
}

if ($isWarehouse) {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    $where[] = 'c.profile_country_id = ?';
    $params[] = $warehouseCountryId;
} elseif (!$fullAccess) {
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $where[] = 'c.sub_branch_id = ?';
    $params[] = $branchId;
}

$sql = 'SELECT c.id, c.name, c.code, c.profile_country_id, co.name AS profile_country_name, '
    . 'c.created_at, c.sub_branch_id, b.name AS sub_branch_name, '
    . 'COUNT(o.id) AS orders_count '
    . 'FROM customers c '
    . 'LEFT JOIN countries co ON co.id = c.profile_country_id '
    . 'LEFT JOIN branches b ON b.id = c.sub_branch_id '
    . 'LEFT JOIN orders o ON o.customer_id = c.id AND o.deleted_at IS NULL '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'GROUP BY c.id '
    . 'ORDER BY c.id DESC';

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $type);
}
$stmt->execute();
$rows = $stmt->fetchAll();

api_json(['ok' => true, 'data' => $rows]);
