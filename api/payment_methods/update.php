<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$methodId = api_int($input['payment_method_id'] ?? ($input['id'] ?? null));
$name = api_string($input['name'] ?? null);

if (!$methodId || !$name) {
    api_error('payment_method_id and name are required', 422);
}

$stmt = db()->prepare('UPDATE payment_methods SET name = ? WHERE id = ?');

try {
    $stmt->execute([$name, $methodId]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        api_error('Payment method already exists', 409);
    }
    api_error('Failed to update payment method', 500);
}

if ($stmt->rowCount() === 0) {
    api_error('Payment method not found', 404);
}

api_json(['ok' => true]);
