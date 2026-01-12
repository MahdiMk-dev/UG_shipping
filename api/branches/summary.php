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

$branchId = api_int($_GET['branch_id'] ?? null);
if ($role === 'Sub Branch') {
    $branchId = (int) ($user['branch_id'] ?? 0);
}

if (!$branchId) {
    api_error('branch_id is required', 422);
}

$branchStmt = db()->prepare(
    'SELECT b.id, b.name, b.type, b.country_id, c.name AS country_name, '
    . 'b.parent_branch_id, p.name AS parent_branch_name, b.phone, b.address, '
    . 'COALESCE(bb.balance, 0) AS balance '
    . 'FROM branches b '
    . 'LEFT JOIN countries c ON c.id = b.country_id '
    . 'LEFT JOIN branches p ON p.id = b.parent_branch_id '
    . 'LEFT JOIN ('
        . 'SELECT branch_id, SUM(amount) AS balance '
        . 'FROM branch_balance_entries '
        . 'GROUP BY branch_id'
    . ') bb ON bb.branch_id = b.id '
    . 'WHERE b.id = ? AND b.deleted_at IS NULL'
);
$branchStmt->execute([$branchId]);
$branch = $branchStmt->fetch();

if (!$branch) {
    api_error('Branch not found', 404);
}

$profileStmt = db()->prepare(
    'SELECT COUNT(*) FROM customers '
    . 'WHERE deleted_at IS NULL AND is_system = 0 AND sub_branch_id = ?'
);
$profileStmt->execute([$branchId]);
$profileCount = (int) $profileStmt->fetchColumn();

$balanceStatsStmt = db()->prepare(
    'SELECT COUNT(*) AS account_count, '
    . 'SUM(CASE WHEN balance <> 0 THEN 1 ELSE 0 END) AS balance_count, '
    . 'SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) AS due_total, '
    . 'SUM(CASE WHEN balance < 0 THEN -balance ELSE 0 END) AS credit_total '
    . 'FROM ('
        . 'SELECT '
        . 'CASE WHEN c.account_id IS NULL THEN -c.id ELSE c.account_id END AS account_key, '
        . 'MAX(c.balance) AS balance '
        . 'FROM customers c '
        . 'WHERE c.deleted_at IS NULL AND c.is_system = 0 AND c.sub_branch_id = ? '
        . 'GROUP BY account_key'
    . ') agg'
);
$balanceStatsStmt->execute([$branchId]);
$balanceStats = $balanceStatsStmt->fetch() ?: [];

api_json([
    'ok' => true,
    'data' => [
        'branch' => $branch,
        'stats' => [
            'profile_count' => $profileCount,
            'account_count' => (int) ($balanceStats['account_count'] ?? 0),
            'balance_count' => (int) ($balanceStats['balance_count'] ?? 0),
            'due_total' => (float) ($balanceStats['due_total'] ?? 0),
            'credit_total' => (float) ($balanceStats['credit_total'] ?? 0),
        ],
    ],
]);
