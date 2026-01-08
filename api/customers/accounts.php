<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$search = api_string($filters['q'] ?? null);
$subBranchId = api_int($filters['sub_branch_id'] ?? null);
$profileCountryId = api_int($filters['profile_country_id'] ?? null);
$isSystem = api_int($filters['is_system'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$role = $user['role'] ?? '';
$isWarehouse = $role === 'Warehouse';
$fullAccess = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
$branchId = $user['branch_id'] ?? null;

if ($isWarehouse) {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    if ($profileCountryId && (int) $profileCountryId !== (int) $warehouseCountryId) {
        api_error('Forbidden', 403);
    }
    $profileCountryId = $warehouseCountryId;
} elseif (!$fullAccess) {
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $subBranchId = $branchId;
}

$where = ['c.deleted_at IS NULL', '(c.account_id IS NULL OR ca.id IS NOT NULL)'];
$params = [];

if ($search) {
    $where[] = '(c.name LIKE ? OR c.code LIKE ? OR c.phone LIKE ? OR ca.phone LIKE ? OR ca.username LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($subBranchId) {
    $where[] = 'c.sub_branch_id = ?';
    $params[] = $subBranchId;
}

if ($profileCountryId) {
    $where[] = 'c.profile_country_id = ?';
    $params[] = $profileCountryId;
}

if ($isSystem !== null) {
    $where[] = 'c.is_system = ?';
    $params[] = $isSystem;
}

$baseSql = 'SELECT '
    . 'CASE WHEN c.account_id IS NULL THEN -c.id ELSE c.account_id END AS account_key, '
    . 'c.account_id, '
    . 'MAX(c.id) AS primary_customer_id, '
    . 'MIN(c.name) AS customer_name, '
    . 'MIN(c.code) AS customer_code, '
    . 'MAX(c.phone) AS customer_phone, '
    . 'MAX(c.sub_branch_id) AS sub_branch_id, '
    . 'COUNT(c.id) AS profile_count, '
    . 'GROUP_CONCAT(DISTINCT co.name ORDER BY co.name SEPARATOR \', \') AS profile_countries, '
    . 'MAX(c.balance) AS balance '
    . 'FROM customers c '
    . 'LEFT JOIN countries co ON co.id = c.profile_country_id '
    . 'LEFT JOIN customer_accounts ca ON ca.id = c.account_id AND ca.deleted_at IS NULL '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'GROUP BY account_key, c.account_id';

$sql = 'SELECT agg.account_id, agg.primary_customer_id, agg.customer_name, agg.customer_code, agg.customer_phone, '
    . 'agg.sub_branch_id, b.name AS sub_branch_name, agg.profile_count, agg.profile_countries, agg.balance, '
    . 'ca.username AS portal_username, ca.phone AS portal_phone '
    . 'FROM (' . $baseSql . ') agg '
    . 'LEFT JOIN branches b ON b.id = agg.sub_branch_id '
    . 'LEFT JOIN customer_accounts ca ON ca.id = agg.account_id AND ca.deleted_at IS NULL '
    . 'ORDER BY agg.primary_customer_id DESC LIMIT ? OFFSET ?';

$params[] = $limit;
$params[] = $offset;

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $type);
}
$stmt->execute();
$rows = $stmt->fetchAll();

api_json(['ok' => true, 'data' => $rows]);
