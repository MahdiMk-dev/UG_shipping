<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$filters = $_GET ?? [];

$search = api_string($filters['q'] ?? null);
$type = api_string($filters['type'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['p.deleted_at IS NULL'];
$params = [];

if ($type) {
    $allowed = ['shipper', 'consignee'];
    if (!in_array($type, $allowed, true)) {
        api_error('Invalid type', 422);
    }
    $where[] = 'p.type = ?';
    $params[] = $type;
}

if ($search) {
    $where[] = '(p.name LIKE ? OR p.phone LIKE ? OR p.address LIKE ? OR p.note LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = 'SELECT p.id, p.type, p.name, p.phone, p.address, p.note, '
    . 'p.balance, p.created_at, p.updated_at '
    . 'FROM supplier_profiles p '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY p.id DESC LIMIT ? OFFSET ?';

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


