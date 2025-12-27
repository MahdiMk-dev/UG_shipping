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
$accountId = api_int($filters['account_id'] ?? null);
$isSystem = api_int($filters['is_system'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['c.deleted_at IS NULL'];
$params = [];

$role = $user['role'] ?? '';
$fullAccess = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
$branchId = $user['branch_id'] ?? null;

if (!$fullAccess && $role !== 'Warehouse') {
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $where[] = 'c.sub_branch_id = ?';
    $params[] = $branchId;
}
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    $where[] = 'c.profile_country_id = ?';
    $params[] = $warehouseCountryId;
}

if ($search) {
    $where[] = '(c.name LIKE ? OR c.code LIKE ? OR c.phone LIKE ? OR ca.phone LIKE ? OR ca.username LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($subBranchId && $fullAccess) {
    $where[] = 'c.sub_branch_id = ?';
    $params[] = $subBranchId;
}

if ($profileCountryId && $fullAccess) {
    $where[] = 'c.profile_country_id = ?';
    $params[] = $profileCountryId;
}

if ($accountId) {
    $where[] = 'c.account_id = ?';
    $params[] = $accountId;
}

if ($isSystem !== null) {
    $where[] = 'c.is_system = ?';
    $params[] = $isSystem;
}

$sql = 'SELECT c.id, c.account_id, c.name, c.code, c.phone, c.address, c.sub_branch_id, '
    . 'b.name AS sub_branch_name, c.profile_country_id, co.name AS profile_country_name, '
    . 'c.balance, c.is_system, ca.username AS portal_username, ca.phone AS portal_phone '
    . 'FROM customers c '
    . 'LEFT JOIN branches b ON b.id = c.sub_branch_id '
    . 'LEFT JOIN customer_accounts ca ON ca.id = c.account_id '
    . 'LEFT JOIN countries co ON co.id = c.profile_country_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY c.id DESC LIMIT ? OFFSET ?';

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
