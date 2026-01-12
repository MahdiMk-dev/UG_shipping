<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/company.php';

api_require_method('GET');
$user = auth_require_user();
$role = $user['role'] ?? '';

if (!in_array($role, ['Admin', 'Owner', 'Main Branch', 'Sub Branch'], true)) {
    api_error('Forbidden', 403);
}

$settings = company_points_settings();

api_json(['ok' => true, 'data' => $settings]);
