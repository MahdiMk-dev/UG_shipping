<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$customerId = api_int($filters['customer_id'] ?? null);
$includeAll = api_bool($filters['include_all'] ?? null, false);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['o.deleted_at IS NULL'];
$params = [];

$role = $user['role'] ?? '';
$readOnly = is_read_only_role($user) && $role !== 'Warehouse';
if ($readOnly) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $where[] = 'o.sub_branch_id = ?';
    $params[] = $branchId;
}

if ($customerId) {
    $where[] = 'o.customer_id = ?';
    $params[] = $customerId;
}

if (!$includeAll) {
    $where[] = "o.fulfillment_status = 'received_subbranch'";
}

$where[] = 'NOT EXISTS ('
    . 'SELECT 1 FROM invoice_items ii '
    . 'JOIN invoices i ON i.id = ii.invoice_id '
    . 'WHERE ii.order_id = o.id AND i.deleted_at IS NULL AND i.status <> \'void\''
    . ')';

$sql = 'SELECT o.id, o.shipment_id, s.shipment_number, o.customer_id, c.name AS customer_name, '
    . 'o.sub_branch_id, b.name AS sub_branch_name, o.tracking_number, o.delivery_type, '
    . 'o.unit_type, o.qty, o.total_price, o.fulfillment_status, o.created_at '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'LEFT JOIN branches b ON b.id = o.sub_branch_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY o.id DESC LIMIT ? OFFSET ?';

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
