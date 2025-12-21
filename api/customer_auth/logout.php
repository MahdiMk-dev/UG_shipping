<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/customer_auth.php';

api_require_method('POST');
customer_auth_logout();
api_json(['ok' => true]);
