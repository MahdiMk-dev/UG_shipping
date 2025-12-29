<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$search = api_string($filters['q'] ?? null);
$limit = api_int($filters['limit'] ?? 200, 200);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(500, $limit ?? 200));
$offset = max(0, $offset ?? 0);

$where = ['deleted_at IS NULL'];
$params = [];

if ($search) {
    $where[] = 'name LIKE ?';
    $params[] = '%' . $search . '%';
}

$sql = 'SELECT id, name FROM goods_types';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY name ASC LIMIT ? OFFSET ?';

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
