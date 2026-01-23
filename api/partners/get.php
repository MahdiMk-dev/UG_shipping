<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$partnerId = api_int($_GET['id'] ?? null);

if (!$partnerId) {
    api_error('id is required', 422);
}

$stmt = db()->prepare('SELECT * FROM partners WHERE id = ?');
$stmt->execute([$partnerId]);
$partner = $stmt->fetch();

if (!$partner) {
    api_error('Partner not found', 404);
}

api_json(['ok' => true, 'partner' => $partner]);
