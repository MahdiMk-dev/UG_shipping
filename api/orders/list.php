<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$shipmentId = api_int($filters['shipment_id'] ?? null);
$customerId = api_int($filters['customer_id'] ?? null);
$subBranchId = api_int($filters['sub_branch_id'] ?? null);
$fulfillmentStatus = api_string($filters['fulfillment_status'] ?? null);
$search = api_string($filters['q'] ?? null);
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
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    $where[] = 's.origin_country_id = ?';
    $params[] = $warehouseCountryId;
}

if ($shipmentId) {
    $where[] = 'o.shipment_id = ?';
    $params[] = $shipmentId;
}

if ($customerId) {
    $where[] = 'o.customer_id = ?';
    $params[] = $customerId;
}

if ($subBranchId && !$readOnly) {
    $where[] = 'o.sub_branch_id = ?';
    $params[] = $subBranchId;
}

if ($fulfillmentStatus) {
    $allowed = ['in_shipment', 'main_branch', 'pending_receipt', 'received_subbranch', 'closed', 'returned', 'canceled'];
    if (!in_array($fulfillmentStatus, $allowed, true)) {
        api_error('Invalid fulfillment_status', 422);
    }
    $where[] = 'o.fulfillment_status = ?';
    $params[] = $fulfillmentStatus;
}

if ($search) {
    $where[] = '(o.tracking_number LIKE ? OR c.name LIKE ? OR c.code LIKE ? OR c.phone LIKE ? OR ca.phone LIKE ? '
        . 'OR s.shipment_number LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = 'SELECT o.id, o.shipment_id, s.shipment_number, o.customer_id, c.name AS customer_name, c.code AS customer_code, '
    . 'o.sub_branch_id, b.name AS sub_branch_name, o.tracking_number, o.delivery_type, '
    . 'o.unit_type, o.qty, o.total_price, o.fulfillment_status, o.notification_status, '
    . 'o.created_at, o.updated_at, cu.name AS created_by_name, uu.name AS updated_by_name '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'LEFT JOIN customer_accounts ca ON ca.id = c.account_id '
    . 'LEFT JOIN branches b ON b.id = o.sub_branch_id '
    . 'LEFT JOIN users cu ON cu.id = o.created_by_user_id '
    . 'LEFT JOIN users uu ON uu.id = o.updated_by_user_id '
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

$role = $user['role'] ?? '';
$showMeta = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
if (!$showMeta) {
    foreach ($rows as &$row) {
        unset($row['created_by_name'], $row['updated_by_name']);
    }
    unset($row);
}
if ($role === 'Warehouse') {
    foreach ($rows as &$row) {
        unset($row['total_price']);
    }
    unset($row);
}

api_json(['ok' => true, 'data' => $rows]);
