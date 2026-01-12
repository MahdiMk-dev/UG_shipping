<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$ownerType = api_string($filters['owner_type'] ?? null);
$ownerId = api_int($filters['owner_id'] ?? null);
$isActive = isset($filters['is_active']) ? api_int($filters['is_active']) : null;
$paymentMethodId = api_int($filters['payment_method_id'] ?? null);
$search = api_string($filters['q'] ?? null);

$role = $user['role'] ?? '';
if (in_array($role, ['Warehouse', 'Staff'], true)) {
    api_error('Forbidden', 403);
}

$allowedOwnerTypes = ['admin', 'branch'];
if ($ownerType && !in_array($ownerType, $allowedOwnerTypes, true)) {
    api_error('Invalid owner_type', 422);
}

if (in_array($role, ['Sub Branch', 'Main Branch'], true)) {
    $branchId = api_int($user['branch_id'] ?? null);
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $ownerType = 'branch';
    $ownerId = $branchId;
}

$where = ['a.deleted_at IS NULL'];
$params = [];

if ($ownerType) {
    $where[] = 'a.owner_type = ?';
    $params[] = $ownerType;
}
if ($ownerId) {
    $where[] = 'a.owner_id = ?';
    $params[] = $ownerId;
}
if ($paymentMethodId) {
    $where[] = 'a.payment_method_id = ?';
    $params[] = $paymentMethodId;
}
if ($isActive !== null) {
    $where[] = 'a.is_active = ?';
    $params[] = $isActive ? 1 : 0;
}
if ($search) {
    $where[] = '('
        . 'a.name LIKE ? OR a.account_type LIKE ? OR pm.name LIKE ? OR '
        . 'b.name LIKE ?'
        . ')';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (in_array($role, ['Sub Branch', 'Main Branch'], true)) {
    $where[] = 'a.owner_type = ? AND a.owner_id = ?';
    $params[] = 'branch';
    $params[] = $ownerId;
} elseif (!$ownerType) {
    $where[] = 'a.owner_type IN (?, ?)';
    $params[] = 'admin';
    $params[] = 'branch';
}

$sql = 'SELECT a.id, a.owner_type, a.owner_id, a.name, a.account_type, a.currency, a.payment_method_id, '
    . 'pm.name AS payment_method_name, a.balance, a.is_active, '
    . 'CASE '
        . 'WHEN a.owner_type = \'admin\' THEN \'Admin\' '
        . 'WHEN a.owner_type = \'branch\' THEN b.name '
        . 'ELSE NULL '
    . 'END AS owner_name '
    . 'FROM accounts a '
    . 'LEFT JOIN payment_methods pm ON pm.id = a.payment_method_id '
    . 'LEFT JOIN branches b ON b.id = a.owner_id AND a.owner_type = \'branch\' AND b.deleted_at IS NULL '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY a.owner_type ASC, a.name ASC';

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $typeParam = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $typeParam);
}
$stmt->execute();
$rows = $stmt->fetchAll();

api_json(['ok' => true, 'data' => $rows]);
