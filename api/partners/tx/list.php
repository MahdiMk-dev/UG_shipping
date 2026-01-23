<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/api.php';
require_once __DIR__ . '/../../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$filters = $_GET ?? [];

$partnerId = api_int($filters['partner_id'] ?? null);
$fromPartnerId = api_int($filters['from_partner_id'] ?? null);
$toPartnerId = api_int($filters['to_partner_id'] ?? null);
$status = api_string($filters['status'] ?? null);
$fromDate = api_string($filters['from'] ?? null);
$toDate = api_string($filters['to'] ?? null);
$limit = api_int($filters['limit'] ?? 50) ?? 50;
$offset = api_int($filters['offset'] ?? 0) ?? 0;

$limit = max(1, min(200, $limit));
$offset = max(0, $offset);

if ($fromDate !== null && strtotime($fromDate) === false) {
    api_error('Invalid from date', 422);
}
if ($toDate !== null && strtotime($toDate) === false) {
    api_error('Invalid to date', 422);
}

$where = ['1=1'];
$params = [];

if ($partnerId) {
    $where[] = 'pt.partner_id = ?';
    $params[] = $partnerId;
}
if ($fromPartnerId) {
    $where[] = 'pt.from_partner_id = ?';
    $params[] = $fromPartnerId;
}
if ($toPartnerId) {
    $where[] = 'pt.to_partner_id = ?';
    $params[] = $toPartnerId;
}
if ($status && $status !== 'all') {
    $where[] = 'pt.status = ?';
    $params[] = $status;
}
if ($fromDate) {
    $where[] = 'pt.tx_date >= ?';
    $params[] = date('Y-m-d H:i:s', strtotime($fromDate));
}
if ($toDate) {
    $where[] = 'pt.tx_date <= ?';
    $params[] = date('Y-m-d H:i:s', strtotime($toDate));
}

$sql = 'SELECT pt.*, p.name AS partner_name, fp.name AS from_partner_name, tp.name AS to_partner_name, '
    . 'fa.name AS from_admin_account_name, fa.account_type AS from_admin_account_type, '
    . 'ta.name AS to_admin_account_name, ta.account_type AS to_admin_account_type '
    . 'FROM partner_transactions pt '
    . 'LEFT JOIN partners p ON p.id = pt.partner_id '
    . 'LEFT JOIN partners fp ON fp.id = pt.from_partner_id '
    . 'LEFT JOIN partners tp ON tp.id = pt.to_partner_id '
    . 'LEFT JOIN accounts fa ON fa.id = pt.from_admin_account_id '
    . 'LEFT JOIN accounts ta ON ta.id = pt.to_admin_account_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY pt.tx_date DESC, pt.id DESC '
    . 'LIMIT ? OFFSET ?';
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
