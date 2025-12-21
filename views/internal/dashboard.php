<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../app/db.php';

$user = internal_require_user();
$db = db();
$role = $user['role'] ?? '';
$warehouseCountryId = null;
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        $warehouseCountryId = -1;
    }
}

if ($warehouseCountryId) {
    $activeStmt = $db->prepare(
        "SELECT COUNT(*) FROM shipments WHERE deleted_at IS NULL AND status IN ('active', 'departed', 'airport') "
        . 'AND origin_country_id = ?'
    );
    $activeStmt->execute([$warehouseCountryId]);
    $activeShipments = (int) $activeStmt->fetchColumn();
    $pendingStmt = $db->prepare(
        "SELECT COUNT(*) FROM orders o "
        . 'INNER JOIN shipments s ON s.id = o.shipment_id AND s.deleted_at IS NULL '
        . "WHERE o.deleted_at IS NULL AND o.fulfillment_status = 'pending_receipt' "
        . 'AND s.origin_country_id = ?'
    );
    $pendingStmt->execute([$warehouseCountryId]);
    $pendingReceipt = (int) $pendingStmt->fetchColumn();
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
    'SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE deleted_at IS NULL AND DATE(created_at) = CURRENT_DATE()'
)->fetchColumn();

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
