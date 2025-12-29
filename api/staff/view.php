<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Sub Branch']);

$staffId = api_int($_GET['staff_id'] ?? ($_GET['id'] ?? null));
if (!$staffId) {
    api_error('staff_id is required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT s.*, b.name AS branch_name '
    . 'FROM staff_members s '
    . 'LEFT JOIN branches b ON b.id = s.branch_id '
    . 'WHERE s.id = ? AND s.deleted_at IS NULL'
);
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    api_error('Staff member not found', 404);
}

$role = $user['role'] ?? '';
$fullAccess = in_array($role, ['Admin', 'Owner'], true);
if (!$fullAccess) {
    $userBranchId = $user['branch_id'] ?? null;
    if (!$userBranchId || (int) $staff['branch_id'] !== (int) $userBranchId) {
        api_error('Forbidden', 403);
    }
}

$limit = api_int($_GET['limit'] ?? 50, 50);
$offset = api_int($_GET['offset'] ?? 0, 0);
$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$expenseStmt = $db->prepare(
    'SELECT e.id, e.type, e.amount, e.salary_before, e.salary_after, e.expense_date, e.salary_month, e.note, '
    . 'e.created_at, u.name AS created_by_name '
    . 'FROM staff_expenses e '
    . 'LEFT JOIN users u ON u.id = e.created_by_user_id '
    . 'WHERE e.staff_id = ? AND e.deleted_at IS NULL '
    . 'ORDER BY e.id DESC LIMIT ? OFFSET ?'
);
$expenseStmt->bindValue(1, $staffId, PDO::PARAM_INT);
$expenseStmt->bindValue(2, $limit, PDO::PARAM_INT);
$expenseStmt->bindValue(3, $offset, PDO::PARAM_INT);
$expenseStmt->execute();
$expenses = $expenseStmt->fetchAll();

api_json(['ok' => true, 'staff' => $staff, 'expenses' => $expenses]);
