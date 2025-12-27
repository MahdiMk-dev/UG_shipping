<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/customer_auth.php';

api_require_method('GET');

$user = auth_user();
$customer = customer_auth_user();

if (!$user && !$customer) {
    api_error('Unauthorized', 401);
}
if ($customer && empty($customer['account_id'])) {
    api_error('Customer session is invalid', 401);
}

$attachmentId = api_int($_GET['id'] ?? ($_GET['attachment_id'] ?? null));
if (!$attachmentId) {
    api_error('attachment id is required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT id, entity_type, entity_id, title, file_path, original_name, mime_type, size_bytes '
    . 'FROM attachments WHERE id = ? AND deleted_at IS NULL'
);
$stmt->execute([$attachmentId]);
$attachment = $stmt->fetch();

if (!$attachment) {
    api_error('Attachment not found', 404);
}

if ($customer) {
    $entityType = $attachment['entity_type'];
    $entityId = (int) $attachment['entity_id'];

    if ($entityType === 'shipment') {
        api_error('Forbidden', 403);
    }

    if ($entityType === 'order') {
        $check = $db->prepare(
            'SELECT o.id FROM orders o '
            . 'JOIN customers c ON c.id = o.customer_id '
            . 'WHERE o.id = ? AND c.account_id = ? AND o.deleted_at IS NULL AND c.deleted_at IS NULL'
        );
        $check->execute([$entityId, $customer['account_id']]);
        if (!$check->fetch()) {
            api_error('Forbidden', 403);
        }
    }

    if ($entityType === 'invoice') {
        $check = $db->prepare(
            'SELECT i.id FROM invoices i '
            . 'JOIN customers c ON c.id = i.customer_id '
            . 'WHERE i.id = ? AND c.account_id = ? AND i.deleted_at IS NULL AND c.deleted_at IS NULL'
        );
        $check->execute([$entityId, $customer['account_id']]);
        if (!$check->fetch()) {
            api_error('Forbidden', 403);
        }
    }

    if ($entityType === 'shopping_order') {
        $check = $db->prepare(
            'SELECT s.id FROM shopping_orders s '
            . 'JOIN customers c ON c.id = s.customer_id '
            . 'WHERE s.id = ? AND c.account_id = ? AND s.deleted_at IS NULL AND c.deleted_at IS NULL'
        );
        $check->execute([$entityId, $customer['account_id']]);
        if (!$check->fetch()) {
            api_error('Forbidden', 403);
        }
    }
}

$publicRoot = realpath(APP_ROOT . '/public');
$fullPath = realpath(APP_ROOT . '/public/' . $attachment['file_path']);

if (!$publicRoot || !$fullPath || strpos($fullPath, $publicRoot) !== 0) {
    api_error('File not found', 404);
}

if (!is_file($fullPath)) {
    api_error('File not found', 404);
}

header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . $attachment['size_bytes']);
header('Content-Disposition: attachment; filename="' . basename($attachment['original_name']) . '"');

readfile($fullPath);
exit;
