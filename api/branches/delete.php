<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$branchId = api_int($input['branch_id'] ?? ($input['id'] ?? null));
if (!$branchId) {
    api_error('branch_id is required', 422);
}

$stmt = db()->prepare(
    'UPDATE branches SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
    . 'WHERE id = ? AND deleted_at IS NULL'
);
$stmt->execute([$user['id'] ?? null, $branchId]);

if ($stmt->rowCount() === 0) {
    api_error('Branch not found', 404);
}

api_json(['ok' => true]);
