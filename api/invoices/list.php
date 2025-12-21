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

$filters = $_GET ?? [];
$status = api_string($filters['status'] ?? null);
$customerId = api_int($filters['customer_id'] ?? null);
$branchId = api_int($filters['branch_id'] ?? null);
$search = api_string($filters['q'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['i.deleted_at IS NULL'];
$params = [];

if ($status) {
    $allowed = ['open', 'partially_paid', 'paid', 'void'];
    if (!in_array($status, $allowed, true)) {
        api_error('Invalid status', 422);
    }
    $where[] = 'i.status = ?';
    $params[] = $status;
}

if ($customer) {
    $where[] = 'i.customer_id = ?';
    $params[] = $customer['customer_id'];
} elseif ($customerId) {
    $where[] = 'i.customer_id = ?';
    $params[] = $customerId;
}

if ($branchId) {
    $where[] = 'i.branch_id = ?';
    $params[] = $branchId;
}

if ($search) {
    $where[] = 'i.invoice_no LIKE ?';
    $params[] = '%' . $search . '%';
}

$sql = 'SELECT i.id, i.invoice_no, i.customer_id, c.name AS customer_name, i.branch_id, '
    . 'b.name AS branch_name, i.status, i.total, i.paid_total, i.due_total, i.issued_at, '
    . 'i.updated_at, iu.name AS issued_by_name, uu.name AS updated_by_name '
    . 'FROM invoices i '
    . 'LEFT JOIN customers c ON c.id = i.customer_id '
    . 'LEFT JOIN branches b ON b.id = i.branch_id '
    . 'LEFT JOIN users iu ON iu.id = i.issued_by_user_id '
    . 'LEFT JOIN users uu ON uu.id = i.updated_by_user_id '
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

$showMeta = false;
if ($user) {
    $role = $user['role'] ?? '';
    $showMeta = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
}
if (!$showMeta) {
    foreach ($rows as &$row) {
        unset($row['issued_by_name'], $row['updated_by_name']);
        if (!$user) {
            unset($row['updated_at']);
        }
    }
    unset($row);
}

api_json(['ok' => true, 'data' => $rows]);
