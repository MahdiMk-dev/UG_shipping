<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../app/db.php';

$user = internal_require_user();
$db = db();
$role = $user['role'] ?? '';
$isWarehouse = $role === 'Warehouse';
$warehouseCountryId = null;
if ($isWarehouse) {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        $warehouseCountryId = -1;
    }
}

$activeShipments = 0;
$pendingReceipt = 0;
$departedShipments = 0;
if ($warehouseCountryId) {
    $activeStmt = $db->prepare(
        "SELECT COUNT(*) FROM shipments WHERE deleted_at IS NULL AND status = 'active' "
        . 'AND origin_country_id = ?'
    );
    $activeStmt->execute([$warehouseCountryId]);
    $activeShipments = (int) $activeStmt->fetchColumn();
    $departedStmt = $db->prepare(
        "SELECT COUNT(*) FROM shipments WHERE deleted_at IS NULL AND status = 'departed' "
        . 'AND origin_country_id = ?'
    );
    $departedStmt->execute([$warehouseCountryId]);
    $departedShipments = (int) $departedStmt->fetchColumn();
} else {
    $activeShipments = (int) $db->query(
        "SELECT COUNT(*) FROM shipments WHERE deleted_at IS NULL AND status IN ('active', 'departed', 'airport')"
    )->fetchColumn();
    $pendingReceipt = (int) $db->query(
        "SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND fulfillment_status = 'pending_receipt'"
    )->fetchColumn();
}
$openInvoices = (int) $db->query(
    "SELECT COUNT(*) FROM invoices WHERE deleted_at IS NULL AND status IN ('open', 'partially_paid')"
)->fetchColumn();
$todaysCollections = (float) $db->query(
    'SELECT COALESCE(SUM(amount), 0) FROM transactions '
    . "WHERE deleted_at IS NULL AND status = 'active' AND DATE(created_at) = CURRENT_DATE()"
)->fetchColumn();

$isAdmin = in_array($role, ['Admin', 'Owner'], true);
$isMainBranch = $role === 'Main Branch';
$isSubBranch = $role === 'Sub Branch';
$branchId = $user['branch_id'] ?? null;

$chartPercent = static function (float $value, float $max): string {
    if ($max <= 0) {
        return '0%';
    }
    $percent = ($value / $max) * 100;
    return round($percent) . '%';
};

$monthAnchor = new DateTimeImmutable('first day of this month');
$monthKeys = [];
$monthLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $month = $monthAnchor->modify("-{$i} months");
    $key = $month->format('Y-m');
    $monthKeys[] = $key;
    $monthLabels[$key] = $month->format('M Y');
}
$rangeStart = $monthAnchor->modify('-5 months')->format('Y-m-01');
$rangeEnd = $monthAnchor->modify('+1 month')->format('Y-m-01');
$today = new DateTimeImmutable('today');

$shipmentsByStatus = [];
$shipmentsPerMonth = array_fill_keys($monthKeys, 0);
$ordersPerMonth = array_fill_keys($monthKeys, 0);
$shipmentsByCountry = [];
$pendingBranchDue = [];
$avgOrdersPerShipment = null;
$avgOrdersPerMonth = null;
$topCustomerByWeight = null;
$expectedArrivals = [];

if ($isAdmin) {
    $statusLabels = [
        'active' => 'Active',
        'departed' => 'Departed',
        'airport' => 'Airport',
        'arrived' => 'Arrived',
        'partially_distributed' => 'Partially distributed',
        'distributed' => 'Distributed',
    ];
    $statusTotals = array_fill_keys(array_keys($statusLabels), 0);
    $statusRows = $db->query(
        'SELECT status, COUNT(*) AS total FROM shipments WHERE deleted_at IS NULL GROUP BY status'
    )->fetchAll();
    foreach ($statusRows as $row) {
        $status = $row['status'] ?? '';
        if (array_key_exists($status, $statusTotals)) {
            $statusTotals[$status] = (int) $row['total'];
        }
    }
    foreach ($statusTotals as $status => $total) {
        $shipmentsByStatus[] = ['label' => $statusLabels[$status], 'total' => $total];
    }

    $shipmentsMonthStmt = $db->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total "
        . 'FROM shipments WHERE deleted_at IS NULL AND created_at >= ? AND created_at < ? '
        . 'GROUP BY ym'
    );
    $shipmentsMonthStmt->execute([$rangeStart, $rangeEnd]);
    foreach ($shipmentsMonthStmt->fetchAll() as $row) {
        $key = $row['ym'] ?? '';
        if (isset($shipmentsPerMonth[$key])) {
            $shipmentsPerMonth[$key] = (int) $row['total'];
        }
    }

    $countryStmt = $db->query(
        'SELECT c.name AS country_name, COUNT(*) AS total '
        . 'FROM shipments s JOIN countries c ON c.id = s.origin_country_id '
        . 'WHERE s.deleted_at IS NULL '
        . 'GROUP BY s.origin_country_id '
        . 'ORDER BY total DESC '
        . 'LIMIT 8'
    );
    $shipmentsByCountry = $countryStmt->fetchAll();

    $pendingStmt = $db->query(
        'SELECT b.name AS branch_name, SUM(acc.balance) AS due_total '
        . 'FROM ('
        . 'SELECT CASE WHEN c.account_id IS NULL THEN CONCAT(\'single-\', c.id) '
        . 'ELSE CONCAT(\'acct-\', c.account_id) END AS account_key, '
        . 'c.sub_branch_id, MAX(c.balance) AS balance '
        . 'FROM customers c '
        . 'WHERE c.deleted_at IS NULL AND c.is_system = 0 AND c.sub_branch_id IS NOT NULL '
        . 'GROUP BY account_key, c.sub_branch_id'
        . ') acc '
        . 'JOIN branches b ON b.id = acc.sub_branch_id '
        . 'WHERE acc.balance > 0 '
        . 'GROUP BY acc.sub_branch_id '
        . 'ORDER BY due_total DESC'
    );
    $pendingBranchDue = $pendingStmt->fetchAll();

    $avgStmt = $db->prepare(
        'SELECT AVG(order_count) FROM ('
        . 'SELECT COUNT(*) AS order_count FROM orders '
        . 'WHERE deleted_at IS NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) '
        . 'GROUP BY shipment_id'
        . ') totals'
    );
    $avgStmt->execute();
    $avgOrdersPerShipment = (float) $avgStmt->fetchColumn();
}

if ($isSubBranch && $branchId) {
    $monthStart = $monthAnchor->format('Y-m-01');
    $monthEnd = $monthAnchor->modify('+1 month')->format('Y-m-01');
    $topStmt = $db->prepare(
        'SELECT c.name, c.code, SUM(o.qty) AS total_qty, COUNT(*) AS order_count '
        . 'FROM orders o JOIN customers c ON c.id = o.customer_id '
        . 'WHERE o.deleted_at IS NULL AND o.sub_branch_id = ? '
        . 'AND o.created_at >= ? AND o.created_at < ? '
        . 'GROUP BY o.customer_id '
        . 'ORDER BY total_qty DESC '
        . 'LIMIT 1'
    );
    $topStmt->execute([$branchId, $monthStart, $monthEnd]);
    $topCustomerByWeight = $topStmt->fetch();

    $ordersStmt = $db->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total "
        . 'FROM orders WHERE deleted_at IS NULL AND sub_branch_id = ? '
        . 'AND created_at >= ? AND created_at < ? '
        . 'GROUP BY ym'
    );
    $ordersStmt->execute([$branchId, $rangeStart, $rangeEnd]);
    foreach ($ordersStmt->fetchAll() as $row) {
        $key = $row['ym'] ?? '';
        if (isset($ordersPerMonth[$key])) {
            $ordersPerMonth[$key] = (int) $row['total'];
        }
    }
    $avgOrdersPerMonth = array_sum($ordersPerMonth) / max(count($ordersPerMonth), 1);
}

if ($isWarehouse && $warehouseCountryId) {
    $shipmentsStmt = $db->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total "
        . 'FROM shipments WHERE deleted_at IS NULL AND origin_country_id = ? '
        . 'AND created_at >= ? AND created_at < ? '
        . 'GROUP BY ym'
    );
    $shipmentsStmt->execute([$warehouseCountryId, $rangeStart, $rangeEnd]);
    foreach ($shipmentsStmt->fetchAll() as $row) {
        $key = $row['ym'] ?? '';
        if (isset($shipmentsPerMonth[$key])) {
            $shipmentsPerMonth[$key] = (int) $row['total'];
        }
    }

    $ordersStmt = $db->prepare(
        "SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS ym, COUNT(*) AS total "
        . 'FROM orders o JOIN shipments s ON s.id = o.shipment_id '
        . 'WHERE o.deleted_at IS NULL AND s.deleted_at IS NULL AND s.origin_country_id = ? '
        . 'AND o.created_at >= ? AND o.created_at < ? '
        . 'GROUP BY ym'
    );
    $ordersStmt->execute([$warehouseCountryId, $rangeStart, $rangeEnd]);
    foreach ($ordersStmt->fetchAll() as $row) {
        $key = $row['ym'] ?? '';
        if (isset($ordersPerMonth[$key])) {
            $ordersPerMonth[$key] = (int) $row['total'];
        }
    }
    $avgOrdersPerMonth = array_sum($ordersPerMonth) / max(count($ordersPerMonth), 1);
}

if ($isMainBranch) {
    $ordersStmt = $db->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total "
        . 'FROM orders WHERE deleted_at IS NULL AND created_at >= ? AND created_at < ? '
        . 'GROUP BY ym'
    );
    $ordersStmt->execute([$rangeStart, $rangeEnd]);
    foreach ($ordersStmt->fetchAll() as $row) {
        $key = $row['ym'] ?? '';
        if (isset($ordersPerMonth[$key])) {
            $ordersPerMonth[$key] = (int) $row['total'];
        }
    }

    $arrivalStmt = $db->prepare(
        'SELECT shipment_number, origin_country_id, arrival_date, status '
        . 'FROM shipments WHERE deleted_at IS NULL '
        . "AND status IN ('active','departed','airport') "
        . 'AND arrival_date IS NOT NULL AND arrival_date >= CURDATE() '
        . 'ORDER BY arrival_date ASC '
        . 'LIMIT 8'
    );
    $arrivalStmt->execute();
    $expectedArrivals = $arrivalStmt->fetchAll();
}

if ($warehouseCountryId) {
    $shipmentsStmt = $db->prepare(
        'SELECT s.shipment_number, c.name AS origin, s.status, s.departure_date, s.arrival_date '
        . 'FROM shipments s '
        . 'JOIN countries c ON c.id = s.origin_country_id '
        . 'WHERE s.deleted_at IS NULL AND s.origin_country_id = ? '
        . 'ORDER BY s.created_at DESC '
        . 'LIMIT 6'
    );
    $shipmentsStmt->execute([$warehouseCountryId]);
    $recentShipments = $shipmentsStmt->fetchAll();
} else {
    $shipmentsStmt = $db->query(
        'SELECT s.shipment_number, c.name AS origin, s.status, s.departure_date, s.arrival_date '
        . 'FROM shipments s '
        . 'JOIN countries c ON c.id = s.origin_country_id '
        . 'WHERE s.deleted_at IS NULL '
        . 'ORDER BY s.created_at DESC '
        . 'LIMIT 6'
    );
    $recentShipments = $shipmentsStmt->fetchAll();
}

internal_page_start($user, 'dashboard', 'Dashboard');
?>
<?php if ($isWarehouse): ?>
    <section class="stats-grid">
        <article class="stat-card">
            <h3>Active shipments</h3>
            <p class="stat-value"><?= number_format($activeShipments) ?></p>
        </article>
        <article class="stat-card">
            <h3>Departed shipments</h3>
            <p class="stat-value"><?= number_format($departedShipments) ?></p>
        </article>
    </section>
<?php else: ?>
    <section class="stats-grid">
        <article class="stat-card">
            <h3>Active shipments</h3>
            <p class="stat-value"><?= number_format($activeShipments) ?></p>
        </article>
        <article class="stat-card">
            <h3>Pending receipt</h3>
            <p class="stat-value"><?= number_format($pendingReceipt) ?></p>
        </article>
        <article class="stat-card">
            <h3>Open invoices</h3>
            <p class="stat-value"><?= number_format($openInvoices) ?></p>
        </article>
        <article class="stat-card">
            <h3>Today\'s collections</h3>
            <p class="stat-value"><?= number_format($todaysCollections, 2) ?></p>
        </article>
    </section>
<?php endif; ?>

<?php if ($isAdmin): ?>
    <section class="panel-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Pending customer balances by branch</h3>
                    <p>Outstanding customer balances (positive only).</p>
                </div>
            </div>
            <?php if (empty($pendingBranchDue)): ?>
                <p class="muted">No pending balances yet.</p>
            <?php else: ?>
                <?php $maxDue = max(array_map(static fn($row) => (float) $row['due_total'], $pendingBranchDue)); ?>
                <div class="chart-list">
                    <?php foreach ($pendingBranchDue as $row): ?>
                        <div class="chart-row">
                            <span class="chart-label"><?= htmlspecialchars($row['branch_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="chart-bar" style="--bar: <?= $chartPercent((float) $row['due_total'], $maxDue) ?>">
                                <span></span>
                            </div>
                            <span class="chart-value"><?= number_format((float) $row['due_total'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Shipments by status</h3>
                    <p>Current distribution across lifecycle states.</p>
                </div>
            </div>
            <?php $maxStatus = $shipmentsByStatus ? max(array_column($shipmentsByStatus, 'total')) : 0; ?>
            <div class="chart-list">
                <?php foreach ($shipmentsByStatus as $row): ?>
                    <div class="chart-row">
                        <span class="chart-label"><?= htmlspecialchars($row['label'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="chart-bar" style="--bar: <?= $chartPercent((float) $row['total'], (float) $maxStatus) ?>">
                            <span></span>
                        </div>
                        <span class="chart-value"><?= number_format((int) $row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Total shipments per month</h3>
                    <p>Last 6 months.</p>
                </div>
            </div>
            <?php $maxShipmentsMonth = $shipmentsPerMonth ? max($shipmentsPerMonth) : 0; ?>
            <div class="chart-list">
                <?php foreach ($shipmentsPerMonth as $monthKey => $total): ?>
                    <div class="chart-row">
                        <span class="chart-label"><?= htmlspecialchars($monthLabels[$monthKey], ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="chart-bar" style="--bar: <?= $chartPercent((float) $total, (float) $maxShipmentsMonth) ?>">
                            <span></span>
                        </div>
                        <span class="chart-value"><?= number_format($total) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </section>

    <section class="panel-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Shipments by origin country</h3>
                    <p>Top origins by shipment count.</p>
                </div>
            </div>
            <?php if (empty($shipmentsByCountry)): ?>
                <p class="muted">No shipments recorded yet.</p>
            <?php else: ?>
                <?php $maxCountry = max(array_map(static fn($row) => (int) $row['total'], $shipmentsByCountry)); ?>
                <div class="chart-list">
                    <?php foreach ($shipmentsByCountry as $row): ?>
                        <div class="chart-row">
                            <span class="chart-label"><?= htmlspecialchars($row['country_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="chart-bar" style="--bar: <?= $chartPercent((float) $row['total'], (float) $maxCountry) ?>">
                                <span></span>
                            </div>
                            <span class="chart-value"><?= number_format((int) $row['total']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Operational averages</h3>
                    <p>Based on the last 90 days.</p>
                </div>
            </div>
            <div class="detail-list">
                <div>
                    <span>Avg orders per shipment</span>
                    <strong><?= number_format((float) $avgOrdersPerShipment, 2) ?></strong>
                </div>
            </div>
        </section>
    </section>
<?php elseif ($isSubBranch && $branchId): ?>
    <section class="panel-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Top customer by weight (this month)</h3>
                    <p>Based on total order quantity.</p>
                </div>
            </div>
            <?php if (!$topCustomerByWeight): ?>
                <p class="muted">No orders recorded this month.</p>
            <?php else: ?>
                <div class="detail-list">
                    <div><span>Customer</span><strong><?= htmlspecialchars($topCustomerByWeight['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div><span>Code</span><strong><?= htmlspecialchars($topCustomerByWeight['code'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div><span>Total qty</span><strong><?= number_format((float) ($topCustomerByWeight['total_qty'] ?? 0), 2) ?></strong></div>
                    <div><span>Orders</span><strong><?= number_format((int) ($topCustomerByWeight['order_count'] ?? 0)) ?></strong></div>
                </div>
            <?php endif; ?>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Orders per month</h3>
                    <p>Last 6 months.</p>
                </div>
            </div>
            <?php $maxOrdersMonth = $ordersPerMonth ? max($ordersPerMonth) : 0; ?>
            <div class="chart-list">
                <?php foreach ($ordersPerMonth as $monthKey => $total): ?>
                    <div class="chart-row">
                        <span class="chart-label"><?= htmlspecialchars($monthLabels[$monthKey], ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="chart-bar" style="--bar: <?= $chartPercent((float) $total, (float) $maxOrdersMonth) ?>">
                            <span></span>
                        </div>
                        <span class="chart-value"><?= number_format($total) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Average orders per month</h3>
                    <p>Based on the last 6 months.</p>
                </div>
            </div>
            <div class="detail-list">
                <div>
                    <span>Average</span>
                    <strong><?= number_format((float) $avgOrdersPerMonth, 1) ?></strong>
                </div>
            </div>
        </section>
    </section>
<?php elseif ($isWarehouse && $warehouseCountryId): ?>
    <section class="panel-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Shipments per month</h3>
                    <p>Warehouse origin country only.</p>
                </div>
            </div>
            <?php $maxShipmentsMonth = $shipmentsPerMonth ? max($shipmentsPerMonth) : 0; ?>
            <div class="chart-list">
                <?php foreach ($shipmentsPerMonth as $monthKey => $total): ?>
                    <div class="chart-row">
                        <span class="chart-label"><?= htmlspecialchars($monthLabels[$monthKey], ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="chart-bar" style="--bar: <?= $chartPercent((float) $total, (float) $maxShipmentsMonth) ?>">
                            <span></span>
                        </div>
                        <span class="chart-value"><?= number_format($total) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Average orders per month</h3>
                    <p>Warehouse origin country only.</p>
                </div>
            </div>
            <div class="detail-list">
                <div>
                    <span>Average</span>
                    <strong><?= number_format((float) $avgOrdersPerMonth, 1) ?></strong>
                </div>
            </div>
        </section>
    </section>
<?php elseif ($isMainBranch): ?>
    <section class="panel-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Orders per month</h3>
                    <p>Distribution over the last 6 months.</p>
                </div>
            </div>
            <?php $maxOrdersMonth = $ordersPerMonth ? max($ordersPerMonth) : 0; ?>
            <div class="chart-list">
                <?php foreach ($ordersPerMonth as $monthKey => $total): ?>
                    <div class="chart-row">
                        <span class="chart-label"><?= htmlspecialchars($monthLabels[$monthKey], ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="chart-bar" style="--bar: <?= $chartPercent((float) $total, (float) $maxOrdersMonth) ?>">
                            <span></span>
                        </div>
                        <span class="chart-value"><?= number_format($total) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Expected arrivals</h3>
                    <p>Upcoming shipments with scheduled arrival dates.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Shipment</th>
                            <th>Arrival date</th>
                            <th>Status</th>
                            <th>Days left</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expectedArrivals)) : ?>
                            <tr>
                                <td colspan="4" class="muted">No upcoming arrivals.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($expectedArrivals as $shipment) : ?>
                                <?php
                                $arrival = $shipment['arrival_date'] ? new DateTimeImmutable((string) $shipment['arrival_date']) : null;
                                $daysLeft = $arrival ? (int) $today->diff($arrival)->format('%r%a') : null;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($shipment['shipment_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($shipment['arrival_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($shipment['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $daysLeft !== null ? number_format($daysLeft) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Recent shipments</h3>
        </div>
        <a class="button ghost small" href="<?= BASE_URL ?>/views/internal/shipments">View all</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Shipment</th>
                    <th>Origin</th>
                    <th>Status</th>
                    <th>Departure</th>
                    <th>Arrival</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentShipments)) : ?>
                    <tr>
                        <td colspan="5" class="muted">No shipments found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($recentShipments as $shipment) : ?>
                        <tr>
                            <td><?= htmlspecialchars($shipment['shipment_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($shipment['origin'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($shipment['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($shipment['departure_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($shipment['arrival_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
internal_page_end();
