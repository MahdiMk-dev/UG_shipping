<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$shipmentId = api_int($input['shipment_id'] ?? ($input['id'] ?? null));
if (!$shipmentId) {
    api_error('shipment_id is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM shipments WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$shipmentId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Shipment not found', 404);
}

$status = $before['status'] ?? '';
if ($status !== 'arrived') {
    api_error('Shipment must be arrived to distribute', 422);
}

$db->beginTransaction();
try {
    $updateOrders = $db->prepare(
        'UPDATE orders SET fulfillment_status = ?, updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE shipment_id = ? AND deleted_at IS NULL '
        . 'AND sub_branch_id > 0 AND fulfillment_status = ?'
    );
    $updateOrders->execute(['pending_receipt', $user['id'] ?? null, $shipmentId, 'main_branch']);
    $updatedOrders = $updateOrders->rowCount();

    $remainingStmt = $db->prepare(
        'SELECT COUNT(*) FROM orders WHERE shipment_id = ? AND deleted_at IS NULL '
        . 'AND fulfillment_status = ? AND (sub_branch_id IS NULL OR sub_branch_id = 0)'
    );
    $remainingStmt->execute([$shipmentId, 'main_branch']);
    $remainingWithoutBranch = (int) $remainingStmt->fetchColumn();

    $shipmentDistributed = false;
    if ($remainingWithoutBranch === 0) {
        $updateShipment = $db->prepare(
            'UPDATE shipments SET status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE id = ? AND deleted_at IS NULL'
        );
        $updateShipment->execute(['distributed', $user['id'] ?? null, $shipmentId]);
        $shipmentDistributed = true;
    }

    $afterStmt = $db->prepare('SELECT * FROM shipments WHERE id = ?');
    $afterStmt->execute([$shipmentId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'shipments.distribute', 'shipment', $shipmentId, $before, $after, [
        'updated_orders' => $updatedOrders,
        'remaining_without_branch' => $remainingWithoutBranch,
        'shipment_distributed' => $shipmentDistributed,
    ]);

    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to distribute shipment', 500);
}

api_json([
    'ok' => true,
    'updated_orders' => $updatedOrders,
    'remaining_without_branch' => $remainingWithoutBranch,
    'shipment_distributed' => $shipmentDistributed,
]);
