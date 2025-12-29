<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner']);
$input = api_read_input();

$name = api_string($input['name'] ?? null);
if (!$name) {
    api_error('name is required', 422);
}

$db = db();
$existingStmt = $db->prepare('SELECT id, deleted_at FROM goods_types WHERE name = ? LIMIT 1');
$existingStmt->execute([$name]);
$existing = $existingStmt->fetch();

if ($existing && empty($existing['deleted_at'])) {
    api_error('Goods type already exists', 409);
}

try {
    if ($existing && !empty($existing['deleted_at'])) {
        $db->prepare(
            'UPDATE goods_types SET deleted_at = NULL, updated_at = NOW(), updated_by_user_id = ? WHERE id = ?'
        )->execute([$user['id'] ?? null, (int) $existing['id']]);
        $goodsTypeId = (int) $existing['id'];
    } else {
        $insert = $db->prepare(
            'INSERT INTO goods_types (name, created_by_user_id) VALUES (?, ?)'
        );
        $insert->execute([$name, $user['id'] ?? null]);
        $goodsTypeId = (int) $db->lastInsertId();
    }
} catch (PDOException $e) {
    api_error('Failed to create goods type', 500);
}

api_json(['ok' => true, 'id' => $goodsTypeId]);
