<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$orderId = api_int($input['shopping_order_id'] ?? ($input['id'] ?? null));
if (!$orderId) {
    api_error('shopping_order_id is required', 422);
}

$stmt = db()->prepare(
    'UPDATE shopping_orders SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
    . 'WHERE id = ? AND deleted_at IS NULL'
);
$stmt->execute([$user['id'] ?? null, $orderId]);

if ($stmt->rowCount() === 0) {
    api_error('Shopping order not found', 404);
}

api_json(['ok' => true]);
