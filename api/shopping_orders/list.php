<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$customerId = api_int($filters['customer_id'] ?? null);
$subBranchId = api_int($filters['sub_branch_id'] ?? null);
$status = api_string($filters['status'] ?? null);
$deliveryType = api_string($filters['delivery_type'] ?? null);
$search = api_string($filters['q'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['s.deleted_at IS NULL'];
$params = [];

if ($customerId) {
    $where[] = 's.customer_id = ?';
    $params[] = $customerId;
}

if ($subBranchId) {
    $where[] = 's.sub_branch_id = ?';
    $params[] = $subBranchId;
}

if ($status) {
    $allowed = ['pending', 'distributed', 'received_subbranch', 'closed', 'canceled'];
    if (!in_array($status, $allowed, true)) {
        api_error('Invalid status', 422);
    }
    $where[] = 's.status = ?';
    $params[] = $status;
}

if ($deliveryType) {
    $allowed = ['pickup', 'delivery'];
    if (!in_array($deliveryType, $allowed, true)) {
        api_error('Invalid delivery_type', 422);
    }
    $where[] = 's.delivery_type = ?';
    $params[] = $deliveryType;
}

if ($search) {
    $where[] = 's.name LIKE ?';
    $params[] = '%' . $search . '%';
}

$sql = 'SELECT s.id, s.customer_id, c.name AS customer_name, s.sub_branch_id, b.name AS sub_branch_name, '
    . 's.name, s.image_url, s.cost, s.price, s.fees_type, s.fees_amount, s.fees_percentage, '
    . 's.total, s.delivery_type, s.status '
    . 'FROM shopping_orders s '
    . 'LEFT JOIN customers c ON c.id = s.customer_id '
    . 'LEFT JOIN branches b ON b.id = s.sub_branch_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY s.id DESC LIMIT ? OFFSET ?';

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
