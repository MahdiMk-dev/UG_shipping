<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$branchId = api_int($input['branch_id'] ?? null);
$shipmentId = api_int($input['shipment_id'] ?? null);
$title = api_string($input['title'] ?? null);
$amount = api_float($input['amount'] ?? null);
$expenseDate = api_string($input['expense_date'] ?? null);
$note = api_string($input['note'] ?? null);
if (!$title || $amount === null) {
    api_error('title and amount are required', 422);
}
if ((float) $amount <= 0.0) {
    api_error('amount must be greater than zero', 422);
}
if ($expenseDate !== null && strtotime($expenseDate) === false) {
    api_error('Invalid expense_date', 422);
}

if ($branchId !== null && $branchId > 0) {
    $branchStmt = db()->prepare('SELECT id FROM branches WHERE id = ? AND deleted_at IS NULL');
    $branchStmt->execute([$branchId]);
    if (!$branchStmt->fetch()) {
        api_error('Branch not found', 404);
    }
} else {
    $branchId = null;
}

if ($shipmentId !== null && $shipmentId > 0) {
    $shipmentStmt = db()->prepare('SELECT id FROM shipments WHERE id = ? AND deleted_at IS NULL');
    $shipmentStmt->execute([$shipmentId]);
    if (!$shipmentStmt->fetch()) {
        api_error('Shipment not found', 404);
    }
} else {
    $shipmentId = null;
}

$db = db();
$db->beginTransaction();

try {
    $stmt = $db->prepare(
        'INSERT INTO general_expenses '
        . '(branch_id, shipment_id, title, amount, expense_date, note, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $branchId,
        $shipmentId,
        $title,
        $amount,
        $expenseDate,
        $note,
        $user['id'] ?? null,
    ]);

    $expenseId = (int) $db->lastInsertId();
    $rowStmt = $db->prepare('SELECT * FROM general_expenses WHERE id = ?');
    $rowStmt->execute([$expenseId]);
    $after = $rowStmt->fetch();
    audit_log($user, 'expenses.create', 'general_expense', $expenseId, null, $after);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to create expense', 500);
}

if ($shipmentId) {
    update_shipment_cost_per_unit($shipmentId);
}

api_json(['ok' => true, 'id' => $expenseId]);
