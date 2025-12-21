<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'customers', 'Customer Details', 'Profile, balance, and activity.');
?>
<?php
$customerId = $_GET['id'] ?? null;
?>
<div data-customer-view data-customer-id="<?= htmlspecialchars((string) $customerId, ENT_QUOTES) ?>">
    <section class="panel detail-grid">
        <article class="detail-card">
            <h3>Customer info</h3>
            <div class="detail-list">
                <div><span>Name</span><strong data-detail="name">--</strong></div>
                <div><span>Code</span><strong data-detail="code">--</strong></div>
                <div><span>Phone</span><strong data-detail="phone">--</strong></div>
                <div><span>Address</span><strong data-detail="address">--</strong></div>
                <div><span>Portal username</span><strong data-detail="portal_username">--</strong></div>
                <div><span>Branch</span><strong data-detail="sub_branch_name">--</strong></div>
            </div>
        </article>
        <article class="detail-card">
            <h3>Balance</h3>
            <div class="detail-list">
                <div><span>Current</span><strong data-detail="balance">--</strong></div>
                <div><span>System</span><strong data-detail="is_system">--</strong></div>
            </div>
        </article>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Invoices</h3>
                <p>Latest invoices issued to this customer.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Due</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody data-customer-invoices>
                    <tr><td colspan="5" class="muted">Loading invoices...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-customer-invoices-pagination>
            <button class="button ghost small" type="button" data-customer-invoices-prev>Previous</button>
            <span class="page-label" data-customer-invoices-page>Page 1</span>
            <button class="button ghost small" type="button" data-customer-invoices-next>Next</button>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Transactions</h3>
                <p>Payments and allocations recorded for this customer.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody data-customer-transactions>
                    <tr><td colspan="4" class="muted">Loading transactions...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-customer-transactions-pagination>
            <button class="button ghost small" type="button" data-customer-transactions-prev>Previous</button>
            <span class="page-label" data-customer-transactions-page>Page 1</span>
            <button class="button ghost small" type="button" data-customer-transactions-next>Next</button>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Orders</h3>
                <p>Recent orders linked to this customer.</p>
            </div>
            <div class="panel-actions">
                <label class="inline-field">
                    <span>Search notes</span>
                    <input type="text" data-order-note-search placeholder="Search notes">
                </label>
                <button class="button ghost small" type="button" data-order-note-submit>Search</button>
                <label class="inline-field">
                    <span>Move selected to</span>
                    <select data-reassign-customer>
                        <option value="">Select customer</option>
                    </select>
                </label>
                <button class="button primary small" type="button" data-reassign-submit>Change customer</button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-col"><input type="checkbox" data-orders-select-all></th>
                        <th>Tracking</th>
                        <th>Shipment</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th>Total</th>
                        <th class="meta-col">Created</th>
                        <th class="meta-col">Updated</th>
                        <th>Media</th>
                    </tr>
                </thead>
                <tbody data-customer-orders>
                    <tr><td colspan="9" class="muted">Loading orders...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-customer-orders-pagination>
            <button class="button ghost small" type="button" data-customer-orders-prev>Previous</button>
            <span class="page-label" data-customer-orders-page>Page 1</span>
            <button class="button ghost small" type="button" data-customer-orders-next>Next</button>
        </div>
    </section>

    <section class="panel is-hidden" data-order-media-panel>
        <div class="panel-header">
            <div>
                <h3>Order media</h3>
                <p data-order-media-title>Select an order to manage attachments.</p>
            </div>
        </div>
        <form class="grid-form" data-order-media-form enctype="multipart/form-data">
            <input type="hidden" name="entity_type" value="order">
            <input type="hidden" name="entity_id" data-order-media-id>
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
            <button class="button primary" type="submit">Upload</button>
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
                <tbody data-order-media-table>
                    <tr><td colspan="5" class="muted">No attachments loaded.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-order-media-pagination>
            <button class="button ghost small" type="button" data-order-media-prev>Previous</button>
            <span class="page-label" data-order-media-page>Page 1</span>
            <button class="button ghost small" type="button" data-order-media-next>Next</button>
        </div>
        <div class="notice-stack" data-order-media-status></div>
    </section>

    <div class="notice-stack" data-customer-view-status></div>
</div>
<?php
internal_page_end();
