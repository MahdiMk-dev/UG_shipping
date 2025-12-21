<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$filters = $_GET ?? [];

$user = auth_require_user();

$status = api_string($filters['status'] ?? null);
$shippingType = api_string($filters['shipping_type'] ?? null);
$originCountryId = api_int($filters['origin_country_id'] ?? null);
$originCountry = api_string($filters['origin_country'] ?? null);
$search = api_string($filters['q'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['s.deleted_at IS NULL'];
$params = [];

$role = $user['role'] ?? '';
$readOnly = is_read_only_role($user) && $role !== 'Warehouse';
if ($readOnly) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $where[] =
        'EXISTS (SELECT 1 FROM orders o '
        . 'INNER JOIN customers cu2 ON cu2.id = o.customer_id '
        . 'WHERE o.shipment_id = s.id AND o.deleted_at IS NULL AND cu2.sub_branch_id = ?)';
    $params[] = $branchId;
}
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    $where[] = 's.origin_country_id = ?';
    $params[] = $warehouseCountryId;
}

if ($status) {
    $allowed = ['active', 'departed', 'airport', 'arrived', 'distributed'];
    if (!in_array($status, $allowed, true)) {
        api_error('Invalid status filter', 422);
    }
    $where[] = 's.status = ?';
    $params[] = $status;
}

if ($shippingType) {
    $allowed = ['air', 'sea', 'land'];
    if (!in_array($shippingType, $allowed, true)) {
        api_error('Invalid shipping_type filter', 422);
    }
    $where[] = 's.shipping_type = ?';
    $params[] = $shippingType;
}

if ($originCountryId) {
    $where[] = 's.origin_country_id = ?';
    $params[] = $originCountryId;
} elseif ($originCountry) {
    $where[] = '(c.name LIKE ? OR c.iso2 LIKE ? OR c.iso3 LIKE ?)';
    $like = '%' . $originCountry . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($search) {
    $where[] = 's.shipment_number LIKE ?';
    $params[] = '%' . $search . '%';
}

$sql = 'SELECT s.id, s.shipment_number, s.status, s.shipping_type, s.origin_country_id, c.name AS origin_country, '
    . 's.shipment_date, s.departure_date, s.arrival_date, s.default_rate, s.default_rate_unit, s.note, '
    . 's.created_at, s.updated_at, cu.name AS created_by_name, uu.name AS updated_by_name '
    . 'FROM shipments s '
    . 'LEFT JOIN countries c ON c.id = s.origin_country_id '
    . 'LEFT JOIN users cu ON cu.id = s.created_by_user_id '
    . 'LEFT JOIN users uu ON uu.id = s.updated_by_user_id '
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

$role = $user['role'] ?? '';
$showMeta = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
if (!$showMeta) {
    foreach ($rows as &$row) {
        unset($row['created_by_name'], $row['updated_by_name']);
    }
    unset($row);
}

api_json(['ok' => true, 'data' => $rows]);
