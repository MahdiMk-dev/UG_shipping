<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

function attachment_is_allowed_mime(string $mime): bool
{
    $allowed = config_get('uploads.allowed_mime', []);

    return in_array($mime, $allowed, true);
}

function attachment_safe_name(string $originalName): string
{
    $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
    $base = trim($base, '._-');
    if ($base === '') {
        $base = 'file';
    }

    $ext = pathinfo($base, PATHINFO_EXTENSION);
    $name = pathinfo($base, PATHINFO_FILENAME);

    $suffix = bin2hex(random_bytes(8));
    return $name . '_' . $suffix . ($ext ? '.' . $ext : '');
}
