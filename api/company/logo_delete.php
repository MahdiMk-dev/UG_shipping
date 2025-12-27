<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/company.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);

$db = db();
$currentStmt = $db->prepare('SELECT logo_url, name, address, phone, email, website FROM company_settings WHERE id = 1');
$currentStmt->execute();
$current = $currentStmt->fetch();
$previousLogo = $current['logo_url'] ?? null;

try {
    if ($current) {
        $stmt = $db->prepare(
            'UPDATE company_settings SET logo_url = NULL, updated_at = NOW(), updated_by_user_id = ? WHERE id = 1'
        );
        $stmt->execute([$user['id'] ?? null]);
    } else {
        $stmt = $db->prepare(
            'INSERT INTO company_settings (id, name, address, phone, email, website, logo_url, updated_at, updated_by_user_id) '
            . 'VALUES (1, ?, ?, ?, ?, ?, NULL, NOW(), ?)'
        );
        $stmt->execute([
            config_get('company.name', 'Company'),
            config_get('company.location', ''),
            config_get('company.phone', ''),
            config_get('company.email', ''),
            config_get('company.website', ''),
            $user['id'] ?? null,
        ]);
    }
} catch (Throwable $e) {
    api_error('Failed to delete company logo', 500);
}

if ($previousLogo) {
    company_delete_logo_file($previousLogo);
}

$settings = company_settings();
api_json(['ok' => true, 'data' => ['logo_url' => $settings['logo_url'] ?? '']]);
