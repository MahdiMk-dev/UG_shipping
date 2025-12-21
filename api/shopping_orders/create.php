<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$customerId = api_int($input['customer_id'] ?? null);
$subBranchId = api_int($input['sub_branch_id'] ?? null);
$name = api_string($input['name'] ?? null);
$imageUrl = api_string($input['image_url'] ?? null);
$cost = api_float($input['cost'] ?? null);
$price = api_float($input['price'] ?? null);
$feesType = api_string($input['fees_type'] ?? null);
$feesAmount = api_float($input['fees_amount'] ?? null);
$feesPercentage = api_float($input['fees_percentage'] ?? null);
$total = api_float($input['total'] ?? null);
$deliveryType = api_string($input['delivery_type'] ?? null);
$status = api_string($input['status'] ?? 'pending') ?? 'pending';

if (!$customerId || !$subBranchId || !$name || !$deliveryType) {
    api_error('customer_id, sub_branch_id, name, and delivery_type are required', 422);
}

$allowedStatus = ['pending', 'distributed', 'received_subbranch', 'closed', 'canceled'];
if (!in_array($status, $allowedStatus, true)) {
    api_error('Invalid status', 422);
}

$allowedDelivery = ['pickup', 'delivery'];
if (!in_array($deliveryType, $allowedDelivery, true)) {
    api_error('Invalid delivery_type', 422);
}

$allowedFees = [null, 'amount', 'percentage'];
if (!in_array($feesType, $allowedFees, true)) {
    api_error('Invalid fees_type', 422);
}

if ($total === null) {
    $computedFees = 0.0;
    if ($feesType === 'amount') {
        $computedFees = (float) ($feesAmount ?? 0);
    } elseif ($feesType === 'percentage') {
        $computedFees = (float) ($price ?? 0) * ((float) ($feesPercentage ?? 0) / 100);
    }
    $total = (float) ($price ?? 0) + $computedFees;
}

$stmt = db()->prepare(
    'INSERT INTO shopping_orders '
    . '(customer_id, sub_branch_id, name, image_url, cost, price, fees_type, fees_amount, fees_percentage, '
    . 'total, delivery_type, status, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$stmt->execute([
    $customerId,
    $subBranchId,
    $name,
    $imageUrl,
    $cost,
    $price,
    $feesType,
    $feesAmount,
    $feesPercentage,
    $total,
    $deliveryType,
    $status,
    $user['id'] ?? null,
]);

api_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
