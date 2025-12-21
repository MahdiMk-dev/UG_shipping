<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'shipments', 'Shipment Details', 'Full overview with orders and attachments.');
?>
<?php
$shipmentId = $_GET['id'] ?? null;
$shipmentNumber = $_GET['shipment_number'] ?? null;
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true);
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
     data-collection-packing-url="<?= htmlspecialchars($collectionPackingUrl, ENT_QUOTES) ?>">
    <section class="panel">
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
        </div>
        <div class="detail-grid">
            <article class="detail-card">
                <h3>Shipment info</h3>
                <div class="detail-list">
                    <div><span>Shipment #</span><strong data-detail="shipment_number">--</strong></div>
                    <div><span>Status</span><strong data-detail="status">--</strong></div>
                    <div><span>Type</span><strong data-detail="shipping_type">--</strong></div>
                    <div><span>Origin</span><strong data-detail="origin_country">--</strong></div>
                    <div><span>Departure</span><strong data-detail="departure_date">--</strong></div>
                    <div><span>Arrival</span><strong data-detail="arrival_date">--</strong></div>
                    <div><span>Total weight</span><strong data-detail="total_weight">--</strong></div>
                    <div><span>Total volume</span><strong data-detail="total_volume">--</strong></div>
                </div>
            </article>
            <article class="detail-card">
                <h3>Rates & notes</h3>
                <div class="detail-list">
                    <div><span>Default rate</span><strong data-detail="default_rate">--</strong></div>
                    <div><span>Rate unit</span><strong data-detail="default_rate_unit">--</strong></div>
                    <div><span>Cost per unit</span><strong data-detail="cost_per_unit">--</strong></div>
                    <div><span>Notes</span><strong data-detail="note">--</strong></div>
                </div>
            </article>
        </div>
    </section>
    <?php if ($canEdit): ?>
        <div class="drawer" data-shipment-edit-panel data-shipment-edit-drawer>
            <div class="drawer-scrim" data-shipment-edit-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="edit-shipment-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="edit-shipment-title">Edit shipment</h3>
                        <p>Update shipment status, dates, and default rate.</p>
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
                        <select name="shipping_type" required>
                            <option value="air">Air</option>
                            <option value="sea">Sea</option>
                            <option value="land">Land</option>
                        </select>
                    </label>
                    <label>
                        <span>Status</span>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="departed">Departed</option>
                            <option value="airport">Airport</option>
                            <option value="arrived">Arrived</option>
                            <option value="distributed">Distributed</option>
                        </select>
                    </label>
                    <label>
                        <span>Departure date</span>
                        <input type="date" name="departure_date">
                    </label>
                    <label>
                        <span>Arrival date</span>
                        <input type="date" name="arrival_date">
                    </label>
                    <label>
                        <span>Default rate</span>
                        <input type="number" step="0.01" name="default_rate">
                    </label>
                    <label>
                        <span>Rate unit</span>
                        <select name="default_rate_unit">
                            <option value="">Select unit</option>
                            <option value="kg">KG</option>
                            <option value="cbm">CBM</option>
                        </select>
                    </label>
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

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Collections</h3>
                <p>Groupings within this shipment.</p>
            </div>
        </div>
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
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Shipment media</h3>
                <p>Upload and manage files linked to this shipment.</p>
            </div>
            <div class="panel-actions">
                <a class="button ghost small" target="_blank" data-shipment-packing-view href="#">
                    Packing list
                </a>
                <a class="button ghost small" target="_blank" data-shipment-packing-download href="#">
                    Download
                </a>
            </div>
        </div>
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
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Orders by customer</h3>
                <p>Grouped totals for each customer in this shipment.</p>
            </div>
            <?php if ($canEdit): ?>
                <a class="button ghost small" data-add-order-link href="<?= htmlspecialchars($orderCreateUrl, ENT_QUOTES) ?>">Add order</a>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Orders</th>
                        <th>Total Qty</th>
                        <th>Total</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody data-orders-table>
                    <tr><td colspan="5" class="muted">Loading orders...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-orders-pagination>
            <button class="button ghost small" type="button" data-orders-prev>Previous</button>
            <span class="page-label" data-orders-page>Page 1</span>
            <button class="button ghost small" type="button" data-orders-next>Next</button>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Attachments</h3>
                <p>Media linked to this shipment and its orders.</p>
            </div>
        </div>
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
    </section>

    <div class="notice-stack" data-shipment-status></div>
</div>
<?php
internal_page_end();
