<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$type = api_string($input['type'] ?? null);
$name = api_string($input['name'] ?? null);
$phone = api_string($input['phone'] ?? null);
$email = api_string($input['email'] ?? null);
$address = api_string($input['address'] ?? null);
$status = api_string($input['status'] ?? 'active') ?? 'active';
$openingBalance = api_float($input['opening_balance'] ?? 0);

if (!$type) {
    api_error('type is required', 422);
}
if (!$name) {
    api_error('name is required', 422);
}
if ($openingBalance === null) {
    api_error('opening_balance must be a number', 422);
}

$allowedStatus = ['active', 'inactive'];
if (!in_array($status, $allowedStatus, true)) {
    api_error('Invalid status', 422);
}

$openingBalance = round((float) $openingBalance, 2);

$stmt = db()->prepare(
    'INSERT INTO partners (type, name, phone, email, address, status, opening_balance, current_balance) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $type,
    $name,
    $phone,
    $email,
    $address,
    $status,
    $openingBalance,
    $openingBalance,
]);

api_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
