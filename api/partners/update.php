<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$partnerId = api_int($input['id'] ?? ($input['partner_id'] ?? null));
$type = api_string($input['type'] ?? null);
$name = api_string($input['name'] ?? null);
$phone = api_string($input['phone'] ?? null);
$email = api_string($input['email'] ?? null);
$address = api_string($input['address'] ?? null);
$status = api_string($input['status'] ?? 'active') ?? 'active';

if (!$partnerId) {
    api_error('id is required', 422);
}
if (!$type) {
    api_error('type is required', 422);
}
if (!$name) {
    api_error('name is required', 422);
}

$allowedStatus = ['active', 'inactive'];
if (!in_array($status, $allowedStatus, true)) {
    api_error('Invalid status', 422);
}

$stmt = db()->prepare(
    'UPDATE partners SET type = ?, name = ?, phone = ?, email = ?, address = ?, status = ? WHERE id = ?'
);
$stmt->execute([
    $type,
    $name,
    $phone,
    $email,
    $address,
    $status,
    $partnerId,
]);

if ($stmt->rowCount() === 0) {
    $check = db()->prepare('SELECT id FROM partners WHERE id = ?');
    $check->execute([$partnerId]);
    if (!$check->fetch()) {
        api_error('Partner not found', 404);
    }
}

api_json(['ok' => true]);
