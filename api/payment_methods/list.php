<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();

$stmt = db()->query('SELECT id, name FROM payment_methods ORDER BY name ASC');
$rows = $stmt->fetchAll();

api_json(['ok' => true, 'data' => $rows]);
