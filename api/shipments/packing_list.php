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
$customerId = api_int($_GET['customer_id'] ?? null);
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
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL AND o.sub_branch_id = ? LIMIT 1'
    );
    $accessStmt->execute([$shipment['id'], $branchId]);
    if (!$accessStmt->fetch()) {
        api_error('Forbidden', 403);
    }
}

$ordersWhere = 'o.shipment_id = ? AND o.deleted_at IS NULL';
$ordersParams = [$shipment['id']];
if ($readOnly) {
    $ordersWhere .= ' AND o.sub_branch_id = ?';
    $ordersParams[] = $branchId;
}
if ($customerId) {
    $ordersWhere .= ' AND o.customer_id = ?';
    $ordersParams[] = $customerId;
}

$ordersStmt = $db->prepare(
    'SELECT o.*, c.name AS customer_name, c.code AS customer_code, b.name AS sub_branch_name '
    . 'FROM orders o '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'LEFT JOIN branches b ON b.id = o.sub_branch_id '
    . 'WHERE ' . $ordersWhere . ' '
    . 'ORDER BY o.collection_id IS NULL, o.collection_id ASC, o.id ASC'
);
$ordersStmt->execute($ordersParams);
$orders = $ordersStmt->fetchAll();
if ($customerId && empty($orders)) {
    api_error('No orders found for this customer in the shipment', 404);
}
$orderCount = count($orders);

$collectionIds = array_values(array_unique(array_filter(array_map(
    static fn ($row) => (int) ($row['collection_id'] ?? 0),
    $orders
))));
$collections = [];
if (!empty($collectionIds)) {
    $placeholders = implode(',', array_fill(0, count($collectionIds), '?'));
    $collectionsStmt = $db->prepare(
        'SELECT id, name FROM collections WHERE id IN (' . $placeholders . ') ORDER BY name ASC'
    );
    $collectionsStmt->execute($collectionIds);
    $collections = $collectionsStmt->fetchAll();
}

$customerName = null;
$customerCode = null;
if ($customerId && !empty($orders)) {
    $customerName = $orders[0]['customer_name'] ?? null;
    $customerCode = $orders[0]['customer_code'] ?? null;
}
$customerInfo = null;
if ($customerId) {
    $customerStmt = $db->prepare(
        'SELECT c.name, c.code, c.phone, c.address, b.name AS sub_branch_name '
        . 'FROM customers c '
        . 'LEFT JOIN branches b ON b.id = c.sub_branch_id '
        . 'WHERE c.id = ? AND c.deleted_at IS NULL'
    );
    $customerStmt->execute([$customerId]);
    $customerInfo = $customerStmt->fetch() ?: null;
    if ($customerInfo) {
        $customerName = $customerInfo['name'] ?? $customerName;
        $customerCode = $customerInfo['code'] ?? $customerCode;
    }
}

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

$customerLabel = null;
$customerMeta = null;
$customerFileTag = null;
if ($customerId) {
    $customerLabel = $customerName ?: ('Customer #' . $customerId);
    $customerMeta = $customerLabel;
    if ($customerCode) {
        $customerMeta .= ' (' . $customerCode . ')';
    }
    $rawTag = $customerCode ?: (string) $customerId;
    $customerFileTag = preg_replace('/[^A-Za-z0-9_-]+/', '_', $rawTag);
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
    if ($customerInfo) {
        $pdf->Cell(0, 5, pdf_text('Customer: ' . ($customerInfo['name'] ?? '-')), 0, 1, 'L');
        if (!empty($customerInfo['code'])) {
            $pdf->Cell(0, 5, pdf_text('Code: ' . $customerInfo['code']), 0, 1, 'L');
        }
        if (!empty($customerInfo['phone'])) {
            $pdf->Cell(0, 5, pdf_text('Phone: ' . $customerInfo['phone']), 0, 1, 'L');
        }
        if (!empty($customerInfo['address'])) {
            $pdf->Cell(0, 5, pdf_text('Address: ' . $customerInfo['address']), 0, 1, 'L');
        }
        if (!empty($customerInfo['sub_branch_name'])) {
            $pdf->Cell(0, 5, pdf_text('Branch: ' . $customerInfo['sub_branch_name']), 0, 1, 'L');
        }
    } elseif ($customerMeta) {
        $pdf->Cell(0, 5, pdf_text('Customer: ' . $customerMeta), 0, 1, 'L');
    }
    $pdf->Ln(4);

    $headers = ['Tracking', 'Customer', 'Sub branch', 'Delivery', 'Unit', 'Weight Type', 'Dimensions / Weight'];
    $widths = [28, 42, 26, 16, 12, 18, 44];

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
                pdf_truncate($order['sub_branch_name'] ?? '-', 18),
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

    $fileName = 'packing_list_shipment_' . $shipment['shipment_number'];
    if ($customerFileTag) {
        $fileName .= '_customer_' . $customerFileTag;
    }
    $fileName .= '.pdf';
    $pdf->Output('D', $fileName);
    exit;
}
header('Content-Type: text/html; charset=utf-8');

$shipmentAttachments = [];
$orderAttachmentMap = [];
$collectionAttachmentMap = [];

if (!$readOnly) {
    $shipmentAttStmt = $db->prepare(
        'SELECT id, title, original_name, mime_type, created_at '
        . 'FROM attachments WHERE entity_type = \'shipment\' AND entity_id = ? AND deleted_at IS NULL '
        . 'ORDER BY id DESC'
    );
    $shipmentAttStmt->execute([$shipment['id']]);
    $shipmentAttachments = $shipmentAttStmt->fetchAll();
}

$orderIds = array_values(array_filter(array_map(static fn($row) => (int) ($row['id'] ?? 0), $orders)));
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $orderAttStmt = $db->prepare(
        'SELECT id, entity_id, title, original_name, mime_type, created_at '
        . 'FROM attachments WHERE entity_type = \'order\' AND deleted_at IS NULL '
        . 'AND entity_id IN (' . $placeholders . ') '
        . 'ORDER BY id DESC'
    );
    $orderAttStmt->execute($orderIds);
    $orderAttachments = $orderAttStmt->fetchAll();
    foreach ($orderAttachments as $attachment) {
        $entityId = (int) ($attachment['entity_id'] ?? 0);
        if (!$entityId) {
            continue;
        }
        if (!isset($orderAttachmentMap[$entityId])) {
            $orderAttachmentMap[$entityId] = [];
        }
        $orderAttachmentMap[$entityId][] = $attachment;
    }
}

if (!$readOnly) {
    $collectionIds = array_values(array_filter(array_map(static fn($row) => (int) ($row['id'] ?? 0), $collections)));
    if (!empty($collectionIds)) {
        $placeholders = implode(',', array_fill(0, count($collectionIds), '?'));
        $collectionAttStmt = $db->prepare(
            'SELECT id, entity_id, title, original_name, mime_type, created_at '
            . 'FROM attachments WHERE entity_type = \'collection\' AND deleted_at IS NULL '
            . 'AND entity_id IN (' . $placeholders . ') '
            . 'ORDER BY id DESC'
        );
        $collectionAttStmt->execute($collectionIds);
        $collectionAttachments = $collectionAttStmt->fetchAll();
        foreach ($collectionAttachments as $attachment) {
            $entityId = (int) ($attachment['entity_id'] ?? 0);
            if (!$entityId) {
                continue;
            }
            if (!isset($collectionAttachmentMap[$entityId])) {
                $collectionAttachmentMap[$entityId] = [];
            }
            $collectionAttachmentMap[$entityId][] = $attachment;
        }
    }
}

$safe = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$company = config_get('company', []);
$companyName = $company['name'] ?? 'United Group';
$companyLocation = $company['location'] ?? '';
$companyPhone = $company['phone'] ?? '';
$companyLogo = $company['logo_public'] ?? (PUBLIC_URL . '/assets/img/ug-logo.svg');
$titleSuffix = $customerMeta ? ' - ' . $customerMeta : '';

function render_media_list(array $attachments): string
{
    if (empty($attachments)) {
        return '<span class="muted">-</span>';
    }
    $items = [];
    foreach ($attachments as $attachment) {
        $title = $attachment['title'] ?? '';
        $original = $attachment['original_name'] ?? '';
        $label = $title !== '' ? $title : ($original !== '' ? $original : 'Attachment');
        $url = BASE_URL . '/api/attachments/download.php?id=' . ($attachment['id'] ?? '');
        $mime = (string) ($attachment['mime_type'] ?? '');
        $thumb = '';
        if (str_starts_with($mime, 'image/')) {
            $thumbUrl = $url . '&inline=1';
            $thumb =
                '<img class="media-thumb" src="'
                . htmlspecialchars($thumbUrl, ENT_QUOTES, 'UTF-8')
                . '" alt="">';
        }
        $items[] =
            '<li>'
            . $thumb
            . '<a href="'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" rel="noopener">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</a></li>';
    }
    return '<ul class="media-list">' . implode('', $items) . '</ul>';
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packing List - <?= $safe($shipment['shipment_number']) ?><?= $safe($titleSuffix) ?></title>
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
        .section-title { margin: 18px 0 10px; font-size: 15px; font-weight: 700; color: #364315; }
        .media-list { margin: 0; padding-left: 0; list-style: none; }
        .media-list li { margin: 0 0 6px; display: flex; gap: 6px; align-items: center; }
        .media-thumb { width: 42px; height: 42px; object-fit: cover; border-radius: 6px; border: 1px solid var(--line); }
        .muted { color: #6b7265; }
        .footer { margin-top: 24px; font-size: 12px; color: #6b7265; }
        .customer-card { margin: 10px 0 18px; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; }
        .customer-card h3 { margin: 0 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.7px; color: var(--accent-dark); }
        .customer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px 14px; font-size: 12.5px; }
        .customer-grid span { display: block; font-size: 11px; color: #6b7265; text-transform: uppercase; letter-spacing: 0.5px; }
        .customer-grid strong { display: block; font-size: 13px; color: #1f2420; }
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
        <?php if ($customerMeta): ?>
            <span>Customer: <?= $safe($customerMeta) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($customerInfo): ?>
        <div class="customer-card">
            <h3>Customer info</h3>
            <div class="customer-grid">
                <div><span>Name</span><strong><?= $safe($customerInfo['name'] ?? '-') ?></strong></div>
                <div><span>Code</span><strong><?= $safe($customerInfo['code'] ?? '-') ?></strong></div>
                <?php if (!empty($customerInfo['phone'])): ?>
                    <div><span>Phone</span><strong><?= $safe($customerInfo['phone']) ?></strong></div>
                <?php endif; ?>
                <?php if (!empty($customerInfo['address'])): ?>
                    <div><span>Address</span><strong><?= $safe($customerInfo['address']) ?></strong></div>
                <?php endif; ?>
                <?php if (!empty($customerInfo['sub_branch_name'])): ?>
                    <div><span>Branch</span><strong><?= $safe($customerInfo['sub_branch_name']) ?></strong></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($shipmentAttachments)): ?>
        <div class="section-title">Shipment media</div>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Preview</th>
                    <th>File</th>
                    <th>Type</th>
                    <th>Uploaded</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shipmentAttachments as $attachment): ?>
                    <?php
                        $title = $attachment['title'] ?? '';
                        $original = $attachment['original_name'] ?? '';
                        $label = $title !== '' ? $title : ($original !== '' ? $original : 'Attachment');
                        $url = BASE_URL . '/api/attachments/download.php?id=' . ($attachment['id'] ?? '');
                        $mime = (string) ($attachment['mime_type'] ?? '');
                        $previewUrl = str_starts_with($mime, 'image/') ? $url . '&inline=1' : '';
                    ?>
                    <tr>
                        <td><?= $safe($label) ?></td>
                        <td>
                            <?php if ($previewUrl !== ''): ?>
                                <img class="media-thumb" src="<?= $safe($previewUrl) ?>" alt="">
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="<?= $safe($url) ?>" target="_blank" rel="noopener">Download</a></td>
                        <td><?= $safe($attachment['mime_type'] ?? '-') ?></td>
                        <td><?= $safe($attachment['created_at'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

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
                    <th>Sub branch</th>
                    <th>Delivery</th>
                    <th>Unit</th>
                    <th>Weight Type</th>
                    <th>Dimensions / Weight</th>
                    <th>Media</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $order): ?>
                    <?php
                        $dims = $order['weight_type'] === 'volumetric'
                            ? $safe(($order['w'] ?? '-') . ' x ' . ($order['d'] ?? '-') . ' x ' . ($order['h'] ?? '-'))
                            : $safe($order['actual_weight'] ?? '-');
                        $orderMedia = $orderAttachmentMap[(int) ($order['id'] ?? 0)] ?? [];
                    ?>
                    <tr>
                        <td><?= $safe($order['tracking_number']) ?></td>
                        <td><?= $safe($order['customer_name'] ?? '-') ?></td>
                        <td><?= $safe($order['sub_branch_name'] ?? '-') ?></td>
                        <td><?= $safe($order['delivery_type']) ?></td>
                        <td><?= $safe($order['unit_type']) ?></td>
                        <td><?= $safe($order['weight_type']) ?></td>
                        <td><?= $dims ?></td>
                        <td><?= render_media_list($orderMedia) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php $collectionMedia = $collectionAttachmentMap[(int) $collectionId] ?? []; ?>
        <?php if (!empty($collectionMedia)): ?>
            <div class="section-title">Collection media</div>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Preview</th>
                        <th>File</th>
                        <th>Type</th>
                        <th>Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collectionMedia as $attachment): ?>
                        <?php
                            $title = $attachment['title'] ?? '';
                            $original = $attachment['original_name'] ?? '';
                            $label = $title !== '' ? $title : ($original !== '' ? $original : 'Attachment');
                            $url = BASE_URL . '/api/attachments/download.php?id=' . ($attachment['id'] ?? '');
                            $mime = (string) ($attachment['mime_type'] ?? '');
                            $previewUrl = str_starts_with($mime, 'image/') ? $url . '&inline=1' : '';
                        ?>
                        <tr>
                            <td><?= $safe($label) ?></td>
                            <td>
                                <?php if ($previewUrl !== ''): ?>
                                    <img class="media-thumb" src="<?= $safe($previewUrl) ?>" alt="">
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><a href="<?= $safe($url) ?>" target="_blank" rel="noopener">Download</a></td>
                            <td><?= $safe($attachment['mime_type'] ?? '-') ?></td>
                            <td><?= $safe($attachment['created_at'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="footer">Generated on <?= $safe(date('Y-m-d H:i')) ?> by <?= $safe($user['name'] ?? 'User') ?></div>
</body>
</html>
