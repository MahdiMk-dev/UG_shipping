<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$orderId = api_int($input['shopping_order_id'] ?? ($input['id'] ?? null));
if (!$orderId) {
    api_error('shopping_order_id is required', 422);
}

$fields = [];
$params = [];

$allowedStatus = ['pending', 'distributed', 'received_subbranch', 'closed', 'canceled'];
$allowedDelivery = ['pickup', 'delivery'];
$allowedFees = [null, 'amount', 'percentage'];

$map = [
    'customer_id' => 'customer_id',
    'sub_branch_id' => 'sub_branch_id',
    'name' => 'name',
    'image_url' => 'image_url',
    'cost' => 'cost',
    'price' => 'price',
    'fees_type' => 'fees_type',
    'fees_amount' => 'fees_amount',
    'fees_percentage' => 'fees_percentage',
    'total' => 'total',
    'delivery_type' => 'delivery_type',
    'status' => 'status',
];

foreach ($map as $key => $column) {
    if (!array_key_exists($key, $input)) {
        continue;
    }

    $value = $input[$key];

    if (in_array($key, ['customer_id', 'sub_branch_id'], true)) {
        $value = api_int($value);
        if (!$value) {
            api_error($key . ' is invalid', 422);
        }
    } elseif (in_array($key, ['cost', 'price', 'fees_amount', 'fees_percentage', 'total'], true)) {
        $value = api_float($value);
    } elseif ($key === 'delivery_type') {
        $value = api_string($value);
        if (!$value || !in_array($value, $allowedDelivery, true)) {
            api_error('Invalid delivery_type', 422);
        }
    } elseif ($key === 'status') {
        $value = api_string($value);
        if (!$value || !in_array($value, $allowedStatus, true)) {
            api_error('Invalid status', 422);
        }
    } elseif ($key === 'fees_type') {
        $value = api_string($value);
        if (!in_array($value, $allowedFees, true)) {
            api_error('Invalid fees_type', 422);
        }
    } elseif (in_array($key, ['name'], true)) {
        $value = api_string($value);
        if (!$value) {
            api_error($key . ' cannot be empty', 422);
        }
    } else {
        $value = api_string($value);
    }

    $fields[] = $column . ' = ?';
    $params[] = $value;
}

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;
$params[] = $orderId;

$sql = 'UPDATE shopping_orders SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
$stmt = db()->prepare($sql);
$stmt->execute($params);

if ($stmt->rowCount() === 0) {
    api_error('Shopping order not found', 404);
}

api_json(['ok' => true]);
