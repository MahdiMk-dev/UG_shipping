<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';

$user = internal_require_user();
if (!in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch'], true)) {
    http_response_code(403);
    echo 'Not authorized';
    exit;
}

$shipmentId = (int) ($_GET['shipment_id'] ?? 0);
$branchId = (int) ($_GET['sub_branch_id'] ?? 0);
if ($shipmentId <= 0 || $branchId <= 0) {
    http_response_code(422);
    echo 'shipment_id and sub_branch_id are required';
    exit;
}

$db = db();
$shipmentStmt = $db->prepare(
    'SELECT s.shipment_number, s.shipping_type, s.status, co.name AS origin_country, b.name AS branch_name '
    . 'FROM shipments s '
    . 'LEFT JOIN countries co ON co.id = s.origin_country_id '
    . 'LEFT JOIN branches b ON b.id = ? '
    . 'WHERE s.id = ? AND s.deleted_at IS NULL'
);
$shipmentStmt->execute([$branchId, $shipmentId]);
$shipment = $shipmentStmt->fetch();
if (!$shipment) {
    http_response_code(404);
    echo 'Shipment not found';
    exit;
}

$ordersStmt = $db->prepare(
    'SELECT o.id, o.tracking_number, o.weight_type, o.actual_weight, o.w, o.d, o.h, o.unit_type, '
    . 'o.qty, c.name AS customer_name, c.code AS customer_code '
    . 'FROM orders o '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'WHERE o.deleted_at IS NULL AND o.shipment_id = ? AND o.sub_branch_id = ? '
    . "AND o.fulfillment_status = 'pending_receipt' "
    . 'ORDER BY o.id ASC'
);
$ordersStmt->execute([$shipmentId, $branchId]);
$orders = $ordersStmt->fetchAll();

$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$weightLabel = static function (array $order, ?string $shippingType) {
    $weightType = (string) ($order['weight_type'] ?? 'actual');
    $weightUnit = $weightType === 'volumetric' ? 'cbm' : 'kg';
    $systemWeight = compute_qty(
        (string) ($order['unit_type'] ?? $weightUnit),
        $weightType,
        $order['actual_weight'] !== null ? (float) $order['actual_weight'] : null,
        $order['w'] !== null ? (float) $order['w'] : null,
        $order['d'] !== null ? (float) $order['d'] : null,
        $order['h'] !== null ? (float) $order['h'] : null,
        $shippingType
    );
    return number_format($systemWeight, 3) . ' ' . $weightUnit;
};

$issuedAt = date('Y-m-d H:i');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Distribution Sheet <?= $escape($shipment['shipment_number'] ?? '') ?></title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 24px;
            color: #111827;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        .meta h1 {
            margin: 0;
            font-size: 22px;
        }
        .meta p {
            margin: 4px 0;
            font-size: 13px;
            color: #4b5563;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            font-size: 12px;
            text-align: left;
        }
        th {
            background: #f3f4f6;
        }
        .signatures {
            margin-top: 32px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        .signature-box {
            border-top: 1px solid #111827;
            padding-top: 8px;
            font-size: 12px;
            text-align: center;
        }
        .empty-state {
            margin-top: 12px;
            color: #6b7280;
            font-size: 13px;
        }
        .print-actions {
            margin-top: 16px;
        }
        @media print {
            .print-actions {
                display: none;
            }
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="meta">
            <h1>Distribution Sheet</h1>
            <p>Shipment: <?= $escape($shipment['shipment_number'] ?? '-') ?></p>
            <p>Sub branch: <?= $escape($shipment['branch_name'] ?? $branchId) ?></p>
            <p>Origin: <?= $escape($shipment['origin_country'] ?? '-') ?></p>
        </div>
        <div class="meta">
            <p>Date: <?= $escape($issuedAt) ?></p>
            <p>Status: <?= $escape($shipment['status'] ?? '-') ?></p>
            <p>Mode: <?= $escape($shipment['shipping_type'] ?? '-') ?></p>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <p class="empty-state">No pending receipts found for this sub branch.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tracking</th>
                    <th>Customer</th>
                    <th>Weight</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $index => $order): ?>
                <tr>
                    <td><?= $escape($index + 1) ?></td>
                    <td><?= $escape($order['tracking_number'] ?? '-') ?></td>
                    <td><?= $escape(($order['customer_name'] ?? '') ?: ($order['customer_code'] ?? '-')) ?></td>
                    <td><?= $escape($weightLabel($order, $shipment['shipping_type'] ?? null)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="signatures">
        <div class="signature-box">Main branch signature</div>
        <div class="signature-box">Driver signature</div>
        <div class="signature-box">Sub branch signature</div>
    </div>

    <div class="print-actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
</body>
</html>
