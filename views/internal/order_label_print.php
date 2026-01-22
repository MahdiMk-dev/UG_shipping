<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$orderId = (int) ($_GET['order_id'] ?? 0);
$trackingNumber = trim((string) ($_GET['tracking_number'] ?? ''));
if ($orderId <= 0 && $trackingNumber === '') {
    http_response_code(400);
    echo 'Tracking number or order id is required.';
    exit;
}

$params = [];
$sql = 'SELECT o.id, o.tracking_number, o.created_at, o.fulfillment_status, '
    . 's.shipment_number, c.name AS customer_name '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'WHERE o.deleted_at IS NULL ';

if ($trackingNumber !== '') {
    $sql .= 'AND o.tracking_number = ?';
    $params[] = $trackingNumber;
} else {
    $sql .= 'AND o.id = ?';
    $params[] = $orderId;
}

$stmt = db()->prepare($sql);
$stmt->execute($params);
$order = $stmt->fetch();

if (!$order && $orderId > 0) {
    http_response_code(404);
    echo 'Order not found.';
    exit;
}

$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$labelValue = (string) (($order['tracking_number'] ?? '') ?: $trackingNumber);
$shipmentNumber = (string) ($order['shipment_number'] ?? '');
$customerName = (string) ($order['customer_name'] ?? '');
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Order Label</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            :root {
                --label-width: 80mm;
                --label-height: 40mm;
                --ink: #111111;
                --muted: #6b7280;
            }
            * {
                box-sizing: border-box;
            }
            body {
                margin: 0;
                font-family: "Segoe UI", Arial, sans-serif;
                color: var(--ink);
                background: #ffffff;
            }
            .label {
                width: var(--label-width);
                min-height: var(--label-height);
                padding: 8mm 6mm 6mm;
                display: grid;
                gap: 6mm;
            }
            .label-header {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                gap: 8px;
            }
            .label-header h1 {
                font-size: 14pt;
                letter-spacing: 0.08em;
                margin: 0;
            }
            .label-meta {
                font-size: 8pt;
                color: var(--muted);
                text-align: right;
            }
            .barcode {
                display: grid;
                gap: 4mm;
            }
            .barcode-value {
                font-size: 12pt;
                font-weight: 600;
                text-align: center;
                letter-spacing: 0.12em;
            }
            .barcode svg {
                width: 100%;
                height: 18mm;
            }
            .label-footer {
                display: grid;
                gap: 2mm;
                font-size: 8pt;
                color: var(--muted);
            }
            .print-actions {
                display: flex;
                justify-content: center;
                padding: 12px 0 24px;
            }
            .print-actions button {
                border: 1px solid #d1d5db;
                background: #ffffff;
                padding: 8px 16px;
                border-radius: 999px;
                cursor: pointer;
                font-weight: 600;
            }
            @media print {
                @page {
                    size: var(--label-width) var(--label-height);
                    margin: 0;
                }
                body {
                    margin: 0;
                }
                .print-actions {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <main class="label">
            <div class="label-header">
                <h1>UG SHIPPING</h1>
                <div class="label-meta">
                    <?= $shipmentNumber !== '' ? 'Shipment: ' . $escape($shipmentNumber) : 'Shipment: --' ?>
                </div>
            </div>
            <section class="barcode">
                <div id="barcode" data-barcode-value="<?= $escape($labelValue) ?>"></div>
                <div class="barcode-value"><?= $escape($labelValue) ?></div>
            </section>
            <div class="label-footer">
                <div><?= $customerName !== '' ? 'Customer: ' . $escape($customerName) : 'Customer: --' ?></div>
                <div>Order #<?= $order ? $escape((string) $order['id']) : '--' ?></div>
            </div>
        </main>
        <div class="print-actions">
            <button type="button" onclick="window.print()">Print label</button>
        </div>
        <script>
            (function () {
                const container = document.getElementById('barcode');
                if (!container) {
                    return;
                }
                const rawValue = container.getAttribute('data-barcode-value') || '';
                const value = rawValue.trim().toUpperCase();
                if (!value) {
                    container.textContent = 'Tracking number missing.';
                    return;
                }

                const patterns = {
                    '0': 'nnnwwnwnn',
                    '1': 'wnnwnnnnw',
                    '2': 'nnwwnnnnw',
                    '3': 'wnwwnnnnn',
                    '4': 'nnnwwnnnw',
                    '5': 'wnnwwnnnn',
                    '6': 'nnwwwnnnn',
                    '7': 'nnnwnnwnw',
                    '8': 'wnnwnnwnn',
                    '9': 'nnwwnnwnn',
                    'A': 'wnnnnwnnw',
                    'B': 'nnwnnwnnw',
                    'C': 'wnwnnwnnn',
                    'D': 'nnnnwwnnw',
                    'E': 'wnnnwwnnn',
                    'F': 'nnwnwwnnn',
                    'G': 'nnnnnwwnw',
                    'H': 'wnnnnwwnn',
                    'I': 'nnwnnwwnn',
                    'J': 'nnnnwwwnn',
                    'K': 'wnnnnnnww',
                    'L': 'nnwnnnnww',
                    'M': 'wnwnnnnwn',
                    'N': 'nnnnwnnww',
                    'O': 'wnnnwnnwn',
                    'P': 'nnwnwnnwn',
                    'Q': 'nnnnnnwww',
                    'R': 'wnnnnnwwn',
                    'S': 'nnwnnnwwn',
                    'T': 'nnnnwnwwn',
                    'U': 'wwnnnnnnw',
                    'V': 'nwwnnnnnw',
                    'W': 'wwwnnnnnn',
                    'X': 'nwnnwnnnw',
                    'Y': 'wwnnwnnnn',
                    'Z': 'nwwnwnnnn',
                    '-': 'nwnnnnwnw',
                    '.': 'wwnnnnwnn',
                    ' ': 'nwwnnnwnn',
                    '$': 'nwnwnwnnn',
                    '/': 'nwnwnnnwn',
                    '+': 'nwnnnwnwn',
                    '%': 'nnnwnwnwn',
                    '*': 'nwnnwnwnn',
                };

                for (const ch of value) {
                    if (!patterns[ch]) {
                        container.textContent = 'Unsupported barcode character.';
                        return;
                    }
                }

                const narrow = 2;
                const wide = 5;
                const height = 60;
                const quiet = 6;
                const parts = ['*', ...value.split(''), '*'];
                let totalWidth = quiet * 2;
                const sequence = [];

                parts.forEach((ch, index) => {
                    const pattern = patterns[ch];
                    for (let i = 0; i < pattern.length; i += 1) {
                        const isWide = pattern[i] === 'w';
                        const segmentWidth = isWide ? wide : narrow;
                        sequence.push({ bar: i % 2 === 0, width: segmentWidth });
                        totalWidth += segmentWidth;
                    }
                    if (index < parts.length - 1) {
                        sequence.push({ bar: false, width: narrow });
                        totalWidth += narrow;
                    }
                });

                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', `0 0 ${totalWidth} ${height}`);
                svg.setAttribute('role', 'img');
                svg.setAttribute('aria-label', `Barcode ${value}`);

                let x = quiet;
                sequence.forEach((segment) => {
                    if (segment.bar) {
                        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                        rect.setAttribute('x', x);
                        rect.setAttribute('y', 0);
                        rect.setAttribute('width', segment.width);
                        rect.setAttribute('height', height);
                        rect.setAttribute('fill', '#111111');
                        svg.appendChild(rect);
                    }
                    x += segment.width;
                });

                container.appendChild(svg);
            })();
        </script>
    </body>
    </html>
