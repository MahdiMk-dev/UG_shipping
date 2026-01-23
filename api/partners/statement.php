<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$filters = $_GET ?? [];

$partnerId = api_int($filters['partner_id'] ?? null);
$status = api_string($filters['status'] ?? 'posted') ?? 'posted';
$fromDate = api_string($filters['from'] ?? null);
$toDate = api_string($filters['to'] ?? null);
$limit = api_int($filters['limit'] ?? 50) ?? 50;
$offset = api_int($filters['offset'] ?? 0) ?? 0;

if (!$partnerId) {
    api_error('partner_id is required', 422);
}

$limit = max(1, min(200, $limit));
$offset = max(0, $offset);

if ($fromDate !== null && strtotime($fromDate) === false) {
    api_error('Invalid from date', 422);
}
if ($toDate !== null && strtotime($toDate) === false) {
    api_error('Invalid to date', 422);
}

$partnerStmt = db()->prepare('SELECT id, name, opening_balance, current_balance FROM partners WHERE id = ?');
$partnerStmt->execute([$partnerId]);
$partner = $partnerStmt->fetch();
if (!$partner) {
    api_error('Partner not found', 404);
}

$deltaForType = static function (string $txType, float $amount, int $partnerId, array $row): float {
    switch ($txType) {
        case 'WE_OWE_PARTNER':
        case 'ADJUST_PLUS':
            return $amount;
        case 'PARTNER_OWES_US':
        case 'ADJUST_MINUS':
            return -$amount;
        case 'WE_PAY_PARTNER':
            return -$amount;
        case 'PARTNER_PAYS_US':
            return $amount;
        case 'PARTNER_TO_PARTNER_TRANSFER':
            if ((int) ($row['from_partner_id'] ?? 0) === $partnerId) {
                return $amount;
            }
            if ((int) ($row['to_partner_id'] ?? 0) === $partnerId) {
                return -$amount;
            }
            return 0.0;
        default:
            return 0.0;
    }
};

$deltaForRow = static function (array $row, int $partnerId) use ($deltaForType): float {
    $txType = (string) ($row['tx_type'] ?? '');
    $amount = round(abs((float) ($row['amount'] ?? 0)), 2);
    if ($txType === 'REVERSAL') {
        $meta = $row['meta'] ?? null;
        $decoded = [];
        if ($meta) {
            $decoded = json_decode((string) $meta, true);
            if (!is_array($decoded)) {
                $decoded = [];
            }
        }
        $originalType = (string) ($decoded['original_type'] ?? '');
        if ($originalType === '') {
            return 0.0;
        }
        return -$deltaForType($originalType, $amount, $partnerId, $row);
    }
    return $deltaForType($txType, $amount, $partnerId, $row);
};

$whereBase = '(pt.partner_id = ? OR pt.from_partner_id = ? OR pt.to_partner_id = ?)';
$paramsBase = [$partnerId, $partnerId, $partnerId];

$where = [$whereBase];
$params = $paramsBase;

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

$startingBalance = (float) ($partner['opening_balance'] ?? 0);
if ($fromDate) {
    $beforeWhere = [$whereBase, 'pt.tx_date < ?'];
    $beforeParams = array_merge($paramsBase, [date('Y-m-d H:i:s', strtotime($fromDate))]);
    if ($status && $status !== 'all') {
        $beforeWhere[] = 'pt.status = ?';
        $beforeParams[] = $status;
    }
    $beforeSql = 'SELECT pt.* FROM partner_transactions pt WHERE ' . implode(' AND ', $beforeWhere) . ' '
        . 'ORDER BY pt.tx_date ASC, pt.id ASC';
    $beforeStmt = db()->prepare($beforeSql);
    foreach ($beforeParams as $index => $value) {
        $typeParam = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $beforeStmt->bindValue($index + 1, $value, $typeParam);
    }
    $beforeStmt->execute();
    $beforeRows = $beforeStmt->fetchAll();
    foreach ($beforeRows as $row) {
        $startingBalance += $deltaForRow($row, $partnerId);
    }
}

if ($offset > 0) {
    $offsetSql = 'SELECT pt.* FROM partner_transactions pt WHERE ' . implode(' AND ', $where) . ' '
        . 'ORDER BY pt.tx_date ASC, pt.id ASC LIMIT ?';
    $offsetParams = array_merge($params, [$offset]);
    $offsetStmt = db()->prepare($offsetSql);
    foreach ($offsetParams as $index => $value) {
        $typeParam = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $offsetStmt->bindValue($index + 1, $value, $typeParam);
    }
    $offsetStmt->execute();
    $offsetRows = $offsetStmt->fetchAll();
    foreach ($offsetRows as $row) {
        $startingBalance += $deltaForRow($row, $partnerId);
    }
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
    . 'ORDER BY pt.tx_date ASC, pt.id ASC '
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

$runningBalance = $startingBalance;
$data = [];

foreach ($rows as $row) {
    $delta = $deltaForRow($row, $partnerId);
    $runningBalance += $delta;

    $txType = (string) ($row['tx_type'] ?? '');
    $meta = $row['meta'] ?? null;
    $decoded = [];
    if ($meta) {
        $decoded = json_decode((string) $meta, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
    }
    $baseType = $txType === 'REVERSAL' ? (string) ($decoded['original_type'] ?? '') : $txType;
    $isCash = in_array($baseType, ['WE_PAY_PARTNER', 'PARTNER_PAYS_US'], true);

    $movement = $isCash ? null : $delta;
    $payment = $isCash ? $delta : null;

    $accountLabel = '';
    if (!empty($row['from_admin_account_id'])) {
        $accountLabel = (string) ($row['from_admin_account_name'] ?? '');
    }
    if (!empty($row['to_admin_account_id'])) {
        $accountLabel = (string) ($row['to_admin_account_name'] ?? '');
    }
    if ($accountLabel !== '') {
        $accountLabel = trim($accountLabel);
    }

    $displayType = $txType;
    if ($txType === 'REVERSAL' && $baseType !== '') {
        $displayType = 'REVERSAL (' . $baseType . ')';
    }

    $data[] = [
        'id' => (int) $row['id'],
        'tx_date' => $row['tx_date'],
        'tx_type' => $txType,
        'display_type' => $displayType,
        'movement' => $movement,
        'payment' => $payment,
        'admin_account' => $accountLabel,
        'description' => $row['description'],
        'status' => $row['status'],
        'currency_code' => $row['currency_code'],
        'running_balance' => $runningBalance,
    ];
}

api_json([
    'ok' => true,
    'partner' => $partner,
    'starting_balance' => $startingBalance,
    'data' => $data,
]);
