<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$goodsTypeId = api_int($input['id'] ?? ($input['goods_type_id'] ?? null));
if (!$goodsTypeId) {
    api_error('id is required', 422);
}

$db = db();
$stmt = $db->prepare('SELECT id, name FROM goods_types WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$goodsTypeId]);
$goodsType = $stmt->fetch();
if (!$goodsType) {
    api_error('Goods type not found', 404);
}

$usageStmt = $db->prepare(
    'SELECT 1 FROM shipments WHERE type_of_goods = ? AND deleted_at IS NULL LIMIT 1'
);
$usageStmt->execute([$goodsType['name']]);
if ($usageStmt->fetch()) {
    api_error('Goods type is used by shipments and cannot be deleted', 409);
}

try {
    $db->prepare(
        'UPDATE goods_types SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
    )->execute([$user['id'] ?? null, $goodsTypeId]);
} catch (PDOException $e) {
    api_error('Failed to delete goods type', 500);
}

api_json(['ok' => true]);
