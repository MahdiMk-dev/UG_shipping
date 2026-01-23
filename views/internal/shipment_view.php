<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'shipments', 'Shipment Details', 'Full overview with orders and attachments.');
?>
<?php
$shipmentId = $_GET['id'] ?? null;
$shipmentNumber = $_GET['shipment_number'] ?? null;
$role = $user['role'] ?? '';
$canEdit = in_array($role, ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true);
$isWarehouse = $role === 'Warehouse';
$showIncome = !$isWarehouse;
$showRates = !$isWarehouse;
$orderCreateUrl = BASE_URL . '/views/internal/order_create';
$customerOrdersUrl = BASE_URL . '/views/internal/shipment_customer_orders';
$shipmentPackingUrl = BASE_URL . '/api/shipments/packing_list.php';
$collectionPackingUrl = BASE_URL . '/api/collections/packing_list.php';
if ($shipmentId) {
    $orderCreateUrl .= '?shipment_id=' . urlencode((string) $shipmentId);
} elseif ($shipmentNumber) {
    $orderCreateUrl .= '?shipment_number=' . urlencode((string) $shipmentNumber);
}
?>
<div data-shipment-view data-order-create-url="<?= htmlspecialchars($orderCreateUrl, ENT_QUOTES) ?>"
     data-customer-orders-url="<?= htmlspecialchars($customerOrdersUrl, ENT_QUOTES) ?>"
     data-shipment-packing-url="<?= htmlspecialchars($shipmentPackingUrl, ENT_QUOTES) ?>"
     data-collection-packing-url="<?= htmlspecialchars($collectionPackingUrl, ENT_QUOTES) ?>"
     data-show-income="<?= $showIncome ? '1' : '0' ?>"
     data-show-rates="<?= $showRates ? '1' : '0' ?>">
    <section class="panel is-collapsible" data-collapsible-panel>
        <div class="panel-header">
            <div>
                <div class="panel-title-row">
                    <h3>Shipment details</h3>
                    <?php if ($canEdit): ?>
                        <button class="button primary small" type="button" data-shipment-edit-open data-shipment-edit-trigger>
                            Edit
                        </button>
                    <?php endif; ?>
                    <?php if (in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch'], true)): ?>
                        <button class="button ghost small is-hidden" type="button" data-shipment-distribute>
                            Distribute
                        </button>
                    <?php endif; ?>
                </div>
                <p>Key milestones and totals for this shipment.</p>
            </div>
            <div class="panel-actions">
                <button class="panel-toggle" type="button" data-panel-toggle aria-expanded="true" aria-label="Collapse section">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"></path></svg>
                </button>
            </div>
        </div>
        <div class="panel-body">
            <div class="detail-grid">
                <article class="detail-card">
                    <h3>Shipment info</h3>
                    <div class="detail-list">
                        <div><span>Shipment #</span><strong data-detail="shipment_number">--</strong></div>
                        <div><span>Status</span><strong data-detail="status">--</strong></div>
                        <div><span>Type</span><strong data-detail="shipping_type">--</strong></div>
                        <div><span>Origin</span><strong data-detail="origin_country">--</strong></div>
                        <div><span>Type of goods</span><strong data-detail="type_of_goods">--</strong></div>
                        <div><span>Expected departure</span><strong data-detail="departure_date">--</strong></div>
                        <div><span>Actual departure</span><strong data-detail="actual_departure_date">--</strong></div>
                        <div><span>Expected arrival</span><strong data-detail="arrival_date">--</strong></div>
                        <div><span>Actual arrival</span><strong data-detail="actual_arrival_date">--</strong></div>
                        <div><span>Total weight</span><strong data-detail="total_weight">--</strong></div>
                        <div><span>Total volume</span><strong data-detail="total_volume">--</strong></div>
                    </div>
                </article>
                <article class="detail-card">
                    <h3><?= $showRates ? 'Rates & notes' : 'Notes' ?></h3>
                    <div class="detail-list">
                        <?php if ($showRates): ?>
                            <div><span>Default rate (KG)</span><strong data-detail="default_rate_kg">--</strong></div>
                            <div><span>Default rate (CBM)</span><strong data-detail="default_rate_cbm">--</strong></div>
                            <div><span>Cost per unit</span><strong data-detail="cost_per_unit">--</strong></div>
                        <?php endif; ?>
                        <div><span>Notes</span><strong data-detail="note">--</strong></div>
                    </div>
                </article>
                <article class="detail-card">
                    <h3>Suppliers</h3>
                    <div class="detail-list">
                        <div><span>Shipper</span><strong data-detail="shipper_profile_name">--</strong></div>
                        <div><span>Consignee</span><strong data-detail="consignee_profile_name">--</strong></div>
                    </div>
                </article>
            </div>
        </div>
    </section>
    <?php if ($canEdit): ?>
        <div class="drawer" data-shipment-edit-panel data-shipment-edit-drawer>
            <div class="drawer-scrim" data-shipment-edit-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="edit-shipment-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="edit-shipment-title">Edit shipment</h3>
                        <p>Update shipment status, dates, and default rates.</p>
                    </div>
                    <button class="icon-button" type="button" data-shipment-edit-close aria-label="Close edit panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-shipment-edit-form>
                    <input type="hidden" name="shipment_id" data-shipment-id-field>
                    <label>
                        <span>Shipment number</span>
                        <input type="text" name="shipment_number" required>
                    </label>
                    <label>
                        <span>Origin country</span>
                        <select name="origin_country_id" data-origin-select required>
                            <option value="">Select origin</option>
                        </select>
                    </label>
                    <label>
                        <span>Shipping type</span>
                        <div class="option-group" data-shipping-type-group>
                            <label class="option-pill">
                                <input type="radio" name="shipping_type" value="air" required>
                                <span>Air</span>
                            </label>
                            <label class="option-pill">
                                <input type="radio" name="shipping_type" value="sea">
                                <span>Sea</span>
                            </label>
                            <label class="option-pill">
                                <input type="radio" name="shipping_type" value="land">
                                <span>Land</span>
                            </label>
                        </div>
                    </label>
                    <label>
                        <span>Type of goods</span>
                        <select name="type_of_goods" data-goods-select required>
                            <option value="">Select type</option>
                        </select>
                    </label>
                    <?php if (!$isWarehouse): ?>
                        <label>
                            <span>Shipper profile</span>
                            <select name="shipper_profile_id" data-supplier-select data-supplier-type="shipper">
                                <option value="">Select shipper (optional)</option>
                            </select>
                        </label>
                        <label>
                            <span>Consignee profile</span>
                            <select name="consignee_profile_id" data-supplier-select data-supplier-type="consignee">
                                <option value="">Select consignee (optional)</option>
                            </select>
                        </label>
                    <?php endif; ?>
                    <label>
                        <span>Status</span>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="departed">Departed</option>
                            <option value="airport">Airport</option>
                            <option value="arrived">Arrived</option>
                            <option value="partially_distributed">Partially distributed</option>
                            <option value="distributed">Distributed</option>
                        </select>
                    </label>
                    <label>
                        <span>Expected departure date</span>
                        <input type="date" name="departure_date">
                    </label>
                    <label>
                        <span>Expected arrival date</span>
                        <input type="date" name="arrival_date">
                    </label>
                    <?php if ($showRates): ?>
                        <label>
                            <span>Default rate (KG)</span>
                            <input type="number" step="0.01" name="default_rate_kg">
                        </label>
                        <label>
                            <span>Default rate (CBM)</span>
                            <input type="number" step="0.01" name="default_rate_cbm">
                        </label>
                    <?php endif; ?>
                    <label class="full">
                        <span>Notes</span>
                        <input type="text" name="note" placeholder="Optional notes">
                    </label>
                    <button class="button primary small" type="submit">Save changes</button>
                </form>
                <div class="notice-stack" data-shipment-edit-status></div>
            </div>
        </div>
    <?php endif; ?>

    <section class="panel" data-shipment-tabs>
        <div class="panel-header">
            <div>
                <h3>Shipment activity</h3>
                <p>Browse customers, collections, media, expenses, and attachments.</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="tab-nav" role="tablist">
                <button class="tab-button is-active" type="button" role="tab" data-shipment-tab="customers" aria-selected="true">
                    Customers
                </button>
                <button class="tab-button" type="button" role="tab" data-shipment-tab="collections" aria-selected="false">
                    Collections
                </button>
                <button class="tab-button" type="button" role="tab" data-shipment-tab="media" aria-selected="false">
                    Media
                </button>
                <?php if (in_array($user['role'] ?? '', ['Admin', 'Owner'], true)): ?>
                    <button class="tab-button" type="button" role="tab" data-shipment-tab="expenses" aria-selected="false">
                        Expenses
                    </button>
                <?php endif; ?>
                <button class="tab-button" type="button" role="tab" data-shipment-tab="attachments" aria-selected="false">
                    Attachments
                </button>
            </div>
            <div class="tab-panels">
                <div class="tab-panel is-active" data-shipment-tab-panel="customers">
                    <div class="panel-title-row">
                        <h4>Orders by customer</h4>
                        <?php if ($canEdit): ?>
                            <a class="button ghost small" data-add-order-link href="<?= htmlspecialchars($orderCreateUrl, ENT_QUOTES) ?>">Add order</a>
                        <?php endif; ?>
                    </div>
                    <p>Grouped totals for each customer in this shipment.</p>
                    <form class="filter-bar" data-orders-search-form>
                        <input type="text" name="q" placeholder="Search customer or tracking" data-orders-search>
                        <button class="button primary" type="submit">Search</button>
                        <button class="button ghost" type="button" data-orders-clear>Clear</button>
                    </form>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Orders</th>
                                    <th>Total Qty</th>
                                    <?php if ($showIncome): ?>
                                        <th>Total</th>
                                    <?php endif; ?>
                                    <th>WhatsApp</th>
                                    <th>View</th>
                                </tr>
                            </thead>
                            <tbody data-orders-table>
                                <tr><td colspan="<?= $showIncome ? 6 : 5 ?>" class="muted">Loading orders...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-pagination" data-orders-pagination>
                        <button class="button ghost small" type="button" data-orders-prev>Previous</button>
                        <span class="page-label" data-orders-page>Page 1</span>
                        <button class="button ghost small" type="button" data-orders-next>Next</button>
                    </div>
                </div>
                <div class="tab-panel" data-shipment-tab-panel="collections">
                    <div class="panel-title-row">
                        <h4>Collections</h4>
                    </div>
                    <p>Groupings within this shipment.</p>
                    <?php if ($canEdit): ?>
                        <form class="grid-form" data-collection-create-form>
                            <label>
                                <span>New collection name</span>
                                <input type="text" name="name" placeholder="Collection name" required>
                            </label>
                            <button class="button ghost small" type="submit">Add collection</button>
                        </form>
                    <?php endif; ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Collection</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody data-collections-table>
                                <tr><td colspan="2" class="muted">Loading collections...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-pagination" data-collections-pagination>
                        <button class="button ghost small" type="button" data-collections-prev>Previous</button>
                        <span class="page-label" data-collections-page>Page 1</span>
                        <button class="button ghost small" type="button" data-collections-next>Next</button>
                    </div>
                </div>
                <div class="tab-panel" data-shipment-tab-panel="media">
                    <div class="panel-title-row">
                        <h4>Shipment media</h4>
                        <div class="panel-actions">
                            <a class="button ghost small" target="_blank" data-shipment-packing-view href="#">
                                Packing list
                            </a>
                            <a class="button ghost small" target="_blank" data-shipment-packing-download href="#">
                                Download
                            </a>
                        </div>
                    </div>
                    <p>Upload and manage files linked to this shipment.</p>
                    <form class="grid-form" data-shipment-media-form enctype="multipart/form-data">
                        <input type="hidden" name="entity_type" value="shipment">
                        <input type="hidden" name="entity_id" data-shipment-media-id>
                        <label>
                            <span>Title</span>
                            <input type="text" name="title" placeholder="Attachment title">
                        </label>
                        <label>
                            <span>Description</span>
                            <input type="text" name="description" placeholder="Optional notes">
                        </label>
                        <label>
                            <span>File</span>
                            <input type="file" name="file" required>
                        </label>
                        <button class="button primary small" type="submit">Upload</button>
                    </form>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Uploaded</th>
                                    <th>Download</th>
                                    <th>Remove</th>
                                </tr>
                            </thead>
                            <tbody data-shipment-media-table>
                                <tr><td colspan="5" class="muted">Loading shipment media...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-pagination" data-shipment-media-pagination>
                        <button class="button ghost small" type="button" data-shipment-media-prev>Previous</button>
                        <span class="page-label" data-shipment-media-page>Page 1</span>
                        <button class="button ghost small" type="button" data-shipment-media-next>Next</button>
                    </div>
                    <div class="notice-stack" data-shipment-media-status></div>
                </div>
                <?php if (in_array($user['role'] ?? '', ['Admin', 'Owner'], true)): ?>
                    <div class="tab-panel" data-shipment-tab-panel="expenses">
                        <div class="panel-title-row">
                            <h4>Shipment expenses</h4>
                            <button class="button ghost small" type="button" data-shipment-expenses-add>Add expense</button>
                        </div>
                        <p>Admin-only expenses linked to this shipment.</p>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Title</th>
                                        <th>Amount</th>
                                        <th>Note</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody data-shipment-expenses-table>
                                    <tr><td colspan="5" class="muted">Loading expenses...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-pagination" data-shipment-expenses-pagination>
                            <button class="button ghost small" type="button" data-shipment-expenses-prev>Previous</button>
                            <span class="page-label" data-shipment-expenses-page>Page 1</span>
                            <button class="button ghost small" type="button" data-shipment-expenses-next>Next</button>
                        </div>
                        <div class="notice-stack" data-shipment-expenses-status></div>
                    </div>
                <?php endif; ?>
                <div class="tab-panel" data-shipment-tab-panel="attachments">
                    <div class="panel-title-row">
                        <h4>Attachments</h4>
                    </div>
                    <p>Media linked to this shipment and its orders.</p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Entity</th>
                                    <th>Type</th>
                                    <th>Uploaded</th>
                                    <th>Download</th>
                                </tr>
                            </thead>
                            <tbody data-attachments-table>
                                <tr><td colspan="5" class="muted">Loading attachments...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-pagination" data-attachments-pagination>
                        <button class="button ghost small" type="button" data-attachments-prev>Previous</button>
                        <span class="page-label" data-attachments-page>Page 1</span>
                        <button class="button ghost small" type="button" data-attachments-next>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if (in_array($user['role'] ?? '', ['Admin', 'Owner'], true)): ?>
        <div class="drawer" data-shipment-expenses-drawer>
            <div class="drawer-scrim" data-shipment-expenses-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="shipment-expense-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="shipment-expense-title" data-shipment-expenses-title>Add expense</h3>
                        <p>Track costs tied to this shipment.</p>
                    </div>
                    <button class="icon-button" type="button" data-shipment-expenses-close aria-label="Close expense panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-shipment-expenses-form>
                    <input type="hidden" name="expense_id" data-shipment-expense-id>
                    <label>
                        <span>Title</span>
                        <input type="text" name="title" required>
                    </label>
                    <label>
                        <span>Amount</span>
                        <input type="number" step="0.01" name="amount" placeholder="0.00" required>
                    </label>
                    <label>
                        <span>Expense date</span>
                        <input type="date" name="expense_date">
                    </label>
                    <label class="full">
                        <span>Note</span>
                        <input type="text" name="note" placeholder="Optional note">
                    </label>
                    <button class="button primary small" type="submit" data-shipment-expenses-submit-label>Add expense</button>
                </form>
                <div class="notice-stack" data-shipment-expenses-form-status></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="notice-stack" data-shipment-status></div>
</div>
<?php
internal_page_end();


