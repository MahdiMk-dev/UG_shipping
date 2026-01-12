<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config/config.php';

function company_settings(): array
{
    $fallbackLogo = config_get('company.logo_public', PUBLIC_URL . '/assets/img/ug-logo.jpg');
    $fallback = [
        'name' => config_get('company.name', 'Company'),
        'address' => config_get('company.location', ''),
        'phone' => config_get('company.phone', ''),
        'email' => config_get('company.email', ''),
        'website' => config_get('company.website', ''),
        'logo_url' => $fallbackLogo,
        'points_price' => 0.0,
        'points_value' => 0.0,
        'usd_to_lbp' => 0.0,
    ];

    try {
        $stmt = db()->query(
            'SELECT name, address, phone, email, website, logo_url, points_price, points_value, usd_to_lbp '
            . 'FROM company_settings WHERE id = 1'
        );
        $row = $stmt->fetch();
        if (!$row) {
            return $fallback;
        }

        $logoUrl = trim((string) ($row['logo_url'] ?? ''));
        if ($logoUrl !== '') {
            if (!preg_match('#^https?://#', $logoUrl)) {
                if ($logoUrl[0] === '/') {
                    $logoUrl = rtrim(BASE_URL, '/') . $logoUrl;
                } else {
                    $logoUrl = rtrim(PUBLIC_URL, '/') . '/' . ltrim($logoUrl, '/');
                }
            }
        } else {
            $logoUrl = '';
        }

        return [
            'name' => $row['name'] ?? $fallback['name'],
            'address' => $row['address'] ?? $fallback['address'],
            'phone' => $row['phone'] ?? $fallback['phone'],
            'email' => $row['email'] ?? $fallback['email'],
            'website' => $row['website'] ?? $fallback['website'],
            'logo_url' => $logoUrl,
            'points_price' => $row['points_price'] !== null ? (float) $row['points_price'] : $fallback['points_price'],
            'points_value' => $row['points_value'] !== null ? (float) $row['points_value'] : $fallback['points_value'],
            'usd_to_lbp' => $row['usd_to_lbp'] !== null ? (float) $row['usd_to_lbp'] : $fallback['usd_to_lbp'],
        ];
    } catch (Throwable $e) {
        return $fallback;
    }
}

function company_points_settings(): array
{
    try {
        $stmt = db()->query('SELECT points_price, points_value FROM company_settings WHERE id = 1');
        $row = $stmt->fetch();
        if (!$row) {
            return ['points_price' => 0.0, 'points_value' => 0.0];
        }
        return [
            'points_price' => $row['points_price'] !== null ? (float) $row['points_price'] : 0.0,
            'points_value' => $row['points_value'] !== null ? (float) $row['points_value'] : 0.0,
        ];
    } catch (Throwable $e) {
        return ['points_price' => 0.0, 'points_value' => 0.0];
    }
}

function company_logo_file_path(?string $logoUrl): ?string
{
    $logoUrl = trim((string) $logoUrl);
    if ($logoUrl === '') {
        return null;
    }

    $path = $logoUrl;
    if (preg_match('#^https?://#', $logoUrl)) {
        $parsedPath = parse_url($logoUrl, PHP_URL_PATH);
        $path = $parsedPath !== null ? $parsedPath : '';
    }

    if ($path === '') {
        return null;
    }

    if (defined('PUBLIC_PATH') && PUBLIC_PATH !== '/' && strpos($path, PUBLIC_PATH) === 0) {
        $path = substr($path, strlen(PUBLIC_PATH));
    }

    $path = ltrim($path, '/');
    $uploadsPos = strpos($path, 'uploads/');
    if ($uploadsPos === false) {
        return null;
    }
    if ($uploadsPos !== 0) {
        $path = substr($path, $uploadsPos);
    }

    if ($path === '' || strpos($path, 'uploads/') !== 0) {
        return null;
    }

    return APP_ROOT . '/public/' . $path;
}

function company_delete_logo_file(?string $logoUrl): void
{
    $path = company_logo_file_path($logoUrl);
    if (!$path) {
        return;
    }

    $realPath = realpath($path);
    $uploadsRoot = realpath(APP_ROOT . '/public/uploads');
    if (!$realPath || !$uploadsRoot) {
        return;
    }

    if (strpos($realPath, $uploadsRoot) !== 0) {
        return;
    }

    if (is_file($realPath)) {
        @unlink($realPath);
    }
}
