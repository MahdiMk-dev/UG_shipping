<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'shipments', 'Customer Orders', 'All orders for this customer in the shipment.');

$shipmentId = $_GET['shipment_id'] ?? '';
$customerId = $_GET['customer_id'] ?? '';
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true);
$backUrl = $shipmentId
    ? BASE_URL . '/views/internal/shipment_view?id=' . urlencode((string) $shipmentId)
    : BASE_URL . '/views/internal/shipments';
?>
<div data-shipment-customer-orders data-shipment-id="<?= htmlspecialchars((string) $shipmentId, ENT_QUOTES) ?>"
     data-customer-id="<?= htmlspecialchars((string) $customerId, ENT_QUOTES) ?>"
     data-can-edit="<?= $canEdit ? '1' : '0' ?>">
    <div class="app-toolbar">
        <a class="button ghost" href="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>">Back to shipment</a>
        <span class="toolbar-title">Customer orders</span>
    </div>
    <section class="panel detail-grid">
        <article class="detail-card">
            <h3>Shipment</h3>
            <div class="detail-list">
                <div><span>Shipment #</span><strong data-detail="shipment_number">--</strong></div>
                <div><span>Status</span><strong data-detail="status">--</strong></div>
                <div><span>Origin</span><strong data-detail="origin_country">--</strong></div>
            </div>
        </article>
        <article class="detail-card">
            <h3>Customer</h3>
            <div class="detail-list">
                <div><span>Customer</span><strong data-detail="customer_name">--</strong></div>
                <div><span>Orders</span><strong data-detail="order_count">--</strong></div>
                <div><span>Total Qty</span><strong data-detail="total_qty">--</strong></div>
                <div><span>Total</span><strong data-detail="total_price">--</strong></div>
            </div>
        </article>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Orders</h3>
                <p>Each order inside the selected shipment.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Delivery</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Fulfillment</th>
                        <?php if ($canEdit): ?>
                            <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody data-customer-orders-table>
                    <tr><td colspan="<?= $canEdit ? 6 : 5 ?>" class="muted">Loading orders...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-customer-orders-pagination>
            <button class="button ghost small" type="button" data-customer-orders-prev>Previous</button>
            <span class="page-label" data-customer-orders-page>Page 1</span>
            <button class="button ghost small" type="button" data-customer-orders-next>Next</button>
        </div>
    </section>

    <?php if ($canEdit): ?>
        <div class="drawer" data-order-edit-drawer>
            <div class="drawer-scrim" data-order-edit-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="edit-order-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="edit-order-title">Edit order</h3>
                        <p data-order-edit-subtitle>Update weight details, rate, and adjustments.</p>
                    </div>
                    <button class="icon-button" type="button" data-order-edit-close aria-label="Close edit panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-order-edit-form>
                    <input type="hidden" name="order_id" data-order-id-field>
                    <label>
                        <span>Weight type</span>
                        <select name="weight_type" data-order-weight-type required>
                            <option value="actual">Actual</option>
                            <option value="volumetric">Volumetric</option>
                        </select>
                    </label>
                    <label data-order-weight-actual>
                        <span>Actual weight</span>
                        <input type="number" step="0.001" name="actual_weight">
                    </label>
                    <label data-order-weight-dimension>
                        <span>Width (W)</span>
                        <input type="number" step="0.01" name="w">
                    </label>
                    <label data-order-weight-dimension>
                        <span>Depth (D)</span>
                        <input type="number" step="0.01" name="d">
                    </label>
                    <label data-order-weight-dimension>
                        <span>Height (H)</span>
                        <input type="number" step="0.01" name="h">
                    </label>
                    <label>
                        <span>Rate</span>
                        <input type="number" step="0.01" name="rate" required>
                    </label>
                    <div class="adjustments-block full">
                        <div class="panel-header">
                            <div>
                                <h3>Adjustments</h3>
                                <p>Additional costs or discounts for this order.</p>
                            </div>
                            <button class="button ghost small" type="button" data-adjustment-add>Add line</button>
                        </div>
                        <div class="adjustments-list" data-adjustments-list></div>
                    </div>
                    <button class="button primary small" type="submit">Save changes</button>
                </form>
                <div class="notice-stack" data-order-edit-status></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="notice-stack" data-customer-orders-status></div>
</div>
<?php
internal_page_end();
