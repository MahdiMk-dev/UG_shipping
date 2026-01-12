<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Owner', 'Sub Branch', 'Main Branch'], true)) {
    api_error('Forbidden', 403);
}

$accountId = api_int($_GET['id'] ?? ($_GET['account_id'] ?? null));
if (!$accountId) {
    api_error('account_id is required', 422);
}

$where = 'a.id = ? AND a.deleted_at IS NULL AND a.owner_type IN (?, ?)';
$params = [$accountId];
$params[] = 'admin';
$params[] = 'branch';

if (in_array($role, ['Sub Branch', 'Main Branch'], true)) {
    $branchId = api_int($user['branch_id'] ?? null);
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $where .= ' AND a.owner_type = ? AND a.owner_id = ?';
    $params[] = 'branch';
    $params[] = $branchId;
}

$sql = 'SELECT a.id, a.owner_type, a.owner_id, a.name, a.account_type, a.currency, a.payment_method_id, '
    . 'pm.name AS payment_method_name, a.balance, a.is_active, a.created_at, a.updated_at, '
    . 'CASE '
        . 'WHEN a.owner_type = \'admin\' THEN \'Admin\' '
        . 'WHEN a.owner_type = \'branch\' THEN b.name '
        . 'ELSE NULL '
    . 'END AS owner_name '
    . 'FROM accounts a '
    . 'LEFT JOIN payment_methods pm ON pm.id = a.payment_method_id '
    . 'LEFT JOIN branches b ON b.id = a.owner_id AND a.owner_type = \'branch\' AND b.deleted_at IS NULL '
    . 'WHERE ' . $where . ' LIMIT 1';

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $typeParam = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $typeParam);
}
$stmt->execute();
$account = $stmt->fetch();

if (!$account) {
    api_error('Account not found', 404);
}

api_json(['ok' => true, 'account' => $account]);
