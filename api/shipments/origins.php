<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = auth_require_user();

$where = ['s.deleted_at IS NULL', 's.origin_country_id IS NOT NULL'];
$params = [];

$role = $user['role'] ?? '';
$readOnly = is_read_only_role($user);
if ($readOnly && $role !== 'Warehouse') {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $where[] =
        'EXISTS (SELECT 1 FROM orders o '
        . 'INNER JOIN customers cu ON cu.id = o.customer_id '
        . 'WHERE o.shipment_id = s.id AND o.deleted_at IS NULL AND cu.sub_branch_id = ?)';
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

$sql = 'SELECT DISTINCT c.id, c.name '
    . 'FROM shipments s '
    . 'INNER JOIN countries c ON c.id = s.origin_country_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY c.name ASC';

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $type);
}
$stmt->execute();
$rows = $stmt->fetchAll();

api_json(['ok' => true, 'data' => $rows]);
