<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();

$filters = $_GET ?? [];
$branchId = api_int($filters['branch_id'] ?? null);
$dateFrom = api_string($filters['date_from'] ?? null);
$dateTo = api_string($filters['date_to'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$role = $user['role'] ?? '';
if ($role === 'Sub Branch') {
    $branchId = api_int($user['branch_id'] ?? null);
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
} elseif (in_array($role, ['Warehouse', 'Staff'], true)) {
    api_error('Forbidden', 403);
}

if ($dateFrom !== null && strtotime($dateFrom) === false) {
    api_error('Invalid date_from', 422);
}
if ($dateTo !== null && strtotime($dateTo) === false) {
    api_error('Invalid date_to', 422);
}

$where = ['t.deleted_at IS NULL'];
$params = [];

if ($branchId) {
    $where[] = '(t.from_branch_id = ? OR t.to_branch_id = ?)';
    $params[] = $branchId;
    $params[] = $branchId;
}

if ($dateFrom) {
    $where[] = 'COALESCE(t.transfer_date, DATE(t.created_at)) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'COALESCE(t.transfer_date, DATE(t.created_at)) <= ?';
    $params[] = $dateTo;
}

$sql = 'SELECT t.id, t.from_branch_id, bf.name AS from_branch_name, '
    . 't.to_branch_id, bt.name AS to_branch_name, t.amount, t.transfer_date, '
    . 't.note, t.created_at '
    . 'FROM branch_transfers t '
    . 'LEFT JOIN branches bf ON bf.id = t.from_branch_id '
    . 'LEFT JOIN branches bt ON bt.id = t.to_branch_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY COALESCE(t.transfer_date, DATE(t.created_at)) DESC, t.id DESC '
    . 'LIMIT ? OFFSET ?';

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
