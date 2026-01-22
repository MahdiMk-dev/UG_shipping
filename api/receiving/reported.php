<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$branchId = api_int($filters['branch_id'] ?? null);
$shipmentId = api_int($filters['shipment_id'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$role = $user['role'] ?? '';
$readOnly = is_read_only_role($user) && $role !== 'Warehouse';
if ($role === 'Warehouse') {
    api_error('Warehouse access is not supported for reported scans', 403);
}
if ($readOnly) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
}

$where = ['s.match_status = ?', 's.reported_weight IS NOT NULL', 'o.deleted_at IS NULL'];
$params = ['matched'];

if ($branchId) {
    $where[] = 's.branch_id = ?';
    $params[] = $branchId;
}
if ($shipmentId) {
    $where[] = 's.shipment_id = ?';
    $params[] = $shipmentId;
}

$sql = 'SELECT s.id, s.branch_id, b.name AS branch_name, s.shipment_id, sh.shipment_number, '
    . 's.tracking_number, s.reported_weight, s.reported_at, s.reported_by_user_id, u.name AS reported_by_name, '
    . 'o.id AS order_id, o.weight_type, o.actual_weight, o.w, o.d, o.h, o.unit_type, sh.shipping_type '
    . 'FROM branch_receiving_scans s '
    . 'JOIN orders o ON o.id = s.matched_order_id '
    . 'LEFT JOIN branches b ON b.id = s.branch_id '
    . 'LEFT JOIN shipments sh ON sh.id = s.shipment_id '
    . 'LEFT JOIN users u ON u.id = s.reported_by_user_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY s.reported_at DESC LIMIT ? OFFSET ?';

$params[] = $limit;
$params[] = $offset;

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $type);
}
$stmt->execute();
$rows = $stmt->fetchAll();

foreach ($rows as &$row) {
    $weightType = (string) ($row['weight_type'] ?? 'actual');
    $weightUnit = $weightType === 'volumetric' ? 'cbm' : 'kg';
    $systemWeight = compute_qty(
        (string) ($row['unit_type'] ?? $weightUnit),
        $weightType,
        $row['actual_weight'] !== null ? (float) $row['actual_weight'] : null,
        $row['w'] !== null ? (float) $row['w'] : null,
        $row['d'] !== null ? (float) $row['d'] : null,
        $row['h'] !== null ? (float) $row['h'] : null,
        $row['shipping_type'] ?? null
    );
    $row['system_weight'] = $systemWeight;
    $row['weight_unit'] = $weightUnit;
}
unset($row);

api_json(['ok' => true, 'data' => $rows]);
