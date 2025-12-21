<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$name = api_string($input['name'] ?? null);
$iso2 = api_string($input['iso2'] ?? null);
$iso3 = api_string($input['iso3'] ?? null);
$phoneCode = api_string($input['phone_code'] ?? null);

if (!$name || !$iso2) {
    api_error('name and iso2 are required', 422);
}

$stmt = db()->prepare(
    'INSERT INTO countries (name, iso2, iso3, phone_code) VALUES (?, ?, ?, ?)'
);

try {
    $stmt->execute([$name, strtoupper($iso2), $iso3, $phoneCode]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        api_error('Country already exists', 409);
    }
    api_error('Failed to create country', 500);
}

api_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
