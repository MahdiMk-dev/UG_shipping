<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = auth_require_user();

$orderId = api_int($_GET['order_id'] ?? ($_GET['id'] ?? null));
if (!$orderId) {
    api_error('order_id is required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT o.*, s.origin_country_id, s.status AS shipment_status, c.name AS customer_name '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'WHERE o.id = ? AND o.deleted_at IS NULL'
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    api_error('Order not found', 404);
}

$role = $user['role'] ?? '';
$readOnly = is_read_only_role($user) && $role !== 'Warehouse';
if ($readOnly) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    if ((int) $order['sub_branch_id'] !== (int) $branchId) {
        api_error('Forbidden', 403);
    }
}

if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    if ((int) ($order['origin_country_id'] ?? 0) !== (int) $warehouseCountryId) {
        api_error('Forbidden', 403);
    }
}

$adjStmt = $db->prepare(
    'SELECT id, title, description, kind, calc_type, value, computed_amount '
    . 'FROM order_adjustments WHERE order_id = ? AND deleted_at IS NULL ORDER BY id ASC'
);
$adjStmt->execute([$orderId]);
$adjustments = $adjStmt->fetchAll() ?: [];

if ($role === 'Warehouse') {
    unset($order['rate'], $order['base_price'], $order['adjustments_total'], $order['total_price']);
    $adjustments = [];
}

api_json([
    'ok' => true,
    'order' => $order,
    'adjustments' => $adjustments,
]);
