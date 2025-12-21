<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/customer_auth.php';
require_once __DIR__ . '/../../config/config.php';

api_require_method('GET');

$invoiceId = api_int($_GET['invoice_id'] ?? ($_GET['id'] ?? null));
$invoiceNo = api_string($_GET['invoice_no'] ?? null);

if (!$invoiceId && !$invoiceNo) {
    api_error('invoice_id or invoice_no is required', 422);
}

$user = auth_user();
$customer = customer_auth_user();

if (!$user && !$customer) {
    api_error('Unauthorized', 401);
}

$db = db();
if ($invoiceId) {
    $stmt = $db->prepare(
        'SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address, '
        . 'b.name AS branch_name '
        . 'FROM invoices i '
        . 'LEFT JOIN customers c ON c.id = i.customer_id '
        . 'LEFT JOIN branches b ON b.id = i.branch_id '
        . 'WHERE i.id = ? AND i.deleted_at IS NULL'
    );
    $stmt->execute([$invoiceId]);
} else {
    $stmt = $db->prepare(
        'SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address, '
        . 'b.name AS branch_name '
        . 'FROM invoices i '
        . 'LEFT JOIN customers c ON c.id = i.customer_id '
        . 'LEFT JOIN branches b ON b.id = i.branch_id '
        . 'WHERE i.invoice_no = ? AND i.deleted_at IS NULL'
    );
    $stmt->execute([$invoiceNo]);
}

$invoice = $stmt->fetch();
if (!$invoice) {
    api_error('Invoice not found', 404);
}

if ($customer && (int) $invoice['customer_id'] !== (int) $customer['customer_id']) {
    api_error('Forbidden', 403);
}

$itemStmt = $db->prepare(
    'SELECT id, order_id, order_snapshot_json, line_total '
    . 'FROM invoice_items WHERE invoice_id = ?'
);
$itemStmt->execute([$invoice['id']]);

$items = [];
while ($row = $itemStmt->fetch()) {
    $row['order_snapshot'] = json_decode($row['order_snapshot_json'], true);
    unset($row['order_snapshot_json']);
    $items[] = $row;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$rowsHtml = '';
foreach ($items as $item) {
    $snapshot = $item['order_snapshot'] ?? [];
    $rowsHtml .= '<tr>'
        . '<td>' . h((string) ($snapshot['tracking_number'] ?? '')) . '</td>'
        . '<td>' . h((string) ($snapshot['shipment_number'] ?? '')) . '</td>'
        . '<td>' . h((string) ($snapshot['unit_type'] ?? '')) . '</td>'
        . '<td class="right">' . h(number_format((float) ($snapshot['qty'] ?? 0), 2)) . '</td>'
        . '<td class="right">' . h(number_format((float) ($snapshot['rate'] ?? 0), 2)) . '</td>'
        . '<td class="right">' . h(number_format((float) ($item['line_total'] ?? 0), 2)) . '</td>'
        . '</tr>';
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?= h($invoice['invoice_no']) ?></title>
    <style>
        :root {
            color-scheme: light;
            --ink: #1a1a1a;
            --muted: #5a5a5a;
            --line: #e3e3e3;
            --accent: #0f6b57;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Manrope", "Trebuchet MS", "Gill Sans", sans-serif;
            color: var(--ink);
            background: #f7f5ef;
            padding: 32px 16px 60px;
        }
        .sheet {
            max-width: 880px;
            margin: 0 auto;
            background: #fff;
            border-radius: 18px;
            border: 1px solid var(--line);
            padding: 28px 32px;
        }
        header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
        }
        h1 {
            margin: 0 0 6px;
            font-size: 28px;
        }
        .muted { color: var(--muted); }
        .meta {
            display: grid;
            gap: 6px;
            font-size: 14px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 20px;
            font-size: 14px;
        }
        .summary div {
            background: #f9f8f4;
            padding: 14px;
            border-radius: 12px;
            border: 1px solid var(--line);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
            font-size: 14px;
        }
        th, td {
            border-bottom: 1px solid var(--line);
            padding: 10px 8px;
            text-align: left;
        }
        th {
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 11px;
            color: var(--muted);
        }
        .right { text-align: right; }
        .totals {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
        }
        .totals div {
            min-width: 240px;
            background: #f9f8f4;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: none; border-radius: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <header>
            <div>
                <h1>Invoice <?= h($invoice['invoice_no']) ?></h1>
                <div class="muted">UG Shipping</div>
            </div>
            <div class="meta">
                <div><strong>Date:</strong> <?= h($invoice['issued_at']) ?></div>
                <div><strong>Branch:</strong> <?= h($invoice['branch_name']) ?></div>
                <div><strong>Status:</strong> <?= h($invoice['status']) ?></div>
            </div>
        </header>

        <section class="summary">
            <div>
                <div class="muted">Billed to</div>
                <div><strong><?= h($invoice['customer_name']) ?></strong></div>
                <div><?= h($invoice['customer_phone']) ?></div>
                <div><?= h($invoice['customer_address']) ?></div>
            </div>
            <div>
                <div class="muted">Notes</div>
                <div><?= h($invoice['note']) ?></div>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Tracking</th>
                    <th>Shipment</th>
                    <th>Unit</th>
                    <th class="right">Qty</th>
                    <th class="right">Rate</th>
                    <th class="right">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?= $rowsHtml ?>
            </tbody>
        </table>

        <div class="totals">
            <div>
                <div class="totals-row"><span>Total</span><span><?= h(number_format((float) $invoice['total'], 2)) ?></span></div>
                <div class="totals-row"><span>Paid</span><span><?= h(number_format((float) $invoice['paid_total'], 2)) ?></span></div>
                <div class="totals-row"><span>Due</span><span><?= h(number_format((float) $invoice['due_total'], 2)) ?></span></div>
            </div>
        </div>
    </div>
</body>
</html>
