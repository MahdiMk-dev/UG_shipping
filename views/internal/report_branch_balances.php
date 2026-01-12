<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../app/company.php';
require_once __DIR__ . '/../../app/db.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Owner', 'Sub Branch'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$branchId = (int) ($_GET['branch_id'] ?? 0);
if ($role === 'Sub Branch') {
    $branchId = (int) ($user['branch_id'] ?? 0);
    if ($branchId <= 0) {
        http_response_code(403);
        echo 'Branch scope required.';
        exit;
    }
}

$branchName = '';
if ($branchId > 0) {
    $branchStmt = db()->prepare('SELECT name FROM branches WHERE id = ? AND deleted_at IS NULL');
    $branchStmt->execute([$branchId]);
    $branchName = (string) ($branchStmt->fetchColumn() ?: '');
    if ($branchName === '') {
        http_response_code(404);
        echo 'Branch not found.';
        exit;
    }
}

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

if ($dateFrom === '') {
    $dateFrom = date('Y-m-01');
}
if ($dateTo === '') {
    $dateTo = date('Y-m-t');
}
if ($dateFrom !== '' && strtotime($dateFrom) === false) {
    http_response_code(400);
    echo 'Invalid date_from.';
    exit;
}
if ($dateTo !== '' && strtotime($dateTo) === false) {
    http_response_code(400);
    echo 'Invalid date_to.';
    exit;
}

$where = ['e.status = \'active\'', 'a.owner_type = \'branch\'', 'a.deleted_at IS NULL'];
$params = [];
if ($branchId > 0) {
    $where[] = 'a.owner_id = ?';
    $params[] = $branchId;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(COALESCE(e.entry_date, e.created_at)) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(COALESCE(e.entry_date, e.created_at)) <= ?';
    $params[] = $dateTo;
}

$summarySql = 'SELECT a.owner_id AS branch_id, b.name AS branch_name, '
    . 'SUM(CASE WHEN e.amount > 0 THEN e.amount ELSE 0 END) AS total_in, '
    . 'SUM(CASE WHEN e.amount < 0 THEN -e.amount ELSE 0 END) AS total_out, '
    . 'SUM(e.amount) AS net_total, '
    . 'COUNT(*) AS entry_count '
    . 'FROM account_entries e '
    . 'JOIN accounts a ON a.id = e.account_id '
    . 'LEFT JOIN branches b ON b.id = a.owner_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'GROUP BY a.owner_id '
    . 'ORDER BY b.name ASC';
$summaryStmt = db()->prepare($summarySql);
$summaryStmt->execute($params);
$summaryRows = $summaryStmt->fetchAll();

$customerParams = [];
$customerWhere = ['c.deleted_at IS NULL', 'c.is_system = 0', 'c.sub_branch_id IS NOT NULL'];
if ($branchId > 0) {
    $customerWhere[] = 'c.sub_branch_id = ?';
    $customerParams[] = $branchId;
}
$customerSummaryStmt = db()->prepare(
    'SELECT agg.sub_branch_id, '
    . 'SUM(CASE WHEN agg.balance < 0 THEN -agg.balance ELSE 0 END) AS credit_total, '
    . 'SUM(CASE WHEN agg.balance > 0 THEN agg.balance ELSE 0 END) AS due_total '
    . 'FROM ('
    . 'SELECT '
    . 'CASE WHEN c.account_id IS NULL THEN -c.id ELSE c.account_id END AS account_key, '
    . 'c.sub_branch_id, MAX(c.balance) AS balance '
    . 'FROM customers c '
    . 'WHERE ' . implode(' AND ', $customerWhere) . ' '
    . 'GROUP BY account_key, c.sub_branch_id'
    . ') agg '
    . 'GROUP BY agg.sub_branch_id'
);
$customerSummaryStmt->execute($customerParams);
$customerSummary = $customerSummaryStmt->fetchAll();
$customerSummaryMap = [];
foreach ($customerSummary as $row) {
    $customerSummaryMap[(int) ($row['sub_branch_id'] ?? 0)] = $row;
}

$detailsSql = 'SELECT e.id, a.owner_id AS branch_id, b.name AS branch_name, e.entry_type, e.amount, '
    . 'COALESCE(e.entry_date, e.created_at) AS entry_date, e.created_at, '
    . 'at.reference_type, at.reference_id, at.note, '
    . 'af.name AS from_account_name, aa.name AS to_account_name, '
    . 'btbl.from_branch_id, bf.name AS from_branch_name, '
    . 'btbl.to_branch_id, bt.name AS to_branch_name, '
    . 'tr.customer_id, c.name AS customer_name, c.code AS customer_code, '
    . 'ge.title AS expense_title '
    . 'FROM account_entries e '
    . 'JOIN accounts a ON a.id = e.account_id '
    . 'LEFT JOIN branches b ON b.id = a.owner_id '
    . 'LEFT JOIN account_transfers at ON at.id = e.transfer_id '
    . 'LEFT JOIN accounts af ON af.id = at.from_account_id '
    . 'LEFT JOIN accounts aa ON aa.id = at.to_account_id '
    . 'LEFT JOIN branch_transfers btbl ON btbl.id = at.reference_id AND at.reference_type = \'branch_transfer\' '
    . 'LEFT JOIN branches bf ON bf.id = btbl.from_branch_id '
    . 'LEFT JOIN branches bt ON bt.id = btbl.to_branch_id '
    . 'LEFT JOIN transactions tr ON tr.id = at.reference_id AND at.reference_type = \'transaction\' '
    . 'LEFT JOIN customers c ON c.id = tr.customer_id '
    . 'LEFT JOIN general_expenses ge ON ge.id = at.reference_id AND at.reference_type = \'general_expense\' '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY COALESCE(e.entry_date, e.created_at) DESC, e.id DESC';
$detailsStmt = db()->prepare($detailsSql);
$detailsStmt->execute($params);
$details = $detailsStmt->fetchAll();

$entryLabels = [
    'customer_payment' => 'Customer payment',
    'branch_transfer' => 'Branch transfer',
    'partner_transaction' => 'Partner transaction',
    'staff_expense' => 'Staff expense',
    'general_expense' => 'Company expense',
    'shipment_expense' => 'Shipment expense',
    'adjustment' => 'Adjustment',
    'other' => 'Other',
];

$company = company_settings();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$periodLabel = $dateFrom && $dateTo ? "{$dateFrom} to {$dateTo}" : 'All dates';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Branch Balance Report</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; padding: 32px; font-family: "Georgia", "Times New Roman", serif; color: #1b1b1b; background: #f8f7f5; }
        .sheet { max-width: 1040px; margin: 0 auto; border: 1px solid #ddd; padding: 32px; background: #fff; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #222; padding-bottom: 16px; }
        .brand { display: flex; gap: 16px; align-items: center; }
        .brand img { width: 72px; height: 72px; object-fit: contain; }
        .brand h1 { margin: 0; font-size: 22px; letter-spacing: 1px; text-transform: uppercase; }
        .company-meta { font-size: 12px; line-height: 1.5; color: #333; }
        .report-meta { text-align: right; font-size: 12px; color: #555; }
        .section { margin-top: 24px; }
        .section h3 { margin: 0 0 10px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 12px; }
        .summary-card { border: 1px solid #e1e1e1; padding: 12px; }
        .summary-card strong { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; border-bottom: 1px solid #ccc; padding: 8px 6px; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; color: #555; }
        td { padding: 8px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        .amount-positive { color: #0a7d33; }
        .amount-negative { color: #b21a1a; }
        .actions { margin-top: 24px; text-align: right; }
        .actions button { padding: 8px 14px; border: 1px solid #222; background: #222; color: #fff; cursor: pointer; }
        @media print {
            body { padding: 0; background: #fff; }
            .sheet { border: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <header class="header">
        <div class="brand">
            <?php if (!empty($company['logo_url'])): ?>
                <img src="<?= $escape($company['logo_url']) ?>" alt="<?= $escape($company['name']) ?>">
            <?php endif; ?>
            <div>
                <h1><?= $escape($company['name']) ?></h1>
                <div class="company-meta">
                    <?php if (!empty($company['address'])): ?><?= $escape($company['address']) ?><br><?php endif; ?>
                    <?php if (!empty($company['phone'])): ?><?= $escape($company['phone']) ?><br><?php endif; ?>
                    <?php if (!empty($company['email'])): ?><?= $escape($company['email']) ?><br><?php endif; ?>
                    <?php if (!empty($company['website'])): ?><?= $escape($company['website']) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="report-meta">
            <div><strong>Branch Balance Report</strong></div>
            <div>Period: <?= $escape($periodLabel) ?></div>
            <?php if ($branchId > 0): ?>
                <div>Branch: <?= $escape($branchName ?: '-') ?></div>
            <?php else: ?>
                <div>Company-wide</div>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($summaryRows)): ?>
        <section class="section">
            <div class="summary-grid">
                <?php
                $totalIn = 0.0;
                $totalOut = 0.0;
                $totalNet = 0.0;
                $totalCount = 0;
                $totalCustomerCredit = 0.0;
                foreach ($summaryRows as $row) {
                    $totalIn += (float) ($row['total_in'] ?? 0);
                    $totalOut += (float) ($row['total_out'] ?? 0);
                    $totalNet += (float) ($row['net_total'] ?? 0);
                    $totalCount += (int) ($row['entry_count'] ?? 0);
                    $branchIdValue = (int) ($row['branch_id'] ?? 0);
                    if ($branchIdValue && isset($customerSummaryMap[$branchIdValue])) {
                        $totalCustomerCredit += (float) ($customerSummaryMap[$branchIdValue]['credit_total'] ?? 0);
                    }
                }
                ?>
                <div class="summary-card">
                    <div>Total entries</div>
                    <strong><?= number_format($totalCount) ?></strong>
                </div>
                <div class="summary-card">
                    <div>Total in</div>
                    <strong><?= number_format($totalIn, 2) ?></strong>
                </div>
                <div class="summary-card">
                    <div>Total out</div>
                    <strong><?= number_format($totalOut, 2) ?></strong>
                </div>
                <div class="summary-card">
                    <div>Net change</div>
                    <strong><?= number_format($totalNet, 2) ?></strong>
                </div>
                <div class="summary-card">
                    <div>Customer credit</div>
                    <strong><?= number_format($totalCustomerCredit, 2) ?></strong>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="section">
        <h3>Summary by branch</h3>
        <?php if (empty($summaryRows)): ?>
            <p>No balance entries found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Entries</th>
                        <th>Total in</th>
                        <th>Total out</th>
                        <th>Net change</th>
                        <th>Customer credit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summaryRows as $row): ?>
                        <?php
                        $branchIdValue = (int) ($row['branch_id'] ?? 0);
                        $creditTotal = $branchIdValue && isset($customerSummaryMap[$branchIdValue])
                            ? (float) ($customerSummaryMap[$branchIdValue]['credit_total'] ?? 0)
                            : 0.0;
                        ?>
                        <tr>
                            <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                            <td><?= number_format((int) ($row['entry_count'] ?? 0)) ?></td>
                            <td><?= number_format((float) ($row['total_in'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['total_out'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['net_total'] ?? 0), 2) ?></td>
                            <td><?= number_format($creditTotal, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Balance entries</h3>
        <?php if (empty($details)): ?>
            <p>No balance entries found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $row): ?>
                        <?php
                        $dateSource = $row['entry_date'] ?? $row['created_at'] ?? null;
                        $dateLabel = $dateSource ? date('Y-m-d', strtotime($dateSource)) : '';
                        $entryType = $row['entry_type'] ?? '';
                        $typeLabel = $entryLabels[$entryType] ?? ($entryType ?: '-');
                        $amount = (float) ($row['amount'] ?? 0);
                        $amountClass = $amount >= 0 ? 'amount-positive' : 'amount-negative';
                        $reference = '-';
                        if (($row['reference_type'] ?? '') === 'branch_transfer') {
                            $fromLabel = $row['from_branch_name'] ?? $row['from_account_name'] ?? '-';
                            $toLabel = $row['to_branch_name'] ?? $row['to_account_name'] ?? '-';
                            $reference = trim($fromLabel . ' -> ' . $toLabel);
                        } elseif (($row['reference_type'] ?? '') === 'transaction') {
                            $customerLabel = $row['customer_name'] ?? '';
                            if (!empty($row['customer_code'])) {
                                $customerLabel = trim($customerLabel . ' (' . $row['customer_code'] . ')');
                            }
                            $reference = $customerLabel !== '' ? $customerLabel : 'Transaction #' . ($row['reference_id'] ?? '');
                        } elseif (in_array(($row['reference_type'] ?? ''), ['general_expense', 'shipment_expense'], true)) {
                            $reference = $row['expense_title']
                                ? $row['expense_title']
                                : (($row['reference_type'] ?? 'Expense') . ' #' . ($row['reference_id'] ?? ''));
                        } elseif (!empty($row['reference_id'])) {
                            $reference = ($row['reference_type'] ?? 'Ref') . ' #' . $row['reference_id'];
                        }
                        ?>
                        <tr>
                            <td><?= $escape($dateLabel ?: '-') ?></td>
                            <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                            <td><?= $escape($typeLabel) ?></td>
                            <td><?= $escape($reference) ?></td>
                            <td class="<?= $amountClass ?>"><?= number_format($amount, 2) ?></td>
                            <td><?= $escape($row['note'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
</div>
</body>
</html>
