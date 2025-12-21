<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$branchId = api_int($filters['branch_id'] ?? null);
$shipmentId = api_int($filters['shipment_id'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['s.match_status = ?'];
$params = ['unmatched'];

if ($branchId) {
    $where[] = 's.branch_id = ?';
    $params[] = $branchId;
}
if ($shipmentId) {
    $where[] = 's.shipment_id = ?';
    $params[] = $shipmentId;
}

$sql = 'SELECT s.id, s.branch_id, s.shipment_id, s.tracking_number, s.scanned_at, s.note '
    . 'FROM branch_receiving_scans s '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY s.scanned_at DESC LIMIT ? OFFSET ?';

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
