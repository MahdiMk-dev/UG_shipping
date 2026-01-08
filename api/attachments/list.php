<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/customer_auth.php';

api_require_method('GET');

$user = auth_user();
$customer = customer_auth_user();

if (!$user && !$customer) {
    api_error('Unauthorized', 401);
}
if ($customer && empty($customer['account_id'])) {
    api_error('Customer session is invalid', 401);
}

$entityType = api_string($_GET['entity_type'] ?? null);
$entityId = api_int($_GET['entity_id'] ?? null);
$search = api_string($_GET['q'] ?? null);
$limit = api_int($_GET['limit'] ?? 50, 50);
$offset = api_int($_GET['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$allowedTypes = ['shipment', 'order', 'shopping_order', 'invoice', 'collection'];

$where = ['a.deleted_at IS NULL'];
$params = [];

if ($entityType) {
    if (!in_array($entityType, $allowedTypes, true)) {
        api_error('Invalid entity_type', 422);
    }
    $where[] = 'a.entity_type = ?';
    $params[] = $entityType;
}

if ($entityId) {
    $where[] = 'a.entity_id = ?';
    $params[] = $entityId;
}

if ($search) {
    $where[] = '('
        . "(a.entity_type = 'shipment' AND s_att.shipment_number LIKE ?) "
        . "OR (a.entity_type = 'order' AND (o.tracking_number LIKE ? OR s_order.shipment_number LIKE ?)) "
        . "OR (a.entity_type = 'collection' AND (c_att.name LIKE ? OR s_col.shipment_number LIKE ?))"
        . ')';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($customer) {
    if (!$entityType || !$entityId) {
        api_error('entity_type and entity_id are required for portal access', 422);
    }

    if ($entityType === 'shipment') {
        api_error('Forbidden', 403);
    }

    if ($entityType === 'order') {
        $check = db()->prepare(
            'SELECT o.id FROM orders o '
            . 'JOIN customers c ON c.id = o.customer_id '
            . 'WHERE o.id = ? AND c.account_id = ? AND o.deleted_at IS NULL AND c.deleted_at IS NULL'
        );
        $check->execute([$entityId, $customer['account_id'] ?? 0]);
        if (!$check->fetch()) {
            api_error('Forbidden', 403);
        }
    }

    if ($entityType === 'invoice') {
        $check = db()->prepare(
            'SELECT i.id FROM invoices i '
            . 'JOIN customers c ON c.id = i.customer_id '
            . 'WHERE i.id = ? AND c.account_id = ? AND i.deleted_at IS NULL AND c.deleted_at IS NULL'
        );
        $check->execute([$entityId, $customer['account_id'] ?? 0]);
        if (!$check->fetch()) {
            api_error('Forbidden', 403);
        }
    }

    if ($entityType === 'shopping_order') {
        $check = db()->prepare(
            'SELECT s.id FROM shopping_orders s '
            . 'JOIN customers c ON c.id = s.customer_id '
            . 'WHERE s.id = ? AND c.account_id = ? AND s.deleted_at IS NULL AND c.deleted_at IS NULL'
        );
        $check->execute([$entityId, $customer['account_id'] ?? 0]);
        if (!$check->fetch()) {
            api_error('Forbidden', 403);
        }
    }

    if ($entityType === 'collection') {
        api_error('Forbidden', 403);
    }
}

$sql = 'SELECT a.id, a.entity_type, a.entity_id, a.title, a.description, a.original_name, '
    . 'a.mime_type, a.size_bytes, a.created_at '
    . 'FROM attachments a '
    . "LEFT JOIN shipments s_att ON s_att.id = a.entity_id AND a.entity_type = 'shipment' "
    . "AND s_att.deleted_at IS NULL "
    . "LEFT JOIN orders o ON o.id = a.entity_id AND a.entity_type = 'order' AND o.deleted_at IS NULL "
    . "LEFT JOIN shipments s_order ON s_order.id = o.shipment_id AND s_order.deleted_at IS NULL "
    . "LEFT JOIN collections c_att ON c_att.id = a.entity_id AND a.entity_type = 'collection' "
    . "LEFT JOIN shipments s_col ON s_col.id = c_att.shipment_id AND s_col.deleted_at IS NULL "
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY a.id DESC LIMIT ? OFFSET ?';

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
    $row['download_url'] = BASE_URL . '/api/attachments/download.php?id=' . $row['id'];
}

api_json(['ok' => true, 'data' => $rows]);
