<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/company.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$shipmentId = api_int($input['shipment_id'] ?? ($input['id'] ?? null));
$status = api_string($input['status'] ?? null);

if (!$shipmentId || !$status) {
    api_error('shipment_id and status are required', 422);
}

$allowedStatus = ['active', 'departed', 'airport', 'arrived', 'partially_distributed', 'distributed'];
if (!in_array($status, $allowedStatus, true)) {
    api_error('Invalid status', 422);
}
$receivedStatuses = ['received_subbranch', 'with_delivery', 'picked_up'];

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM shipments WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$shipmentId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Shipment not found', 404);
}
if (($before['status'] ?? '') === 'distributed' && !in_array($status, ['distributed', 'partially_distributed'], true)) {
    api_error('Cannot update status after shipment is distributed', 422);
}

$db->beginTransaction();
try {
    $fields = ['status = ?', 'updated_at = NOW()', 'updated_by_user_id = ?'];
    $params = [$status, $user['id'] ?? null];
    if ($status === 'departed' && empty($before['actual_departure_date'])) {
        $fields[] = 'actual_departure_date = CURDATE()';
    }
    if ($status === 'arrived' && empty($before['actual_arrival_date'])) {
        $fields[] = 'actual_arrival_date = CURDATE()';
    }
    $params[] = $shipmentId;
    $stmt = $db->prepare(
        'UPDATE shipments SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL'
    );
    $stmt->execute($params);

    if (in_array($status, ['active', 'airport'], true) && ($before['status'] ?? '') !== $status) {
        $pointsSettings = company_points_settings();
        $pointsPrice = (float) ($pointsSettings['points_price'] ?? 0);
        $placeholders = implode(',', array_fill(0, count($receivedStatuses), '?'));
        $receivedStmt = $db->prepare(
            'SELECT id, customer_id, sub_branch_id, total_price FROM orders '
            . 'WHERE shipment_id = ? AND deleted_at IS NULL '
            . "AND fulfillment_status IN ($placeholders)"
        );
        $receivedStmt->execute(array_merge([$shipmentId], $receivedStatuses));
        $receivedOrders = $receivedStmt->fetchAll() ?: [];
        foreach ($receivedOrders as $order) {
            $totalPrice = (float) ($order['total_price'] ?? 0);
            $customerId = (int) ($order['customer_id'] ?? 0);
            adjust_customer_balance($db, $customerId, -$totalPrice);
            adjust_customer_points_for_amount($db, $customerId, -$totalPrice, $pointsPrice);
            record_customer_balance(
                $db,
                $customerId,
                !empty($order['sub_branch_id']) ? (int) $order['sub_branch_id'] : null,
                -$totalPrice,
                'order_reversal',
                'order',
                (int) $order['id'],
                $user['id'] ?? null,
                'Shipment status reset'
            );
            record_branch_balance(
                $db,
                (int) ($order['sub_branch_id'] ?? 0),
                -$totalPrice,
                'order_reversal',
                'order',
                (int) $order['id'],
                $user['id'] ?? null,
                'Shipment status reset'
            );
        }

        $ordersStmt = $db->prepare(
            'UPDATE orders SET fulfillment_status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE shipment_id = ? AND deleted_at IS NULL '
            . "AND fulfillment_status IN ('main_branch', 'pending_receipt', 'received_subbranch', 'with_delivery', 'picked_up')"
        );
        $ordersStmt->execute(['in_shipment', $user['id'] ?? null, $shipmentId]);
    }

    $afterStmt = $db->prepare('SELECT * FROM shipments WHERE id = ?');
    $afterStmt->execute([$shipmentId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'shipments.status', 'shipment', $shipmentId, $before, $after, ['status' => $status]);
    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to update shipment status', 500);
}

api_json(['ok' => true]);
