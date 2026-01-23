<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$filters = $_GET ?? [];

$search = api_string($filters['q'] ?? null);
$type = api_string($filters['type'] ?? null);
$status = api_string($filters['status'] ?? null);
$limit = api_int($filters['limit'] ?? 50) ?? 50;
$offset = api_int($filters['offset'] ?? 0) ?? 0;

$limit = max(1, min(200, $limit));
$offset = max(0, $offset);

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($type) {
    $where[] = 'type = ?';
    $params[] = $type;
}
if ($status) {
    $where[] = 'status = ?';
    $params[] = $status;
}

$sql = 'SELECT id, name, type, current_balance, status '
    . 'FROM partners '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY name ASC '
    . 'LIMIT ? OFFSET ?';
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
