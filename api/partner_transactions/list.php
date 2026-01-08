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

$where = ['t.partner_id = ?', 't.deleted_at IS NULL'];
$params = [$partnerId];

if ($status) {
    $allowed = ['active', 'canceled'];
    if (!in_array($status, $allowed, true)) {
        api_error('Invalid status', 422);
    }
    $where[] = 't.status = ?';
    $params[] = $status;
}

$sql = 'SELECT t.id, t.partner_id, t.invoice_id, t.type, t.status, '
    . 't.payment_method_id, pm.name AS payment_method_name, t.amount, t.payment_date, '
    . 't.note, t.canceled_reason, t.created_at, i.invoice_no '
    . 'FROM partner_transactions t '
    . 'LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id '
    . 'LEFT JOIN partner_invoices i ON i.id = t.invoice_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY t.id DESC LIMIT ? OFFSET ?';

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

api_json(['ok' => true, 'data' => $rows]);
