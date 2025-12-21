<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function audit_log(
    array $user,
    string $action,
    string $entityType,
    ?int $entityId = null,
    ?array $before = null,
    ?array $after = null,
    array $meta = []
): void {
    $db = db();
    $stmt = $db->prepare(
        'INSERT INTO audit_logs '
        . '(user_id, action, entity_type, entity_id, before_json, after_json, meta_json, ip_address, user_agent) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $beforeJson = $before === null ? null : json_encode($before);
    $afterJson = $after === null ? null : json_encode($after);
    $metaJson = empty($meta) ? null : json_encode($meta);

    $stmt->execute([
        $user['id'] ?? null,
        $action,
        $entityType,
        $entityId,
        $beforeJson,
        $afterJson,
        $metaJson,
        $ip,
        $agent,
    ]);
}
