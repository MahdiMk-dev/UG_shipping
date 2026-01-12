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
    . 'COALESCE(bb.balance, 0) AS balance, '
    . 'COALESCE(cb.due_total, 0) AS customer_due_total, '
    . 'COALESCE(cb.balance_count, 0) AS customer_balance_count '
    . 'FROM branches b '
    . 'LEFT JOIN countries c ON c.id = b.country_id '
    . 'LEFT JOIN branches p ON p.id = b.parent_branch_id '
    . 'LEFT JOIN ('
        . 'SELECT branch_id, SUM(amount) AS balance '
        . 'FROM branch_balance_entries '
        . 'GROUP BY branch_id'
    . ') bb ON bb.branch_id = b.id '
    . 'LEFT JOIN ('
        . 'SELECT agg.sub_branch_id, '
        . 'SUM(CASE WHEN agg.balance > 0 THEN agg.balance ELSE 0 END) AS due_total, '
        . 'SUM(CASE WHEN agg.balance <> 0 THEN 1 ELSE 0 END) AS balance_count '
        . 'FROM ('
            . 'SELECT '
            . 'CASE WHEN c.account_id IS NULL THEN -c.id ELSE c.account_id END AS account_key, '
            . 'c.sub_branch_id, MAX(c.balance) AS balance '
            . 'FROM customers c '
            . 'WHERE c.deleted_at IS NULL AND c.is_system = 0 AND c.sub_branch_id IS NOT NULL '
            . 'GROUP BY account_key, c.sub_branch_id'
        . ') agg '
        . 'GROUP BY agg.sub_branch_id'
    . ') cb ON cb.sub_branch_id = b.id '
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
