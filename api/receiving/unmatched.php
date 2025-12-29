<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';

api_require_method('GET');
$user = auth_require_user();
$filters = $_GET ?? [];

$branchId = api_int($filters['branch_id'] ?? null);
$shipmentId = api_int($filters['shipment_id'] ?? null);
$limit = api_int($filters['limit'] ?? 50, 50);
$offset = api_int($filters['offset'] ?? 0, 0);

$limit = max(1, min(200, $limit ?? 50));
$offset = max(0, $offset ?? 0);

$where = ['s.match_status = ?'];
$params = ['unmatched'];

if ($branchId) {
    $where[] = 's.branch_id = ?';
    $params[] = $branchId;
}
if ($shipmentId) {
    $where[] = 's.shipment_id = ?';
    $params[] = $shipmentId;
}

$sql = 'SELECT s.id, s.branch_id, b.name AS branch_name, s.shipment_id, sh.shipment_number, '
    . 's.tracking_number, s.scanned_at, s.note '
    . 'FROM branch_receiving_scans s '
    . 'LEFT JOIN branches b ON b.id = s.branch_id '
    . 'LEFT JOIN shipments sh ON sh.id = s.shipment_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY s.scanned_at DESC LIMIT ? OFFSET ?';

$params[] = $limit;
$params[] = $offset;

$stmt = db()->prepare($sql);
foreach ($params as $index => $value) {
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($index + 1, $value, $type);
}
$stmt->execute();
$rows = $stmt->fetchAll();

$trackingNumbers = [];
foreach ($rows as $row) {
    if (!empty($row['tracking_number'])) {
        $trackingNumbers[$row['tracking_number']] = true;
    }
}

$orderMap = [];
if (!empty($trackingNumbers)) {
    $numbers = array_keys($trackingNumbers);
    $placeholders = implode(',', array_fill(0, count($numbers), '?'));
    $orderStmt = db()->prepare(
        'SELECT o.id, o.tracking_number, o.shipment_id, o.sub_branch_id, o.fulfillment_status, '
        . 's.shipment_number, b.name AS sub_branch_name '
        . 'FROM orders o '
        . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
        . 'LEFT JOIN branches b ON b.id = o.sub_branch_id '
        . 'WHERE o.deleted_at IS NULL AND o.tracking_number IN (' . $placeholders . ')'
    );
    foreach ($numbers as $index => $value) {
        $orderStmt->bindValue($index + 1, $value, PDO::PARAM_STR);
    }
    $orderStmt->execute();
    $orders = $orderStmt->fetchAll();
    foreach ($orders as $order) {
        $orderMap[$order['tracking_number']][] = $order;
    }
}

foreach ($rows as &$row) {
    $row['match_type'] = 'not_found';
    $row['order_id'] = null;
    $row['order_status'] = null;
    $row['expected_branch_id'] = null;
    $row['expected_branch_name'] = null;
    $row['other_shipment_id'] = null;
    $row['other_shipment_number'] = null;

    $tracking = $row['tracking_number'] ?? '';
    if (!$tracking || empty($orderMap[$tracking])) {
        continue;
    }

    $sameShipment = null;
    $otherShipment = null;
    foreach ($orderMap[$tracking] as $order) {
        if ((int) $order['shipment_id'] === (int) ($row['shipment_id'] ?? 0)) {
            $sameShipment = $order;
            break;
        }
        if (!$otherShipment) {
            $otherShipment = $order;
        }
    }

    if ($sameShipment) {
        $row['order_id'] = (int) $sameShipment['id'];
        $row['order_status'] = $sameShipment['fulfillment_status'];
        $row['expected_branch_id'] = $sameShipment['sub_branch_id'];
        $row['expected_branch_name'] = $sameShipment['sub_branch_name'];
        if (!empty($sameShipment['sub_branch_id'])
            && (int) $sameShipment['sub_branch_id'] !== (int) ($row['branch_id'] ?? 0)
        ) {
            $row['match_type'] = 'wrong_branch';
        } else {
            $row['match_type'] = 'status_mismatch';
        }
    } elseif ($otherShipment) {
        $row['match_type'] = 'other_shipment';
        $row['other_shipment_id'] = (int) $otherShipment['shipment_id'];
        $row['other_shipment_number'] = $otherShipment['shipment_number'];
    }
}
unset($row);

api_json(['ok' => true, 'data' => $rows]);
