<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$search = api_string($filters['q'] ?? null);
$subBranchId = api_int($filters['sub_branch_id'] ?? null);
$limit = api_int($filters['limit'] ?? 20, 20);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 20));
$offset = max(0, $offset ?? 0);

$where = [
    'o.deleted_at IS NULL',
    's.deleted_at IS NULL',
    'o.fulfillment_status = ?',
];
$params = ['pending_receipt'];

$role = $user['role'] ?? '';
$readOnly = is_read_only_role($user) && $role !== 'Warehouse';
if ($readOnly) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $where[] = 'o.sub_branch_id = ?';
    $params[] = (int) $branchId;
}

if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    $where[] = 's.origin_country_id = ?';
    $params[] = $warehouseCountryId;
}

if ($subBranchId && !$readOnly) {
    $where[] = 'o.sub_branch_id = ?';
    $params[] = $subBranchId;
}

if ($search) {
    $where[] = '(s.shipment_number LIKE ? OR o.tracking_number LIKE ? OR c.name LIKE ? OR c.code LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = 'SELECT s.id, s.shipment_number, s.status, s.origin_country_id, co.name AS origin_country, '
    . 'COUNT(o.id) AS pending_count '
    . 'FROM shipments s '
    . 'JOIN orders o ON o.shipment_id = s.id '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'LEFT JOIN countries co ON co.id = s.origin_country_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'GROUP BY s.id '
    . 'ORDER BY s.id DESC '
    . 'LIMIT ? OFFSET ?';

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
