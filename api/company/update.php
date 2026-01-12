<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$name = api_string($input['name'] ?? null);
$address = api_string($input['address'] ?? null);
$phone = api_string($input['phone'] ?? null);
$email = api_string($input['email'] ?? null);
$website = api_string($input['website'] ?? null);
$logoUrl = api_string($input['logo_url'] ?? null);
$pointsPrice = api_float($input['points_price'] ?? null);
$pointsValue = api_float($input['points_value'] ?? null);
$usdToLbp = api_float($input['usd_to_lbp'] ?? null);

if (!$name) {
    api_error('Company name is required', 422);
}
if ($pointsPrice !== null && $pointsPrice < 0) {
    api_error('points_price must be 0 or greater', 422);
}
if ($pointsValue !== null && $pointsValue < 0) {
    api_error('points_value must be 0 or greater', 422);
}
if ($usdToLbp !== null && $usdToLbp < 0) {
    api_error('usd_to_lbp must be 0 or greater', 422);
}

$stmt = db()->prepare(
    'INSERT INTO company_settings '
    . '(id, name, address, phone, email, website, logo_url, points_price, points_value, usd_to_lbp, '
    . 'updated_at, updated_by_user_id) '
    . 'VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?) '
    . 'ON DUPLICATE KEY UPDATE '
    . 'name = VALUES(name), '
    . 'address = VALUES(address), '
    . 'phone = VALUES(phone), '
    . 'email = VALUES(email), '
    . 'website = VALUES(website), '
    . 'logo_url = VALUES(logo_url), '
    . 'points_price = VALUES(points_price), '
    . 'points_value = VALUES(points_value), '
    . 'usd_to_lbp = VALUES(usd_to_lbp), '
    . 'updated_at = VALUES(updated_at), '
    . 'updated_by_user_id = VALUES(updated_by_user_id)'
);

$stmt->execute([
    $name,
    $address,
    $phone,
    $email,
    $website,
    $logoUrl,
    $pointsPrice,
    $pointsValue,
    $usdToLbp,
    $user['id'] ?? null,
]);

api_json(['ok' => true]);
