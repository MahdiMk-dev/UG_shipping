<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../config/config.php';

api_require_method('GET');
$user = auth_require_user();

$collectionId = api_int($_GET['collection_id'] ?? ($_GET['id'] ?? null));
$download = api_bool($_GET['download'] ?? null, false);

if (!$collectionId) {
    api_error('collection_id is required', 422);
}

$db = db();
$collectionStmt = $db->prepare(
    'SELECT c.id, c.name, c.shipment_id, s.shipment_number, s.status, s.shipping_type, s.departure_date, '
    . 's.arrival_date, s.actual_departure_date, s.actual_arrival_date, '
    . 's.origin_country_id, co.name AS origin_country '
    . 'FROM collections c '
    . 'LEFT JOIN shipments s ON s.id = c.shipment_id '
    . 'LEFT JOIN countries co ON co.id = s.origin_country_id '
    . 'WHERE c.id = ?'
);
$collectionStmt->execute([$collectionId]);
$collection = $collectionStmt->fetch();

if (!$collection || empty($collection['shipment_id'])) {
    api_error('Collection not found', 404);
}

$role = $user['role'] ?? '';
$warehouseCountryId = null;
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    if ((int) ($collection['origin_country_id'] ?? 0) !== (int) $warehouseCountryId) {
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
        . 'WHERE o.collection_id = ? AND o.deleted_at IS NULL AND o.sub_branch_id = ? LIMIT 1'
    );
    $accessStmt->execute([$collectionId, $branchId]);
    if (!$accessStmt->fetch()) {
        api_error('Forbidden', 403);
    }
}

if ($readOnly) {
    $ordersStmt = $db->prepare(
        'SELECT o.*, cu.name AS customer_name, b.name AS sub_branch_name '
        . 'FROM orders o '
        . 'LEFT JOIN customers cu ON cu.id = o.customer_id '
        . 'LEFT JOIN branches b ON b.id = o.sub_branch_id '
        . 'WHERE o.collection_id = ? AND o.deleted_at IS NULL AND o.sub_branch_id = ? '
        . 'ORDER BY o.id ASC'
    );
    $ordersStmt->execute([$collectionId, $branchId]);
} else {
    $ordersStmt = $db->prepare(
        'SELECT o.*, cu.name AS customer_name, b.name AS sub_branch_name '
        . 'FROM orders o '
        . 'LEFT JOIN customers cu ON cu.id = o.customer_id '
        . 'LEFT JOIN branches b ON b.id = o.sub_branch_id '
        . 'WHERE o.collection_id = ? AND o.deleted_at IS NULL '
        . 'ORDER BY o.id ASC'
    );
    $ordersStmt->execute([$collectionId]);
}
$orders = $ordersStmt->fetchAll();
$orderCount = count($orders);

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
    $pdf->Cell(0, 5, pdf_text('Collection: ' . $collection['name']), 0, 1, 'L');
    $pdf->Cell(
        0,
        5,
        pdf_text(
            'Shipment: ' . ($collection['shipment_number'] ?? '-') . ' | Origin: ' . ($collection['origin_country'] ?? '-')
        ),
        0,
        1,
        'L'
    );
    $pdf->Cell(
        0,
        5,
        pdf_text(
            'Status: ' . ($collection['status'] ?? '-') . ' | Type: ' . ($collection['shipping_type'] ?? '-')
            . ' | Exp Depart: ' . ($collection['departure_date'] ?? '-')
            . ' | Act Depart: ' . ($collection['actual_departure_date'] ?? '-')
            . ' | Exp Arr: ' . ($collection['arrival_date'] ?? '-')
            . ' | Act Arr: ' . ($collection['actual_arrival_date'] ?? '-')
        ),
        0,
        1,
        'L'
    );
    $pdf->Cell(0, 5, pdf_text('Total orders: ' . $orderCount), 0, 1, 'L');
    $pdf->Ln(4);

    $headers = ['Tracking', 'Customer', 'Sub branch', 'Delivery', 'Unit', 'Weight Type', 'Dimensions / Weight'];
    $widths = [28, 42, 26, 16, 12, 18, 44];

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(232, 241, 205);
    foreach ($headers as $index => $header) {
        $pdf->Cell($widths[$index], 6, $header, 1, 0, 'L', true);
    }
    $pdf->Ln();

    $pdf->SetFont('Helvetica', '', 9);
    if (empty($orders)) {
        $pdf->Cell(array_sum($widths), 6, 'No orders in this collection.', 1, 1, 'L');
    } else {
        foreach ($orders as $order) {
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
    }

    $fileName = 'packing_list_collection_' . $collectionId . '.pdf';
    $pdf->Output('D', $fileName);
    exit;
}
header('Content-Type: text/html; charset=utf-8');

$shipmentAttachments = [];
$collectionAttachments = [];
$orderAttachmentMap = [];

$shipmentId = (int) ($collection['shipment_id'] ?? 0);
if (!$readOnly && $shipmentId > 0) {
    $shipmentAttStmt = $db->prepare(
        'SELECT id, title, original_name, mime_type, created_at '
        . 'FROM attachments WHERE entity_type = \'shipment\' AND entity_id = ? AND deleted_at IS NULL '
        . 'ORDER BY id DESC'
    );
    $shipmentAttStmt->execute([$shipmentId]);
    $shipmentAttachments = $shipmentAttStmt->fetchAll();
}

if (!$readOnly) {
    $collectionAttStmt = $db->prepare(
        'SELECT id, title, original_name, mime_type, created_at '
        . 'FROM attachments WHERE entity_type = \'collection\' AND entity_id = ? AND deleted_at IS NULL '
        . 'ORDER BY id DESC'
    );
    $collectionAttStmt->execute([$collectionId]);
    $collectionAttachments = $collectionAttStmt->fetchAll();
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

$safe = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$company = config_get('company', []);
$companyName = $company['name'] ?? 'United Group';
$companyLocation = $company['location'] ?? '';
$companyPhone = $company['phone'] ?? '';
$companyLogo = $company['logo_public'] ?? (PUBLIC_URL . '/assets/img/ug-logo.svg');

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
    <title>Packing List - <?= $safe($collection['name']) ?></title>
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
        .section-title { margin: 18px 0 10px; font-size: 15px; font-weight: 700; color: #364315; }
        .media-list { margin: 0; padding-left: 0; list-style: none; }
        .media-list li { margin: 0 0 6px; display: flex; gap: 6px; align-items: center; }
        .media-thumb { width: 42px; height: 42px; object-fit: cover; border-radius: 6px; border: 1px solid var(--line); }
        .muted { color: #6b7265; }
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
        <span>Collection: <?= $safe($collection['name']) ?></span>
        <span>Shipment: <?= $safe($collection['shipment_number'] ?? '-') ?></span>
        <span>Origin: <?= $safe($collection['origin_country'] ?? '-') ?></span>
        <span>Status: <?= $safe($collection['status'] ?? '-') ?></span>
        <span>Type: <?= $safe($collection['shipping_type'] ?? '-') ?></span>
        <span>Exp depart: <?= $safe($collection['departure_date'] ?? '-') ?></span>
        <span>Act depart: <?= $safe($collection['actual_departure_date'] ?? '-') ?></span>
        <span>Exp arrival: <?= $safe($collection['arrival_date'] ?? '-') ?></span>
        <span>Act arrival: <?= $safe($collection['actual_arrival_date'] ?? '-') ?></span>
        <span>Total orders: <?= $safe($orderCount) ?></span>
    </div>

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

    <?php if (!empty($collectionAttachments)): ?>
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
                <?php foreach ($collectionAttachments as $attachment): ?>
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
            <?php if (empty($orders)): ?>
                <tr><td colspan="8">No orders in this collection.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
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
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">Generated on <?= $safe(date('Y-m-d H:i')) ?> by <?= $safe($user['name'] ?? 'User') ?></div>
</body>
</html>
