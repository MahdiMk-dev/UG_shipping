<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
require_role(['Admin', 'Owner']);
$filters = $_GET ?? [];

$search = api_string($filters['q'] ?? null);
$branchId = api_int($filters['branch_id'] ?? null);
$shipmentId = api_int($filters['shipment_id'] ?? null);
$dateFrom = api_string($filters['date_from'] ?? null);
$dateTo = api_string($filters['date_to'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

if ($dateFrom !== null && strtotime($dateFrom) === false) {
    api_error('Invalid date_from', 422);
}
if ($dateTo !== null && strtotime($dateTo) === false) {
    api_error('Invalid date_to', 422);
}

$where = ['e.deleted_at IS NULL'];
$params = [];

if ($branchId) {
    $where[] = 'e.branch_id = ?';
    $params[] = $branchId;
}

if ($shipmentId) {
    $where[] = 'e.shipment_id = ?';
    $params[] = $shipmentId;
}

if ($search) {
    $where[] = '(e.title LIKE ? OR e.note LIKE ? OR s.shipment_number LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($dateFrom) {
    $where[] = 'e.expense_date >= ?';
    $params[] = $dateFrom;
}

if ($dateTo) {
    $where[] = 'e.expense_date <= ?';
    $params[] = $dateTo;
}

$sql = 'SELECT e.id, e.branch_id, b.name AS branch_name, e.shipment_id, '
    . 's.shipment_number, e.title, e.amount, e.expense_date, '
    . 'e.note, e.created_at, e.updated_at '
    . 'FROM general_expenses e '
    . 'LEFT JOIN branches b ON b.id = e.branch_id '
    . 'LEFT JOIN shipments s ON s.id = e.shipment_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY COALESCE(e.expense_date, DATE(e.created_at)) DESC, e.id DESC '
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
