<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/company.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$orderId = api_int($input['order_id'] ?? ($input['id'] ?? null));
if (!$orderId) {
    api_error('order_id is required', 422);
}

$db = db();
$orderStmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND deleted_at IS NULL');
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();
if (!$order) {
    api_error('Order not found', 404);
}

$previousStatus = (string) ($order['fulfillment_status'] ?? '');
if (in_array($previousStatus, ['with_delivery', 'picked_up'], true)) {
    api_error('Order already delivered or picked up', 409);
}

$previousBranchId = (int) ($order['sub_branch_id'] ?? 0);
if ($previousBranchId <= 0) {
    api_error('Order is not assigned to a sub branch', 409);
}

$previousTotal = (float) ($order['total_price'] ?? 0);
$previousCustomerId = (int) ($order['customer_id'] ?? 0);

$db->beginTransaction();
try {
    $db->prepare(
        'UPDATE orders SET fulfillment_status = ?, updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ? AND deleted_at IS NULL'
    )->execute(['main_branch', $user['id'] ?? null, $orderId]);

    $chargedStatuses = ['received_subbranch'];
    if (in_array($previousStatus, $chargedStatuses, true) && $previousBranchId) {
        if ($previousCustomerId) {
            $pointsSettings = company_points_settings();
            $pointsPrice = (float) ($pointsSettings['points_price'] ?? 0);
            adjust_customer_balance($db, $previousCustomerId, -$previousTotal);
            adjust_customer_points_for_amount($db, $previousCustomerId, -$previousTotal, $pointsPrice);
            record_customer_balance(
                $db,
                $previousCustomerId,
                $previousBranchId ?: null,
                -$previousTotal,
                'order_reversal',
                'order',
                $orderId,
                $user['id'] ?? null,
                'Returned to main branch'
            );
        }
        record_branch_balance(
            $db,
            $previousBranchId,
            -$previousTotal,
            'order_reversal',
            'order',
            $orderId,
            $user['id'] ?? null,
            'Returned to main branch'
        );
    }

    $afterStmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $afterStmt->execute([$orderId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'orders.return_main', 'order', $orderId, $order, $after);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to return order to main branch', 500);
}

api_json(['ok' => true]);
