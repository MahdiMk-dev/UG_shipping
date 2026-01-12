<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Owner', 'Sub Branch'], true)) {
    api_error('Forbidden', 403);
}

$accountId = api_int($_GET['id'] ?? ($_GET['account_id'] ?? null));
if (!$accountId) {
    api_error('account_id is required', 422);
}

$limit = api_int($_GET['limit'] ?? 50, 50);
$offset = api_int($_GET['offset'] ?? 0, 0);
$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$status = api_string($_GET['status'] ?? null);
if ($status !== null) {
    $allowed = ['active', 'canceled'];
    if (!in_array($status, $allowed, true)) {
        api_error('Invalid status', 422);
    }
}

$db = db();
$accountWhere = 'a.id = ? AND a.deleted_at IS NULL AND a.owner_type IN (?, ?)';
$accountParams = [$accountId, 'admin', 'branch'];

if ($role === 'Sub Branch') {
    $branchId = api_int($user['branch_id'] ?? null);
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $accountWhere .= ' AND a.owner_type = ? AND a.owner_id = ?';
    $accountParams[] = 'branch';
    $accountParams[] = $branchId;
}

$accountStmt = $db->prepare(
    'SELECT a.id FROM accounts a '
    . 'LEFT JOIN branches b ON b.id = a.owner_id AND a.owner_type = \'branch\' AND b.deleted_at IS NULL '
    . 'WHERE ' . $accountWhere . ' LIMIT 1'
);
foreach ($accountParams as $index => $value) {
    $typeParam = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $accountStmt->bindValue($index + 1, $value, $typeParam);
}
$accountStmt->execute();
if (!$accountStmt->fetchColumn()) {
    api_error('Account not found', 404);
}

$where = ['e.account_id = ?'];
$params = [$accountId];

if ($status) {
    $where[] = 'e.status = ?';
    $params[] = $status;
}

$sql = 'SELECT e.id, e.entry_type, e.amount, e.entry_date, e.status, e.created_at, '
    . 'e.canceled_reason, e.canceled_at, '
    . 'at.id AS transfer_id, at.transfer_date, at.note AS transfer_note, '
    . 'at.reference_type, at.reference_id, at.status AS transfer_status, '
    . 'af.name AS from_account_name, aa.name AS to_account_name, '
    . 'CASE '
        . 'WHEN at.from_account_id = e.account_id THEN aa.name '
        . 'WHEN at.to_account_id = e.account_id THEN af.name '
        . 'ELSE NULL '
    . 'END AS counterparty_name, '
    . 'c.name AS customer_name, c.code AS customer_code '
    . 'FROM account_entries e '
    . 'LEFT JOIN account_transfers at ON at.id = e.transfer_id '
    . 'LEFT JOIN accounts af ON af.id = at.from_account_id '
    . 'LEFT JOIN accounts aa ON aa.id = at.to_account_id '
    . 'LEFT JOIN transactions t ON t.id = at.reference_id AND at.reference_type = \'transaction\' '
    . 'LEFT JOIN customers c ON c.id = t.customer_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY COALESCE(e.entry_date, DATE(e.created_at)) DESC, e.id DESC '
    . 'LIMIT ? OFFSET ?';

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
foreach ($params as $index => $value) {
    $typeParam = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $typeParam);
}
$stmt->execute();
$rows = $stmt->fetchAll();

api_json(['ok' => true, 'data' => $rows]);
