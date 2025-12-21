<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$shipmentId = api_int($filters['shipment_id'] ?? null);
$limit = api_int($filters['limit'] ?? 200, 200);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(500, $limit ?? 200));
$offset = max(0, $offset ?? 0);

$where = [];
$params = [];

$role = $user['role'] ?? '';
$readOnly = is_read_only_role($user) && $role !== 'Warehouse';
if ($readOnly) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
}
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    $where[] = 'EXISTS (SELECT 1 FROM shipments s WHERE s.id = c.shipment_id AND s.deleted_at IS NULL AND s.origin_country_id = ?)';
    $params[] = $warehouseCountryId;
}

if ($shipmentId) {
    $where[] = 'c.shipment_id = ?';
    $params[] = $shipmentId;
}

$sql = 'SELECT DISTINCT c.id, c.shipment_id, c.name FROM collections c';
if ($readOnly) {
    $sql .= ' INNER JOIN orders o ON o.collection_id = c.id AND o.deleted_at IS NULL '
        . 'INNER JOIN customers cu ON cu.id = o.customer_id AND cu.sub_branch_id = ?';
    $params[] = $branchId;
}
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id ASC LIMIT ? OFFSET ?';

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
