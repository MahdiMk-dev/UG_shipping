<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);
$filters = $_GET ?? [];

$search = api_string($filters['q'] ?? null);
$type = api_string($filters['type'] ?? null);
$countryId = api_int($filters['country_id'] ?? null);
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
    $where[] = '(p.name LIKE ? OR p.phone LIKE ? OR p.address LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$role = $user['role'] ?? '';
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    $where[] = 'p.country_id = ?';
    $params[] = $warehouseCountryId;
} elseif ($countryId) {
    $where[] = 'p.country_id = ?';
    $params[] = $countryId;
}

$sql = 'SELECT p.id, p.type, p.name, p.phone, p.address, p.country_id, c.name AS country_name, '
    . 'p.balance, p.created_at, p.updated_at '
    . 'FROM partner_profiles p '
    . 'LEFT JOIN countries c ON c.id = p.country_id '
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
