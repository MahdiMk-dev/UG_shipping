<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$countryId = api_int($input['country_id'] ?? ($input['id'] ?? null));
if (!$countryId) {
    api_error('country_id is required', 422);
}

$fields = [];
$params = [];

if (array_key_exists('name', $input)) {
    $name = api_string($input['name'] ?? null);
    if (!$name) {
        api_error('name cannot be empty', 422);
    }
    $fields[] = 'name = ?';
    $params[] = $name;
}

if (array_key_exists('iso2', $input)) {
    $iso2 = api_string($input['iso2'] ?? null);
    if (!$iso2) {
        api_error('iso2 cannot be empty', 422);
    }
    $fields[] = 'iso2 = ?';
    $params[] = strtoupper($iso2);
}

if (array_key_exists('iso3', $input)) {
    $fields[] = 'iso3 = ?';
    $params[] = api_string($input['iso3'] ?? null);
}

if (array_key_exists('phone_code', $input)) {
    $fields[] = 'phone_code = ?';
    $params[] = api_string($input['phone_code'] ?? null);
}

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$params[] = $countryId;

$sql = 'UPDATE countries SET ' . implode(', ', $fields) . ' WHERE id = ?';

try {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        api_error('Country already exists', 409);
    }
    api_error('Failed to update country', 500);
}

if ($stmt->rowCount() === 0) {
    api_error('Country not found', 404);
}

api_json(['ok' => true]);
