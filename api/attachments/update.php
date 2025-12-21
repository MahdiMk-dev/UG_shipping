<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('PATCH');
$user = auth_require_user();
$input = api_read_input();

$attachmentId = api_int($input['id'] ?? ($input['attachment_id'] ?? null));
if (!$attachmentId) {
    api_error('attachment_id is required', 422);
}

$fields = [];
$params = [];

if (array_key_exists('title', $input)) {
    $title = api_string($input['title'] ?? null);
    if (!$title) {
        api_error('title cannot be empty', 422);
    }
    $fields[] = 'title = ?';
    $params[] = $title;
}

if (array_key_exists('description', $input)) {
    $fields[] = 'description = ?';
    $params[] = api_string($input['description'] ?? null);
}

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$params[] = $attachmentId;

$sql = 'UPDATE attachments SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
$stmt = db()->prepare($sql);
$stmt->execute($params);

if ($stmt->rowCount() === 0) {
    api_error('Attachment not found', 404);
}

api_json(['ok' => true]);
