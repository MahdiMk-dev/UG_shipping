<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../config/config.php';

api_require_method('GET');
$user = auth_require_user();

$shipmentId = api_int($_GET['shipment_id'] ?? ($_GET['id'] ?? null));
$shipmentNumber = api_string($_GET['shipment_number'] ?? null);
$download = api_bool($_GET['download'] ?? null, false);

if (!$shipmentId && !$shipmentNumber) {
    api_error('shipment_id or shipment_number is required', 422);
}

$db = db();
if ($shipmentId) {
    $stmt = $db->prepare(
        'SELECT s.*, c.name AS origin_country '
        . 'FROM shipments s '
        . 'LEFT JOIN countries c ON c.id = s.origin_country_id '
        . 'WHERE s.id = ? AND s.deleted_at IS NULL'
    );
    $stmt->execute([$shipmentId]);
} else {
    $stmt = $db->prepare(
        'SELECT s.*, c.name AS origin_country '
        . 'FROM shipments s '
        . 'LEFT JOIN countries c ON c.id = s.origin_country_id '
        . 'WHERE s.shipment_number = ? AND s.deleted_at IS NULL'
    );
    $stmt->execute([$shipmentNumber]);
}

$shipment = $stmt->fetch();
if (!$shipment) {
    api_error('Shipment not found', 404);
}

$role = $user['role'] ?? '';
$warehouseCountryId = null;
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    if ((int) ($shipment['origin_country_id'] ?? 0) !== (int) $warehouseCountryId) {
        api_error('Forbidden', 403);
    }
}
$readOnly = is_read_only_role($user) && $role !== 'Warehouse';
$branchId = $user['branch_id'] ?? null;
if ($readOnly) {
    if (!$branchId) {
        api_error('Branch scope required', 403);
    }
    $accessStmt = $db->prepare(
        'SELECT 1 FROM orders o '
        . 'INNER JOIN customers cu2 ON cu2.id = o.customer_id '
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL AND cu2.sub_branch_id = ? LIMIT 1'
    );
    $accessStmt->execute([$shipment['id'], $branchId]);
    if (!$accessStmt->fetch()) {
        api_error('Forbidden', 403);
    }
}

$collectionsStmt = $db->prepare(
    'SELECT id, name FROM collections WHERE shipment_id = ? ORDER BY name ASC'
);
$collectionsStmt->execute([$shipment['id']]);
$collections = $collectionsStmt->fetchAll();

if ($readOnly) {
    $ordersStmt = $db->prepare(
        'SELECT o.*, c.name AS customer_name '
        . 'FROM orders o '
        . 'LEFT JOIN customers c ON c.id = o.customer_id '
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL AND c.sub_branch_id = ? '
        . 'ORDER BY o.collection_id IS NULL, o.collection_id ASC, o.id ASC'
    );
    $ordersStmt->execute([$shipment['id'], $branchId]);
} else {
    $ordersStmt = $db->prepare(
        'SELECT o.*, c.name AS customer_name '
        . 'FROM orders o '
        . 'LEFT JOIN customers c ON c.id = o.customer_id '
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL '
        . 'ORDER BY o.collection_id IS NULL, o.collection_id ASC, o.id ASC'
    );
    $ordersStmt->execute([$shipment['id']]);
}
$orders = $ordersStmt->fetchAll();
$orderCount = count($orders);

$grouped = [];
foreach ($orders as $order) {
    $key = $order['collection_id'] ?? 0;
    if (!isset($grouped[$key])) {
        $grouped[$key] = [];
    }
    $grouped[$key][] = $order;
}

$collectionNames = [0 => 'Unassigned'];
foreach ($collections as $collection) {
    $collectionNames[(int) $collection['id']] = $collection['name'];
}

function pdf_text($value): string
{
    $text = (string) $value;
    $converted = function_exists('iconv')
        ? iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text)
        : $text;
    if ($converted === false) {
        $converted = preg_replace('/[^\x20-\x7E]/', '', $text);
    }
    return $converted;
}

function pdf_truncate(?string $value, int $limit): string
{
    $text = (string) $value;
    if (strlen($text) <= $limit) {
        return $text;
    }
    return substr($text, 0, max(0, $limit - 3)) . '...';
}

if ($download) {
    $company = config_get('company', []);
    $companyName = $company['name'] ?? 'United Group';
    $companyLocation = $company['location'] ?? '';
    $companyPhone = $company['phone'] ?? '';
    $companyLogoPath = $company['logo_path'] ?? '';

    require_once __DIR__ . '/../../app/lib/fpdf.php';

    $pdf = new FPDF();
    $pdf->SetMargins(12, 12, 12);
    $pdf->SetAutoPageBreak(true, 14);
    $pdf->AddPage();

    if ($companyLogoPath && is_file($companyLogoPath)) {
        $pdf->Image($companyLogoPath, 12, 12, 20);
    }

    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 7, pdf_text($companyName), 0, 1, 'R');
    $pdf->SetFont('Helvetica', '', 10);
    $companyLine = trim($companyLocation . ($companyPhone ? ' | ' . $companyPhone : ''));
    if ($companyLine !== '') {
        $pdf->Cell(0, 5, pdf_text($companyLine), 0, 1, 'R');
    }
    $pdf->Ln(6);

    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->Cell(0, 7, 'Packing List', 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 5, pdf_text('Shipment: ' . $shipment['shipment_number']), 0, 1, 'L');
    $pdf->Cell(0, 5, pdf_text('Origin: ' . ($shipment['origin_country'] ?? '-') . ' | Status: ' . $shipment['status']), 0, 1, 'L');
    $pdf->Cell(
        0,
        5,
        pdf_text(
            'Type: ' . $shipment['shipping_type'] . ' | Departure: ' . ($shipment['departure_date'] ?? '-')
            . ' | Arrival: ' . ($shipment['arrival_date'] ?? '-')
        ),
        0,
        1,
        'L'
    );
    $pdf->Cell(0, 5, pdf_text('Total orders: ' . $orderCount), 0, 1, 'L');
    $pdf->Ln(4);

    $headers = ['Tracking', 'Customer', 'Delivery', 'Unit', 'Weight Type', 'Dimensions / Weight'];
    $widths = [30, 50, 20, 14, 22, 52];

    foreach ($collectionNames as $collectionId => $collectionName) {
        $rows = $grouped[$collectionId] ?? [];
        if (empty($rows)) {
            continue;
        }
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(41, 57, 19);
        $pdf->Cell(0, 6, pdf_text($collectionName . ' (' . count($rows) . ' orders)'), 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(232, 241, 205);
        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], 6, $header, 1, 0, 'L', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Helvetica', '', 9);
        foreach ($rows as $order) {
            $dims = $order['weight_type'] === 'volumetric'
                ? ($order['w'] ?? '-') . 'x' . ($order['d'] ?? '-') . 'x' . ($order['h'] ?? '-')
                : ($order['actual_weight'] ?? '-');
            $cells = [
                pdf_truncate($order['tracking_number'] ?? '-', 18),
                pdf_truncate($order['customer_name'] ?? '-', 24),
                $order['delivery_type'] ?? '-',
                $order['unit_type'] ?? '-',
                $order['weight_type'] ?? '-',
                pdf_truncate((string) $dims, 18),
            ];
            foreach ($cells as $index => $cell) {
                $pdf->Cell($widths[$index], 6, pdf_text($cell), 1);
            }
            $pdf->Ln();
        }
        $pdf->Ln(3);
    }

    $fileName = 'packing_list_shipment_' . $shipment['shipment_number'] . '.pdf';
    $pdf->Output('D', $fileName);
    exit;
}
header('Content-Type: text/html; charset=utf-8');

$safe = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$company = config_get('company', []);
$companyName = $company['name'] ?? 'United Group';
$companyLocation = $company['location'] ?? '';
$companyPhone = $company['phone'] ?? '';
$companyLogo = $company['logo_public'] ?? (PUBLIC_URL . '/assets/img/ug-logo.svg');

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packing List - <?= $safe($shipment['shipment_number']) ?></title>
    <style>
        :root { --accent: #8eac26; --accent-dark: #4f5f1d; --surface: #ffffff; --line: #d7e3b5; }
        body { font-family: "Trebuchet MS", Arial, sans-serif; margin: 24px; color: #1f2420; background: #f7fbe8; }
        h1, h2 { margin: 0 0 8px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--accent); padding-bottom: 12px; }
        .company { text-align: right; font-size: 13px; color: #4b5046; }
        .company strong { display: block; font-size: 18px; color: #1f2420; letter-spacing: 0.6px; }
        .logo { width: 72px; height: 72px; object-fit: contain; border-radius: 14px; background: var(--surface); border: 1px solid var(--line); padding: 8px; }
        .title { margin-top: 18px; font-size: 22px; color: var(--accent-dark); }
        .meta { margin: 12px 0 18px; display: flex; flex-wrap: wrap; gap: 8px; font-size: 13px; color: #4b5046; }
        .meta span { display: inline-block; background: #eef5d6; padding: 6px 10px; border-radius: 10px; border: 1px solid var(--line); }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; background: var(--surface); }
        th, td { border: 1px solid var(--line); padding: 8px; font-size: 12.5px; text-align: left; }
        th { background: #e7f1c7; text-transform: uppercase; letter-spacing: 0.6px; color: var(--accent-dark); }
        .collection-title { margin-top: 22px; font-size: 16px; font-weight: 700; color: #364315; display: flex; align-items: center; gap: 8px; }
        .badge { font-size: 11px; padding: 4px 8px; border-radius: 999px; background: #edf4cf; border: 1px solid var(--line); color: var(--accent-dark); }
        .footer { margin-top: 24px; font-size: 12px; color: #6b7265; }
        @media print {
            body { margin: 10mm; background: #fff; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:12px;">
        <button onclick="window.print()">Print</button>
    </div>
    <div class="header">
        <img class="logo" src="<?= $safe($companyLogo) ?>" alt="United Group">
        <div class="company">
            <strong><?= $safe($companyName) ?></strong>
            <div><?= $safe($companyLocation) ?></div>
            <div><?= $safe($companyPhone) ?></div>
        </div>
    </div>
    <h1 class="title">Packing List</h1>
    <div class="meta">
        <span>Shipment: <?= $safe($shipment['shipment_number']) ?></span>
        <span>Origin: <?= $safe($shipment['origin_country'] ?? '-') ?></span>
        <span>Status: <?= $safe($shipment['status']) ?></span>
        <span>Type: <?= $safe($shipment['shipping_type']) ?></span>
        <span>Departure: <?= $safe($shipment['departure_date'] ?? '-') ?></span>
        <span>Arrival: <?= $safe($shipment['arrival_date'] ?? '-') ?></span>
        <span>Total orders: <?= $safe($orderCount) ?></span>
    </div>

    <?php foreach ($collectionNames as $collectionId => $collectionName): ?>
        <?php $rows = $grouped[$collectionId] ?? []; ?>
        <?php if (empty($rows)) continue; ?>
        <div class="collection-title">
            <?= $safe($collectionName) ?>
            <span class="badge"><?= $safe(count($rows)) ?> orders</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Tracking</th>
                    <th>Customer</th>
                    <th>Delivery</th>
                    <th>Unit</th>
                    <th>Weight Type</th>
                    <th>Dimensions / Weight</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $order): ?>
                    <?php
                        $dims = $order['weight_type'] === 'volumetric'
                            ? $safe(($order['w'] ?? '-') . ' x ' . ($order['d'] ?? '-') . ' x ' . ($order['h'] ?? '-'))
                            : $safe($order['actual_weight'] ?? '-');
                    ?>
                    <tr>
                        <td><?= $safe($order['tracking_number']) ?></td>
                        <td><?= $safe($order['customer_name'] ?? '-') ?></td>
                        <td><?= $safe($order['delivery_type']) ?></td>
                        <td><?= $safe($order['unit_type']) ?></td>
                        <td><?= $safe($order['weight_type']) ?></td>
                        <td><?= $dims ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <div class="footer">Generated on <?= $safe(date('Y-m-d H:i')) ?> by <?= $safe($user['name'] ?? 'User') ?></div>
</body>
</html>
