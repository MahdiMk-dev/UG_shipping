<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Sub Branch']);
$filters = $_GET ?? [];

$search = api_string($filters['q'] ?? null);
$branchId = api_int($filters['branch_id'] ?? null);
$status = api_string($filters['status'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['s.deleted_at IS NULL'];
$params = [];

$role = $user['role'] ?? '';
$fullAccess = in_array($role, ['Admin', 'Owner'], true);

if (!$fullAccess) {
    $userBranchId = $user['branch_id'] ?? null;
    if (!$userBranchId) {
        api_error('Branch scope required', 403);
    }
    $where[] = 's.branch_id = ?';
    $params[] = $userBranchId;
}

if ($branchId && $fullAccess) {
    $where[] = 's.branch_id = ?';
    $params[] = $branchId;
}

if ($status) {
    $allowed = ['active', 'inactive'];
    if (!in_array($status, $allowed, true)) {
        api_error('Invalid status', 422);
    }
    $where[] = 's.status = ?';
    $params[] = $status;
}

if ($search) {
    $where[] = '(s.name LIKE ? OR s.phone LIKE ? OR s.position LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = 'SELECT s.id, s.name, s.phone, s.position, s.branch_id, b.name AS branch_name, '
    . 's.base_salary, s.status, s.hired_at, s.created_at, s.updated_at '
    . 'FROM staff_members s '
    . 'LEFT JOIN branches b ON b.id = s.branch_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY s.id DESC LIMIT ? OFFSET ?';

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
