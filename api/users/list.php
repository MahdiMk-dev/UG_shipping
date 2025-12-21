<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner']);
$filters = $_GET ?? [];

$search = api_string($filters['q'] ?? null);
$roleId = api_int($filters['role_id'] ?? null);
$branchId = api_int($filters['branch_id'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['u.deleted_at IS NULL'];
$params = [];

if ($search) {
    $where[] = '(u.name LIKE ? OR u.username LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($roleId) {
    $where[] = 'u.role_id = ?';
    $params[] = $roleId;
}

if ($branchId) {
    $where[] = 'u.branch_id = ?';
    $params[] = $branchId;
}

$sql = 'SELECT u.id, u.name, u.username, u.role_id, r.name AS role_name, '
    . 'u.branch_id, b.name AS branch_name, u.phone, u.address '
    . 'FROM users u '
    . 'LEFT JOIN roles r ON r.id = u.role_id '
    . 'LEFT JOIN branches b ON b.id = u.branch_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY u.id DESC LIMIT ? OFFSET ?';

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
