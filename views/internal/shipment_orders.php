<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'orders', 'Shipment Orders', 'Orders grouped under the selected shipment.');
$role = $user['role'] ?? '';
$isWarehouse = $role === 'Warehouse';
$showIncome = !$isWarehouse;
$shipmentId = $_GET['shipment_id'] ?? ($_GET['id'] ?? null);
$shipmentNumber = $_GET['shipment_number'] ?? null;
$backUrl = BASE_URL . '/views/internal/orders';
$shipmentUrl = $shipmentId ? BASE_URL . '/views/internal/shipment_view?id=' . urlencode((string) $shipmentId) : '';
?>
<div data-shipment-orders-page
     data-shipment-id="<?= htmlspecialchars((string) $shipmentId, ENT_QUOTES) ?>"
     data-shipment-number="<?= htmlspecialchars((string) $shipmentNumber, ENT_QUOTES) ?>"
     data-show-income="<?= $showIncome ? '1' : '0' ?>">
    <div class="app-toolbar">
        <a class="button ghost" href="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>">Back to orders</a>
        <?php if ($shipmentUrl): ?>
            <a class="button ghost" href="<?= htmlspecialchars($shipmentUrl, ENT_QUOTES) ?>">Shipment details</a>
        <?php endif; ?>
        <span class="toolbar-title">Shipment orders</span>
    </div>

    <section class="panel detail-grid">
        <article class="detail-card">
            <h3>Shipment</h3>
            <div class="detail-list">
                <div><span>Shipment #</span><strong data-detail="shipment_number">--</strong></div>
                <div><span>Status</span><strong data-detail="status">--</strong></div>
                <div><span>Origin</span><strong data-detail="origin_country">--</strong></div>
                <div><span>Type</span><strong data-detail="shipping_type">--</strong></div>
            </div>
        </article>
        <article class="detail-card">
            <h3>Orders summary</h3>
            <div class="detail-list">
                <div><span>Orders</span><strong data-detail="order_count">--</strong></div>
                <div><span>Total Qty</span><strong data-detail="total_qty">--</strong></div>
                <?php if ($showIncome): ?>
                    <div><span>Total</span><strong data-detail="total_price">--</strong></div>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Orders</h3>
                <p>All orders tied to this shipment.</p>
            </div>
        </div>
        <form class="filter-bar" data-shipment-orders-filter>
            <select name="fulfillment_status" data-shipment-orders-status-filter>
                <option value="">All statuses</option>
                <option value="in_shipment">In shipment</option>
                <option value="main_branch">Main branch</option>
                <option value="pending_receipt">Pending receipt</option>
                <option value="received_subbranch">Received sub-branch</option>
                <option value="with_delivery">With delivery</option>
                <option value="picked_up">Picked up</option>
                <option value="closed">Closed</option>
                <option value="returned">Returned</option>
                <option value="canceled">Canceled</option>
            </select>
            <input type="text" name="q" data-shipment-orders-search placeholder="Customer code, name, or phone">
            <button class="button primary" type="submit">Apply</button>
            <button class="button ghost" type="button" data-shipment-orders-clear>Clear</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Customer</th>
                        <th>Sub branch</th>
                        <th>Qty</th>
                        <?php if ($showIncome): ?>
                            <th>Total</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody data-shipment-orders-table>
                    <tr><td colspan="<?= $showIncome ? 7 : 6 ?>" class="muted">Loading orders...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-shipment-orders-pagination>
            <button class="button ghost small" type="button" data-shipment-orders-prev>Previous</button>
            <span class="page-label" data-shipment-orders-page>Page 1</span>
            <button class="button ghost small" type="button" data-shipment-orders-next>Next</button>
        </div>
    </section>

    <div class="notice-stack" data-shipment-orders-status></div>
</div>
<?php
internal_page_end();
?>
