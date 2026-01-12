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
$status = api_string($filters['status'] ?? null);
$dateFrom = api_string($filters['date_from'] ?? null);
$dateTo = api_string($filters['date_to'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['t.deleted_at IS NULL'];
$params = [];

$role = $user['role'] ?? '';
if ($role === 'Sub Branch') {
    $branchId = api_int($user['branch_id'] ?? null);
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
} elseif (in_array($role, ['Warehouse', 'Staff'], true)) {
    api_error('Forbidden', 403);
}

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

if ($status) {
    $allowed = ['active', 'canceled'];
    if (!in_array($status, $allowed, true)) {
        api_error('Invalid transaction status', 422);
    }
    $where[] = 't.status = ?';
    $params[] = $status;
}

if ($dateFrom) {
    if (strtotime($dateFrom) === false) {
        api_error('Invalid date_from', 422);
    }
    $where[] = 'DATE(COALESCE(t.payment_date, t.created_at)) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    if (strtotime($dateTo) === false) {
        api_error('Invalid date_to', 422);
    }
    $where[] = 'DATE(COALESCE(t.payment_date, t.created_at)) <= ?';
    $params[] = $dateTo;
}

$sql = 'SELECT t.id, t.branch_id, b.name AS branch_name, t.customer_id, c.name AS customer_name, '
    . 't.type, t.status, t.payment_method_id, pm.name AS payment_method, t.amount, t.currency, t.payment_date, '
    . 't.whish_phone, t.reason, t.note, t.canceled_reason, t.created_at, t.updated_at, '
    . 'af.name AS from_account_name, aa.name AS to_account_name, '
    . 'CASE '
        . 'WHEN af.name IS NOT NULL AND aa.name IS NOT NULL THEN CONCAT(af.name, \' -> \', aa.name) '
        . 'WHEN af.name IS NOT NULL THEN af.name '
        . 'WHEN aa.name IS NOT NULL THEN aa.name '
        . 'ELSE NULL '
    . 'END AS account_label, '
    . 'cu.name AS created_by_name, uu.name AS updated_by_name '
    . 'FROM transactions t '
    . 'LEFT JOIN branches b ON b.id = t.branch_id '
    . 'LEFT JOIN customers c ON c.id = t.customer_id '
    . 'LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id '
    . 'LEFT JOIN account_transfers at ON at.id = t.account_transfer_id '
    . 'LEFT JOIN accounts af ON af.id = at.from_account_id '
    . 'LEFT JOIN accounts aa ON aa.id = at.to_account_id '
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

$branchBalance = null;
if ($branchId) {
    $balanceStmt = db()->prepare(
        'SELECT COALESCE(SUM(amount), 0) FROM branch_balance_entries WHERE branch_id = ?'
    );
    $balanceStmt->execute([$branchId]);
    $branchBalance = (float) $balanceStmt->fetchColumn();
}

$showMeta = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
if (!$showMeta) {
    foreach ($rows as &$row) {
        unset($row['created_by_name'], $row['updated_by_name']);
    }
    unset($row);
}

$response = ['ok' => true, 'data' => $rows];
if ($branchBalance !== null) {
    $response['branch_balance'] = $branchBalance;
}

api_json($response);
