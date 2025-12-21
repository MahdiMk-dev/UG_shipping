<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$methodId = api_int($input['payment_method_id'] ?? ($input['id'] ?? null));
if (!$methodId) {
    api_error('payment_method_id is required', 422);
}

$stmt = db()->prepare('DELETE FROM payment_methods WHERE id = ?');

try {
    $stmt->execute([$methodId]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        api_error('Payment method is in use and cannot be deleted', 409);
    }
    api_error('Failed to delete payment method', 500);
}

if ($stmt->rowCount() === 0) {
    api_error('Payment method not found', 404);
}

api_json(['ok' => true]);
