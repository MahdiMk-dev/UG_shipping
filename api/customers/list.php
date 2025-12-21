<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$search = api_string($filters['q'] ?? null);
$subBranchId = api_int($filters['sub_branch_id'] ?? null);
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

if (!$fullAccess) {
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $where[] = 'c.sub_branch_id = ?';
    $params[] = $branchId;
}

if ($search) {
    $where[] = '(c.name LIKE ? OR c.code LIKE ? OR c.phone LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($subBranchId && $fullAccess) {
    $where[] = 'c.sub_branch_id = ?';
    $params[] = $subBranchId;
}

if ($isSystem !== null) {
    $where[] = 'c.is_system = ?';
    $params[] = $isSystem;
}

$sql = 'SELECT c.id, c.name, c.code, c.phone, c.address, c.sub_branch_id, '
    . 'b.name AS sub_branch_name, c.balance, c.is_system, ca.username AS portal_username '
    . 'FROM customers c '
    . 'LEFT JOIN branches b ON b.id = c.sub_branch_id '
    . 'LEFT JOIN customer_auth ca ON ca.customer_id = c.id '
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
