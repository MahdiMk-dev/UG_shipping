<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);

$db = db();
$datePart = date('ymd');
$prefix = 'UG' . $datePart;
$maxAttempts = 20;

$stmt = $db->prepare(
    'SELECT 1 FROM orders WHERE tracking_number = ? AND deleted_at IS NULL LIMIT 1'
);

$trackingNumber = null;
for ($i = 0; $i < $maxAttempts; $i += 1) {
    $randomPart = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $candidate = $prefix . $randomPart;
    $stmt->execute([$candidate]);
    if (!$stmt->fetchColumn()) {
        $trackingNumber = $candidate;
        break;
    }
}

if (!$trackingNumber) {
    api_error('Unable to generate tracking number. Try again.', 500);
}

api_json(['ok' => true, 'tracking_number' => $trackingNumber]);
