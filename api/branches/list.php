<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$type = api_string($filters['type'] ?? null);
$countryId = api_int($filters['country_id'] ?? null);
$search = api_string($filters['q'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['b.deleted_at IS NULL'];
$params = [];

if ($type) {
    $allowed = ['head', 'main', 'sub', 'warehouse'];
    if (!in_array($type, $allowed, true)) {
        api_error('Invalid branch type', 422);
    }
    $where[] = 'b.type = ?';
    $params[] = $type;
}

if ($countryId) {
    $where[] = 'b.country_id = ?';
    $params[] = $countryId;
}

if ($search) {
    $where[] = 'b.name LIKE ?';
    $params[] = '%' . $search . '%';
}

$sql = 'SELECT b.id, b.name, b.type, b.country_id, c.name AS country_name, '
    . 'b.parent_branch_id, p.name AS parent_branch_name, b.phone, b.address, '
    . 'COALESCE(bb.balance, 0) AS balance '
    . 'FROM branches b '
    . 'LEFT JOIN countries c ON c.id = b.country_id '
    . 'LEFT JOIN branches p ON p.id = b.parent_branch_id '
    . 'LEFT JOIN (SELECT branch_id, SUM(amount) AS balance FROM branch_balance_entries GROUP BY branch_id) bb '
    . 'ON bb.branch_id = b.id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY b.id DESC LIMIT ? OFFSET ?';

$params[] = $limit;
$params[] = $offset;

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $typeParam = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $typeParam);
}
$stmt->execute();
$rows = $stmt->fetchAll();

api_json(['ok' => true, 'data' => $rows]);
