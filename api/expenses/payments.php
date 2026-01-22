<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
require_role(['Admin', 'Owner']);

$filters = $_GET ?? [];
$type = api_string($filters['type'] ?? null);
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

$where = ['at.deleted_at IS NULL'];
$params = [];

if ($type) {
    $type = strtolower($type);
    if (in_array($type, ['general_expense', 'shipment_expense', 'staff_expense'], true)) {
        $where[] = 'at.entry_type = ?';
        $params[] = $type;
    } elseif ($type === 'invoice_points') {
        $where[] = 'at.reference_type = ?';
        $params[] = 'invoice_points';
    }
}

if ($dateFrom) {
    $where[] = 'COALESCE(at.transfer_date, DATE(at.created_at)) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'COALESCE(at.transfer_date, DATE(at.created_at)) <= ?';
    $params[] = $dateTo;
}

$sql = 'SELECT at.id, at.entry_type, at.amount, at.transfer_date, at.note, at.status, '
    . 'at.reference_type, at.reference_id, at.created_at, '
    . 'af.name AS from_account_name, af.id AS from_account_id, '
    . 'ge.title AS expense_title, ge.shipment_id, s.shipment_number, '
    . 'se.type AS staff_expense_type, sm.name AS staff_name, '
    . 'u.name AS created_by_name '
    . 'FROM account_transfers at '
    . 'LEFT JOIN accounts af ON af.id = at.from_account_id '
    . 'LEFT JOIN general_expenses ge ON ge.id = at.reference_id '
    . "AND at.reference_type IN ('general_expense','invoice_points') "
    . 'LEFT JOIN shipments s ON s.id = ge.shipment_id '
    . 'LEFT JOIN staff_expenses se ON se.id = at.reference_id AND at.reference_type = \'staff_expense\' '
    . 'LEFT JOIN staff_members sm ON sm.id = se.staff_id '
    . 'LEFT JOIN users u ON u.id = at.created_by_user_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY at.id DESC '
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
