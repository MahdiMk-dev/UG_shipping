<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner']);

$stmt = db()->query('SELECT id, name FROM roles ORDER BY name ASC');
$rows = $stmt->fetchAll();

api_json(['ok' => true, 'data' => $rows]);
