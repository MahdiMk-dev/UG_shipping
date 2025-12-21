<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Owner']);
$filters = $_GET ?? [];

$action = api_string($filters['action'] ?? null);
$entityType = api_string($filters['entity_type'] ?? null);
$entityId = api_int($filters['entity_id'] ?? null);
$userId = api_int($filters['user_id'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = [];
$params = [];

if ($action) {
    $where[] = 'a.action = ?';
    $params[] = $action;
}
if ($entityType) {
    $where[] = 'a.entity_type = ?';
    $params[] = $entityType;
}
if ($entityId) {
    $where[] = 'a.entity_id = ?';
    $params[] = $entityId;
}
if ($userId) {
    $where[] = 'a.user_id = ?';
    $params[] = $userId;
}

$sql = 'SELECT a.id, a.action, a.entity_type, a.entity_id, a.before_json, a.after_json, a.meta_json, '
    . 'a.ip_address, a.user_agent, a.created_at, u.name AS user_name, u.username '
    . 'FROM audit_logs a '
    . 'LEFT JOIN users u ON u.id = a.user_id ';

if (!empty($where)) {
    $sql .= 'WHERE ' . implode(' AND ', $where) . ' ';
}

$sql .= 'ORDER BY a.id DESC LIMIT ? OFFSET ?';

$params[] = $limit;
$params[] = $offset;

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $typeParam = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $typeParam);
}
$stmt->execute();
$rows = $stmt->fetchAll();

api_json(['ok' => true, 'data' => $rows]);
