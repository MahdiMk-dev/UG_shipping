<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = auth_require_user();
$input = api_read_input();

$scanId = api_int($input['scan_id'] ?? null);
$reportedWeight = api_float($input['reported_weight'] ?? null);

if (!$scanId) {
    api_error('scan_id is required', 422);
}
if ($reportedWeight === null || $reportedWeight <= 0) {
    api_error('reported_weight must be greater than zero', 422);
}

$role = $user['role'] ?? '';
if ($role === 'Warehouse') {
    api_error('Not allowed to report weights', 403);
}

$db = db();
$scanStmt = $db->prepare(
    'SELECT id, branch_id FROM branch_receiving_scans WHERE id = ? AND match_status = ? LIMIT 1'
);
$scanStmt->execute([$scanId, 'matched']);
$scan = $scanStmt->fetch();
if (!$scan) {
    api_error('Scan not found', 404);
}

$readOnly = is_read_only_role($user) && $role !== 'Warehouse';
if ($readOnly) {
    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    if ((int) $scan['branch_id'] !== (int) $branchId) {
        api_error('Cannot report for another branch', 403);
    }
}

$updateStmt = $db->prepare(
    'UPDATE branch_receiving_scans SET reported_weight = ?, reported_at = NOW(), reported_by_user_id = ? '
    . 'WHERE id = ?'
);
$updateStmt->execute([$reportedWeight, $user['id'] ?? null, $scanId]);

api_json(['ok' => true]);
