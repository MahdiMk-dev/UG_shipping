<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);

$partnerId = api_int($_GET['partner_id'] ?? null);
$status = api_string($_GET['status'] ?? null);
$limit = api_int($_GET['limit'] ?? 50, 50);
$offset = api_int($_GET['offset'] ?? 0, 0);

if (!$partnerId) {
    api_error('partner_id is required', 422);
}

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['i.partner_id = ?', 'i.deleted_at IS NULL'];
$params = [$partnerId];

if ($status) {
    $allowed = ['open', 'partially_paid', 'paid', 'void'];
    if (!in_array($status, $allowed, true)) {
        api_error('Invalid status', 422);
    }
    $where[] = 'status = ?';
    $params[] = $status;
}

$sql = 'SELECT i.id, i.partner_id, i.shipment_id, i.invoice_no, i.status, i.currency, i.total, i.paid_total, i.due_total, '
    . 'i.issued_at, i.note, s.shipment_number '
    . 'FROM partner_invoices i '
    . 'LEFT JOIN shipments s ON s.id = i.shipment_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY i.id DESC LIMIT ? OFFSET ?';

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
