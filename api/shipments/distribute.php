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
if (!in_array($status, ['arrived', 'partially_distributed'], true)) {
    api_error('Shipment must be arrived or partially distributed to distribute', 422);
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

    $remainingMainStmt = $db->prepare(
        'SELECT COUNT(*) FROM orders WHERE shipment_id = ? AND deleted_at IS NULL '
        . 'AND fulfillment_status = ?'
    );
    $remainingMainStmt->execute([$shipmentId, 'main_branch']);
    $remainingMainBranch = (int) $remainingMainStmt->fetchColumn();

    $remainingInShipmentStmt = $db->prepare(
        'SELECT COUNT(*) FROM orders WHERE shipment_id = ? AND deleted_at IS NULL '
        . 'AND fulfillment_status = ?'
    );
    $remainingInShipmentStmt->execute([$shipmentId, 'in_shipment']);
    $remainingInShipment = (int) $remainingInShipmentStmt->fetchColumn();

    $remainingUndistributed = $remainingMainBranch + $remainingInShipment;
    $newStatus = $remainingUndistributed === 0 ? 'distributed' : 'partially_distributed';
    $shipmentDistributed = $newStatus === 'distributed';

    $updateShipment = $db->prepare(
        'UPDATE shipments SET status = ?, updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ? AND deleted_at IS NULL'
    );
    $updateShipment->execute([$newStatus, $user['id'] ?? null, $shipmentId]);

    $branchesStmt = $db->prepare(
        'SELECT DISTINCT b.id, b.name FROM orders o '
        . 'JOIN branches b ON b.id = o.sub_branch_id '
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL AND o.fulfillment_status = ? '
        . 'ORDER BY b.name'
    );
    $branchesStmt->execute([$shipmentId, 'pending_receipt']);
    $branches = $branchesStmt->fetchAll();

    $afterStmt = $db->prepare('SELECT * FROM shipments WHERE id = ?');
    $afterStmt->execute([$shipmentId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'shipments.distribute', 'shipment', $shipmentId, $before, $after, [
        'updated_orders' => $updatedOrders,
        'remaining_main_branch' => $remainingMainBranch,
        'remaining_in_shipment' => $remainingInShipment,
        'shipment_status' => $newStatus,
        'shipment_distributed' => $shipmentDistributed,
        'branches' => $branches,
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
    'remaining_main_branch' => $remainingMainBranch,
    'remaining_in_shipment' => $remainingInShipment,
    'shipment_status' => $newStatus,
    'shipment_distributed' => $shipmentDistributed,
    'branches' => $branches ?? [],
]);
