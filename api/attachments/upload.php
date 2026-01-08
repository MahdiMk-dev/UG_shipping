<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/services/attachment_service.php';

api_require_method('POST');
$user = auth_require_user();

$entityType = api_string($_POST['entity_type'] ?? null);
$entityId = api_int($_POST['entity_id'] ?? null);
$title = api_string($_POST['title'] ?? null);
$description = api_string($_POST['description'] ?? null);

if (!$entityType || !$entityId) {
    api_error('entity_type and entity_id are required', 422);
}

$allowedTypes = ['shipment', 'order', 'shopping_order', 'invoice', 'collection'];
if (!in_array($entityType, $allowedTypes, true)) {
    api_error('Invalid entity_type', 422);
}

if (!isset($_FILES['file'])) {
    api_error('file is required', 422);
}

$file = $_FILES['file'];
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
if (!$mime || !attachment_is_allowed_mime($mime)) {
    api_error('File type not allowed', 422);
}

$originalName = (string) ($file['name'] ?? 'file');
$storedName = attachment_safe_name($originalName);

$relativeDir = 'uploads/' . date('Y') . '/' . date('m');
$targetDir = APP_ROOT . '/public/' . $relativeDir;

if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
    api_error('Failed to create upload directory', 500);
}

$targetPath = $targetDir . '/' . $storedName;
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    api_error('Failed to save upload', 500);
}

$filePath = $relativeDir . '/' . $storedName;

$db = db();

$entityMap = [
    'shipment' => 'shipments',
    'order' => 'orders',
    'shopping_order' => 'shopping_orders',
    'invoice' => 'invoices',
    'collection' => 'collections',
];

$table = $entityMap[$entityType];
if ($entityType === 'collection') {
    $checkStmt = $db->prepare("SELECT id FROM {$table} WHERE id = ?");
} else {
    $checkStmt = $db->prepare("SELECT id FROM {$table} WHERE id = ? AND deleted_at IS NULL");
}
$checkStmt->execute([$entityId]);
if (!$checkStmt->fetch()) {
    api_error('Entity not found', 404);
}

$stmt = $db->prepare(
    'INSERT INTO attachments '
    . '(entity_type, entity_id, title, description, file_path, original_name, mime_type, size_bytes, uploaded_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$stmt->execute([
    $entityType,
    $entityId,
    $title ?: $originalName,
    $description,
    $filePath,
    $originalName,
    $mime,
    $size,
    $user['id'] ?? null,
]);

api_json(['ok' => true, 'id' => (int) $db->lastInsertId()]);
