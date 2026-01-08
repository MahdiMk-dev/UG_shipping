<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$expenseId = api_int($input['id'] ?? ($input['expense_id'] ?? null));
if (!$expenseId) {
    api_error('expense_id is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM general_expenses WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$expenseId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Expense not found', 404);
}
$shipmentId = !empty($before['shipment_id']) ? (int) $before['shipment_id'] : null;

$stmt = $db->prepare(
    'UPDATE general_expenses SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
    . 'WHERE id = ? AND deleted_at IS NULL'
);
try {
    $stmt->execute([$user['id'] ?? null, $expenseId]);
    $afterStmt = $db->prepare('SELECT * FROM general_expenses WHERE id = ?');
    $afterStmt->execute([$expenseId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'expenses.delete', 'general_expense', $expenseId, $before, $after);
} catch (PDOException $e) {
    api_error('Failed to delete expense', 500);
}

if ($shipmentId) {
    update_shipment_cost_per_unit($shipmentId);
}

api_json(['ok' => true]);
