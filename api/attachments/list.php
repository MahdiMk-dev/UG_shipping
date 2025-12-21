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

$entityType = api_string($_GET['entity_type'] ?? null);
$entityId = api_int($_GET['entity_id'] ?? null);
$limit = api_int($_GET['limit'] ?? 50, 50);
$offset = api_int($_GET['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$allowedTypes = ['shipment', 'order', 'shopping_order', 'invoice'];

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

if ($customer) {
    if (!$entityType || !$entityId) {
        api_error('entity_type and entity_id are required for portal access', 422);
    }

    if ($entityType === 'shipment') {
        api_error('Forbidden', 403);
    }

    if ($entityType === 'order') {
        $check = db()->prepare('SELECT id FROM orders WHERE id = ? AND customer_id = ? AND deleted_at IS NULL');
        $check->execute([$entityId, $customer['customer_id']]);
        if (!$check->fetch()) {
            api_error('Forbidden', 403);
        }
    }

    if ($entityType === 'invoice') {
        $check = db()->prepare('SELECT id FROM invoices WHERE id = ? AND customer_id = ? AND deleted_at IS NULL');
        $check->execute([$entityId, $customer['customer_id']]);
        if (!$check->fetch()) {
            api_error('Forbidden', 403);
        }
    }

    if ($entityType === 'shopping_order') {
        $check = db()->prepare('SELECT id FROM shopping_orders WHERE id = ? AND customer_id = ? AND deleted_at IS NULL');
        $check->execute([$entityId, $customer['customer_id']]);
        if (!$check->fetch()) {
            api_error('Forbidden', 403);
        }
    }
}

$sql = 'SELECT a.id, a.entity_type, a.entity_id, a.title, a.description, a.original_name, '
    . 'a.mime_type, a.size_bytes, a.created_at '
    . 'FROM attachments a '
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
