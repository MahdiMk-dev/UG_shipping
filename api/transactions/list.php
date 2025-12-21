<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$branchId = api_int($filters['branch_id'] ?? null);
$customerId = api_int($filters['customer_id'] ?? null);
$type = api_string($filters['type'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['t.deleted_at IS NULL'];
$params = [];

if ($branchId) {
    $where[] = 't.branch_id = ?';
    $params[] = $branchId;
}

if ($customerId) {
    $where[] = 't.customer_id = ?';
    $params[] = $customerId;
}

if ($type) {
    $allowed = ['payment', 'deposit', 'refund', 'adjustment', 'admin_settlement'];
    if (!in_array($type, $allowed, true)) {
        api_error('Invalid transaction type', 422);
    }
    $where[] = 't.type = ?';
    $params[] = $type;
}

$sql = 'SELECT t.id, t.branch_id, b.name AS branch_name, t.customer_id, c.name AS customer_name, '
    . 't.type, t.payment_method_id, pm.name AS payment_method, t.amount, t.payment_date, '
    . 't.whish_phone, t.note, t.created_at, t.updated_at, cu.name AS created_by_name, uu.name AS updated_by_name '
    . 'FROM transactions t '
    . 'LEFT JOIN branches b ON b.id = t.branch_id '
    . 'LEFT JOIN customers c ON c.id = t.customer_id '
    . 'LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id '
    . 'LEFT JOIN users cu ON cu.id = t.created_by_user_id '
    . 'LEFT JOIN users uu ON uu.id = t.updated_by_user_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY t.id DESC LIMIT ? OFFSET ?';

$params[] = $limit;
$params[] = $offset;

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $typeParam = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $typeParam);
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

api_json(['ok' => true, 'data' => $rows]);
