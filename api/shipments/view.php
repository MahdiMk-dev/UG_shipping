<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = auth_require_user();

$shipmentId = api_int($_GET['shipment_id'] ?? ($_GET['id'] ?? null));
$shipmentNumber = api_string($_GET['shipment_number'] ?? null);

if (!$shipmentId && !$shipmentNumber) {
    api_error('shipment_id or shipment_number is required', 422);
}

$db = db();
if ($shipmentId) {
    $stmt = $db->prepare(
        'SELECT s.*, c.name AS origin_country, sp.name AS shipper_profile_name, '
        . 'cp.name AS consignee_profile_name, cu.name AS created_by_name, uu.name AS updated_by_name '
        . 'FROM shipments s '
        . 'LEFT JOIN countries c ON c.id = s.origin_country_id '
        . 'LEFT JOIN supplier_profiles sp ON sp.id = s.shipper_profile_id '
        . 'LEFT JOIN supplier_profiles cp ON cp.id = s.consignee_profile_id '
        . 'LEFT JOIN users cu ON cu.id = s.created_by_user_id '
        . 'LEFT JOIN users uu ON uu.id = s.updated_by_user_id '
        . 'WHERE s.id = ? AND s.deleted_at IS NULL'
    );
    $stmt->execute([$shipmentId]);
} else {
    $stmt = $db->prepare(
        'SELECT s.*, c.name AS origin_country, sp.name AS shipper_profile_name, '
        . 'cp.name AS consignee_profile_name, cu.name AS created_by_name, uu.name AS updated_by_name '
        . 'FROM shipments s '
        . 'LEFT JOIN countries c ON c.id = s.origin_country_id '
        . 'LEFT JOIN supplier_profiles sp ON sp.id = s.shipper_profile_id '
        . 'LEFT JOIN supplier_profiles cp ON cp.id = s.consignee_profile_id '
        . 'LEFT JOIN users cu ON cu.id = s.created_by_user_id '
        . 'LEFT JOIN users uu ON uu.id = s.updated_by_user_id '
        . 'WHERE s.shipment_number = ? AND s.deleted_at IS NULL'
    );
    $stmt->execute([$shipmentNumber]);
}

$shipment = $stmt->fetch();
if (!$shipment) {
    api_error('Shipment not found', 404);
}
$shipment['total_weight'] = $shipment['weight'];
$shipment['total_volume'] = $shipment['size'];

$role = $user['role'] ?? '';
$warehouseCountryId = null;
$hideRates = $role === 'Warehouse';
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    if ((int) ($shipment['origin_country_id'] ?? 0) !== (int) $warehouseCountryId) {
        api_error('Forbidden', 403);
    }
}
$readOnly = is_read_only_role($user) && $role !== 'Warehouse';
$branchId = $user['branch_id'] ?? null;
if ($readOnly) {
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $accessStmt = $db->prepare(
        'SELECT 1 FROM orders o '
        . 'INNER JOIN customers cu2 ON cu2.id = o.customer_id '
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL AND cu2.sub_branch_id = ? LIMIT 1'
    );
    $accessStmt->execute([$shipment['id'], $branchId]);
    if (!$accessStmt->fetch()) {
        api_error('Forbidden', 403);
    }
}

$showMeta = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
if (!$showMeta) {
    unset($shipment['created_by_name'], $shipment['updated_by_name']);
}
if ($hideRates) {
    unset($shipment['default_rate_kg'], $shipment['default_rate_cbm'], $shipment['cost_per_unit'], $shipment['default_rate_unit']);
}

if ($readOnly) {
    $collectionsStmt = $db->prepare(
        'SELECT DISTINCT c.id, c.name '
        . 'FROM collections c '
        . 'INNER JOIN orders o ON o.collection_id = c.id AND o.deleted_at IS NULL '
        . 'INNER JOIN customers cu2 ON cu2.id = o.customer_id '
        . 'WHERE c.shipment_id = ? AND cu2.sub_branch_id = ? '
        . 'ORDER BY c.id ASC'
    );
    $collectionsStmt->execute([$shipment['id'], $branchId]);
} else {
    $collectionsStmt = $db->prepare(
        'SELECT id, name FROM collections WHERE shipment_id = ? ORDER BY id ASC'
    );
    $collectionsStmt->execute([$shipment['id']]);
}
$collections = $collectionsStmt->fetchAll();

if ($readOnly) {
    $ordersStmt = $db->prepare(
        'SELECT o.id, o.tracking_number, o.customer_id, c.name AS customer_name, o.total_price, '
        . 'o.fulfillment_status, o.delivery_type, o.notification_status, o.qty, o.rate, o.unit_type '
        . 'FROM orders o '
        . 'LEFT JOIN customers c ON c.id = o.customer_id '
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL AND c.sub_branch_id = ? '
        . 'ORDER BY o.id DESC'
    );
    $ordersStmt->execute([$shipment['id'], $branchId]);
} else {
    $ordersStmt = $db->prepare(
        'SELECT o.id, o.tracking_number, o.customer_id, c.name AS customer_name, o.total_price, '
        . 'o.fulfillment_status, o.delivery_type, o.notification_status, o.qty, o.rate, o.unit_type '
        . 'FROM orders o '
        . 'LEFT JOIN customers c ON c.id = o.customer_id '
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL '
        . 'ORDER BY o.id DESC'
    );
    $ordersStmt->execute([$shipment['id']]);
}
$orders = $ordersStmt->fetchAll();

if ($readOnly) {
    $customerOrdersStmt = $db->prepare(
        'SELECT o.customer_id, c.name AS customer_name, c.code AS customer_code, '
        . 'COALESCE(c.phone, ca.phone) AS customer_phone, '
        . 'COUNT(*) AS order_count, SUM(o.total_price) AS total_price, SUM(o.qty) AS total_qty, '
        . "GROUP_CONCAT(DISTINCT o.unit_type ORDER BY o.unit_type SEPARATOR ', ') AS unit_types, "
        . "GROUP_CONCAT(DISTINCT o.tracking_number ORDER BY o.id SEPARATOR ', ') AS tracking_numbers "
        . 'FROM orders o '
        . 'LEFT JOIN customers c ON c.id = o.customer_id '
        . 'LEFT JOIN customer_accounts ca ON ca.id = c.account_id '
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL AND c.sub_branch_id = ? '
        . 'GROUP BY o.customer_id '
        . 'ORDER BY total_price DESC, customer_name ASC'
    );
    $customerOrdersStmt->execute([$shipment['id'], $branchId]);
} else {
    $customerOrdersStmt = $db->prepare(
        'SELECT o.customer_id, c.name AS customer_name, c.code AS customer_code, '
        . 'COALESCE(c.phone, ca.phone) AS customer_phone, '
        . 'COUNT(*) AS order_count, SUM(o.total_price) AS total_price, SUM(o.qty) AS total_qty, '
        . "GROUP_CONCAT(DISTINCT o.unit_type ORDER BY o.unit_type SEPARATOR ', ') AS unit_types, "
        . "GROUP_CONCAT(DISTINCT o.tracking_number ORDER BY o.id SEPARATOR ', ') AS tracking_numbers "
        . 'FROM orders o '
        . 'LEFT JOIN customers c ON c.id = o.customer_id '
        . 'LEFT JOIN customer_accounts ca ON ca.id = c.account_id '
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL '
        . 'GROUP BY o.customer_id '
        . 'ORDER BY total_price DESC, customer_name ASC'
    );
    $customerOrdersStmt->execute([$shipment['id']]);
}
$customerOrders = $customerOrdersStmt->fetchAll();

if ($hideRates) {
    foreach ($orders as &$order) {
        unset($order['total_price'], $order['rate']);
    }
    unset($order);
    foreach ($customerOrders as &$row) {
        unset($row['total_price']);
    }
    unset($row);
}

$orderIds = array_map(static fn ($row) => (int) $row['id'], $orders);

$attachments = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $attSql =
        'SELECT id, entity_type, entity_id, title, description, original_name, mime_type, size_bytes, created_at '
        . 'FROM attachments '
        . 'WHERE deleted_at IS NULL AND ('
        . '(entity_type = ? AND entity_id = ?) '
        . "OR (entity_type = 'order' AND entity_id IN ($placeholders))"
        . ') ORDER BY id DESC';

    $params = array_merge(['shipment', $shipment['id']], $orderIds);
    $attStmt = $db->prepare($attSql);
    $attStmt->execute($params);
    $attachments = $attStmt->fetchAll();
} else {
    $attStmt = $db->prepare(
        'SELECT id, entity_type, entity_id, title, description, original_name, mime_type, size_bytes, created_at '
        . 'FROM attachments '
        . 'WHERE deleted_at IS NULL AND entity_type = ? AND entity_id = ? '
        . 'ORDER BY id DESC'
    );
    $attStmt->execute(['shipment', $shipment['id']]);
    $attachments = $attStmt->fetchAll();
}

foreach ($attachments as &$attachment) {
    $attachment['download_url'] = BASE_URL . '/api/attachments/download.php?id=' . $attachment['id'];
}

api_json([
    'ok' => true,
    'shipment' => $shipment,
    'collections' => $collections,
    'orders' => $orders,
    'customer_orders' => $customerOrders,
    'attachments' => $attachments,
]);


