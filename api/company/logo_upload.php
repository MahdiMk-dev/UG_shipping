<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/attachment_service.php';
require_once __DIR__ . '/../../app/company.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);

if (!isset($_FILES['logo'])) {
    api_error('logo is required', 422);
}

$file = $_FILES['logo'];
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    api_error('Upload failed', 422);
}

$size = (int) ($file['size'] ?? 0);
$maxBytes = (int) config_get('uploads.max_bytes', 0);
if ($maxBytes > 0 && $size > $maxBytes) {
    api_error('File exceeds max size', 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!$mime || strpos($mime, 'image/') !== 0 || !attachment_is_allowed_mime($mime)) {
    api_error('Logo must be an image file', 422);
}

$originalName = (string) ($file['name'] ?? 'logo');
$storedName = attachment_safe_name($originalName);

$relativeDir = 'uploads/company/' . date('Y') . '/' . date('m');
$targetDir = APP_ROOT . '/public/' . $relativeDir;

if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
    api_error('Failed to create upload directory', 500);
}

$targetPath = $targetDir . '/' . $storedName;
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    api_error('Failed to save upload', 500);
}

$logoPath = $relativeDir . '/' . $storedName;

$db = db();
$currentStmt = $db->prepare('SELECT logo_url, name, address, phone, email, website FROM company_settings WHERE id = 1');
$currentStmt->execute();
$current = $currentStmt->fetch();
$previousLogo = $current['logo_url'] ?? null;

try {
    if ($current) {
        $stmt = $db->prepare(
            'UPDATE company_settings SET logo_url = ?, updated_at = NOW(), updated_by_user_id = ? WHERE id = 1'
        );
        $stmt->execute([$logoPath, $user['id'] ?? null]);
    } else {
        $stmt = $db->prepare(
            'INSERT INTO company_settings (id, name, address, phone, email, website, logo_url, updated_at, updated_by_user_id) '
            . 'VALUES (1, ?, ?, ?, ?, ?, ?, NOW(), ?)'
        );
        $stmt->execute([
            config_get('company.name', 'Company'),
            config_get('company.location', ''),
            config_get('company.phone', ''),
            config_get('company.email', ''),
            config_get('company.website', ''),
            $logoPath,
            $user['id'] ?? null,
        ]);
    }
} catch (Throwable $e) {
    if (is_file($targetPath)) {
        @unlink($targetPath);
    }
    api_error('Failed to update company logo', 500);
}

if ($previousLogo) {
    company_delete_logo_file($previousLogo);
}

$settings = company_settings();

api_json(['ok' => true, 'data' => ['logo_url' => $settings['logo_url'] ?? '']]);
