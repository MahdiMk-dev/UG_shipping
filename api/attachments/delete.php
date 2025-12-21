<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('POST');
$user = auth_require_user();
$input = api_read_input();

$attachmentId = api_int($input['id'] ?? ($input['attachment_id'] ?? null));
if (!$attachmentId) {
    api_error('attachment_id is required', 422);
}

$stmt = db()->prepare(
    'UPDATE attachments SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL'
);
$stmt->execute([$attachmentId]);

if ($stmt->rowCount() === 0) {
    api_error('Attachment not found', 404);
}

api_json(['ok' => true]);
