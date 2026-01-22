<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'shipments', 'Customer Orders', 'All orders for this customer in the shipment.');

$shipmentId = $_GET['shipment_id'] ?? '';
$customerId = $_GET['customer_id'] ?? '';
$role = $user['role'] ?? '';
$canEdit = in_array($role, ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true);
$canPrintLabel = in_array($role, ['Admin', 'Warehouse'], true);
$isWarehouse = $role === 'Warehouse';
$showIncome = !$isWarehouse;
$backUrl = $shipmentId
    ? BASE_URL . '/views/internal/shipment_view?id=' . urlencode((string) $shipmentId)
    : BASE_URL . '/views/internal/shipments';
$packingListUrl = ($shipmentId && $customerId)
    ? BASE_URL . '/api/shipments/packing_list.php?shipment_id=' . urlencode((string) $shipmentId)
        . '&customer_id=' . urlencode((string) $customerId)
    : '';
?>
<div data-shipment-customer-orders data-shipment-id="<?= htmlspecialchars((string) $shipmentId, ENT_QUOTES) ?>"
     data-customer-id="<?= htmlspecialchars((string) $customerId, ENT_QUOTES) ?>"
     data-can-edit="<?= $canEdit ? '1' : '0' ?>"
     data-can-print-label="<?= $canPrintLabel ? '1' : '0' ?>"
     data-show-income="<?= $showIncome ? '1' : '0' ?>">
    <div class="app-toolbar">
        <a class="button ghost" href="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>">Back to shipment</a>
        <span class="toolbar-title">Customer orders</span>
        <?php if ($packingListUrl !== ''): ?>
            <a class="button ghost" href="<?= htmlspecialchars($packingListUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener">Packing list</a>
        <?php endif; ?>
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
                        <?php if ($showIncome): ?>
                            <th>Total</th>
                        <?php endif; ?>
                        <th>Fulfillment</th>
                        <?php if ($canEdit): ?>
                            <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody data-customer-orders-table>
                    <tr><td colspan="<?= ($showIncome ? 5 : 4) + ($canEdit ? 1 : 0) ?>" class="muted">Loading orders...</td></tr>
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
                        <p data-order-edit-subtitle>Update weight details, rates, and adjustments.</p>
                    </div>
                    <button class="icon-button" type="button" data-order-edit-close aria-label="Close edit panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-order-edit-form>
                    <input type="hidden" name="order_id" data-order-id-field>
                    <label class="order-edit-package">
                        <span>Package type</span>
                        <div class="option-group option-group-equal">
                            <label class="option-pill">
                                <input type="radio" name="package_type" value="bag" data-order-package-type checked required>
                                <span>
                                    <svg class="pill-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M7 9v-.5a5 5 0 0 1 10 0V9"></path>
                                        <path d="M5 9h14l-1.2 10.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8L5 9z"></path>
                                    </svg>
                                    Bag
                                </span>
                            </label>
                            <label class="option-pill">
                                <input type="radio" name="package_type" value="box" data-order-package-type>
                                <span>
                                    <svg class="pill-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 7.5L12 3l9 4.5-9 4.5-9-4.5z"></path>
                                        <path d="M3 7.5v9l9 4.5 9-4.5v-9"></path>
                                        <path d="M12 12v9"></path>
                                    </svg>
                                    Box
                                </span>
                            </label>
                        </div>
                    </label>
                    <label class="order-edit-weight-type">
                        <span>Weight type</span>
                        <div class="option-group option-group-equal">
                            <label class="option-pill">
                                <input type="radio" name="weight_type" value="actual" data-order-weight-type-input checked required>
                                <span>Actual (KG)</span>
                            </label>
                            <label class="option-pill">
                                <input type="radio" name="weight_type" value="volumetric" data-order-weight-type-input>
                                <span>Volumetric (CBM)</span>
                            </label>
                        </div>
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
                    <?php if ($showIncome): ?>
                        <label>
                            <span>Rate (KG)</span>
                            <input type="number" step="0.01" name="rate_kg">
                        </label>
                        <label>
                            <span>Rate (CBM)</span>
                            <input type="number" step="0.01" name="rate_cbm">
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
                    <?php endif; ?>
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
