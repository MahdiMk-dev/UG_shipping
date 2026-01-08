<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';

api_require_method('PATCH');
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
$beforeShipmentId = !empty($before['shipment_id']) ? (int) $before['shipment_id'] : null;

$fields = [];
$params = [];

if (array_key_exists('branch_id', $input)) {
    $branchId = api_int($input['branch_id'] ?? null);
    if ($branchId !== null && $branchId > 0) {
        $branchStmt = $db->prepare('SELECT id FROM branches WHERE id = ? AND deleted_at IS NULL');
        $branchStmt->execute([$branchId]);
        if (!$branchStmt->fetch()) {
            api_error('Branch not found', 404);
        }
    } else {
        $branchId = null;
    }
    $fields[] = 'branch_id = ?';
    $params[] = $branchId;
}

if (array_key_exists('shipment_id', $input)) {
    $shipmentId = api_int($input['shipment_id'] ?? null);
    if ($shipmentId !== null && $shipmentId > 0) {
        $shipmentStmt = $db->prepare('SELECT id FROM shipments WHERE id = ? AND deleted_at IS NULL');
        $shipmentStmt->execute([$shipmentId]);
        if (!$shipmentStmt->fetch()) {
            api_error('Shipment not found', 404);
        }
    } else {
        $shipmentId = null;
    }
    $fields[] = 'shipment_id = ?';
    $params[] = $shipmentId;
}

if (array_key_exists('title', $input)) {
    $title = api_string($input['title'] ?? null);
    if (!$title) {
        api_error('title is required', 422);
    }
    $fields[] = 'title = ?';
    $params[] = $title;
}

if (array_key_exists('amount', $input)) {
    $amount = api_float($input['amount'] ?? null);
    if ($amount === null || (float) $amount <= 0.0) {
        api_error('amount must be greater than zero', 422);
    }
    $fields[] = 'amount = ?';
    $params[] = $amount;
}

if (array_key_exists('expense_date', $input)) {
    $expenseDate = api_string($input['expense_date'] ?? null);
    if ($expenseDate !== null && strtotime($expenseDate) === false) {
        api_error('Invalid expense_date', 422);
    }
    $fields[] = 'expense_date = ?';
    $params[] = $expenseDate;
}

if (array_key_exists('note', $input)) {
    $fields[] = 'note = ?';
    $params[] = api_string($input['note'] ?? null);
}

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;
$params[] = $expenseId;

$sql = 'UPDATE general_expenses SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';

$db->beginTransaction();
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $afterStmt = $db->prepare('SELECT * FROM general_expenses WHERE id = ?');
    $afterStmt->execute([$expenseId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'expenses.update', 'general_expense', $expenseId, $before, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to update expense', 500);
}

if ($beforeShipmentId) {
    update_shipment_cost_per_unit($beforeShipmentId);
}
$afterShipmentId = !empty($after['shipment_id']) ? (int) $after['shipment_id'] : null;
if ($afterShipmentId && $afterShipmentId !== $beforeShipmentId) {
    update_shipment_cost_per_unit($afterShipmentId);
}

api_json(['ok' => true]);
