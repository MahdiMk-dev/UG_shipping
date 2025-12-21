<?php
declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    $root = __DIR__ . '/..';
}

define('APP_ROOT', $root);

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');

$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$appRoot = realpath(APP_ROOT);

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
$scheme = $https ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';

$basePath = '';
if ($documentRoot && $appRoot && strpos($appRoot, $documentRoot) === 0) {
    $relative = substr($appRoot, strlen($documentRoot));
    $relative = str_replace('\\', '/', $relative);
    $basePath = rtrim($relative, '/');
} else {
    $basePath = $scriptDir;
    foreach (['/public', '/views', '/api'] as $marker) {
        $pos = strpos($basePath, $marker);
        if ($pos !== false) {
            $basePath = substr($basePath, 0, $pos);
            break;
        }
    }
    $basePath = rtrim($basePath, '/');
}
if ($basePath === '/' || $basePath === '') {
    $basePath = '';
}

if ($host !== '') {
    $baseUrl = $scheme . '://' . $host . $basePath;
} else {
    $baseUrl = $basePath !== '' ? $basePath : '/';
}

define('BASE_URL', $baseUrl);
define('BASE_PATH', $basePath === '' ? '/' : $basePath);

$publicRoot = realpath(APP_ROOT . '/public');
$isPublicRoot = $documentRoot && $publicRoot && $documentRoot === $publicRoot;

$baseUrlTrim = rtrim($baseUrl, '/');
$publicUrl = $isPublicRoot ? $baseUrl : ($baseUrlTrim === '' ? '/public' : $baseUrlTrim . '/public');
$publicPath = $isPublicRoot
    ? (BASE_PATH === '/' ? '/' : BASE_PATH)
    : (BASE_PATH === '/' ? '/public' : BASE_PATH . '/public');

define('PUBLIC_URL', $publicUrl);
define('PUBLIC_PATH', $publicPath);

$config = [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'ug_shipping',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'uploads' => [
        'public_dir' => APP_ROOT . '/public/uploads',
        'max_bytes' => 10 * 1024 * 1024,
        'allowed_mime' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ],
    'features' => [
        'enable_portal' => true,
    ],
    'company' => [
        'name' => 'United Group',
        'location' => 'Beirut, Tayyouneh',
        'phone' => '71277723',
        'logo_public' => PUBLIC_URL . '/assets/img/ug-logo.jpg',
        'logo_path' => APP_ROOT . '/public/assets/img/ug-logo.jpg',
    ],
];

function config_get(string $path, $default = null)
{
    global $config;

    $segments = explode('.', $path);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}
