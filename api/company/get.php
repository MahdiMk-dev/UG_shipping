<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/company.php';

api_require_method('GET');
require_role(['Admin', 'Owner']);

$settings = company_settings();
unset($settings['domain_expiry']);

api_json(['ok' => true, 'data' => $settings]);
