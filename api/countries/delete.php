<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$countryId = api_int($input['country_id'] ?? ($input['id'] ?? null));
if (!$countryId) {
    api_error('country_id is required', 422);
}

$stmt = db()->prepare('DELETE FROM countries WHERE id = ?');

try {
    $stmt->execute([$countryId]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        api_error('Country is in use and cannot be deleted', 409);
    }
    api_error('Failed to delete country', 500);
}

if ($stmt->rowCount() === 0) {
    api_error('Country not found', 404);
}

api_json(['ok' => true]);
