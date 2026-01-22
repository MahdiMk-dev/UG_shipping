<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
$canCreatePayment = in_array($role, ['Admin', 'Owner', 'Main Branch', 'Sub Branch'], true);
$canCreateCustomer = $role === 'Admin';
$canReassign = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
internal_page_start($user, 'customers', 'Customer Details', 'Profile, balance, and activity.');
if ($role === 'Warehouse') {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Warehouse users cannot view customer profiles.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<?php
$customerId = $_GET['id'] ?? null;
?>
<div data-customer-view data-customer-id="<?= htmlspecialchars((string) $customerId, ENT_QUOTES) ?>">
    <section class="panel profile-panel">
        <div class="detail-grid">
            <article class="detail-card">
                <h3>Customer info</h3>
                <div class="detail-list">
                    <div><span>Name</span><strong data-detail="name">--</strong></div>
                    <div><span>Code</span><strong data-detail="code">--</strong></div>
                    <div><span>Profile country</span><strong data-detail="profile_country_name">--</strong></div>
                        <div><span>Phone</span><strong data-detail="phone">--</strong></div>
                        <div><span>Address</span><strong data-detail="address">--</strong></div>
                        <div><span>Notes</span><strong data-detail="note">--</strong></div>
                        <div><span>Portal username</span><strong data-detail="portal_username">--</strong></div>
                    <div><span>Portal phone</span><strong data-detail="portal_phone">--</strong></div>
                    <div><span>Branch</span><strong data-detail="sub_branch_name">--</strong></div>
                </div>
            </article>
            <article class="detail-card">
                <h3>Balance</h3>
                <div class="detail-list">
                    <div><span>Current</span><strong data-detail="balance">--</strong></div>
                    <div><span>Points</span><strong data-detail="points_balance">--</strong></div>
                    <div><span>System</span><strong data-detail="is_system">--</strong></div>
                </div>
            </article>
        </div>
        <div class="stats-grid is-compact">
            <article class="stat-card is-compact">
                <h3 data-customer-stat="orders_count">--</h3>
                <div class="stat-meta">Orders</div>
            </article>
            <article class="stat-card is-compact">
                <h3 data-customer-stat="open_invoices_count">--</h3>
                <div class="stat-meta">Open invoices</div>
            </article>
            <article class="stat-card is-compact">
                <h3 data-customer-stat="total_invoiced">--</h3>
                <div class="stat-meta">Total invoiced</div>
            </article>
            <article class="stat-card is-compact">
                <h3 data-customer-stat="total_paid">--</h3>
                <div class="stat-meta">Total paid</div>
            </article>
            <article class="stat-card is-compact">
                <h3 data-customer-stat="last_order_date">--</h3>
                <div class="stat-meta">Last order</div>
            </article>
        </div>
    </section>

    <section class="panel" data-customer-tabs>
        <div class="panel-header">
            <div>
                <h3>Customer sections</h3>
                <p>Switch between profiles, invoices, orders, and activity.</p>
            </div>
        </div>
        <div class="tab-nav" role="tablist">
            <button class="tab-button is-active" type="button" role="tab" data-customer-tab="profiles" aria-selected="true">
                Profiles
            </button>
            <button class="tab-button" type="button" role="tab" data-customer-tab="invoices" aria-selected="false">
                Invoices
            </button>
            <button class="tab-button" type="button" role="tab" data-customer-tab="uninvoiced" aria-selected="false">
                Un-invoiced
            </button>
            <button class="tab-button" type="button" role="tab" data-customer-tab="transactions" aria-selected="false">
                Transactions
            </button>
            <button class="tab-button" type="button" role="tab" data-customer-tab="orders" aria-selected="false">
                Orders
            </button>
            <button class="tab-button" type="button" role="tab" data-customer-tab="media" aria-selected="false">
                Media
            </button>
        </div>
        <div class="tab-panels">
            <div class="tab-panel is-active" data-customer-tab-panel="profiles">
                <div class="panel-header">
                    <div>
                        <h3>Profiles</h3>
                        <p>All profiles linked to this portal account.</p>
                    </div>
                    <?php if ($canCreateCustomer): ?>
                        <a class="button ghost small" href="<?= BASE_URL ?>/views/internal/customer_info_edit?id=<?= htmlspecialchars((string) $customerId, ENT_QUOTES) ?>">
                            Edit info
                        </a>
                        <a class="button ghost small is-hidden" href="#" data-add-profile>Add profile</a>
                    <?php endif; ?>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Country</th>
                                <th>Branch</th>
                                <th>Balance</th>
                                <th>Portal</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody data-customer-profiles>
                            <tr><td colspan="7" class="muted">Loading profiles...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-panel" data-customer-tab-panel="invoices">
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
            </div>

            <div class="tab-panel" data-customer-tab-panel="uninvoiced">
                <div class="panel-header">
                    <div>
                        <h3>Un-invoiced orders</h3>
                        <p>Orders received at the sub branch that are ready to invoice.</p>
                    </div>
                    <div class="panel-actions">
                        <button class="button primary small" type="button" data-customer-invoice-selected disabled>
                            Create invoice for selected
                        </button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-col"><input type="checkbox" data-customer-uninvoiced-select-all></th>
                                <th>Tracking</th>
                                <th>Shipment</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody data-customer-uninvoiced>
                            <tr>
                                <td colspan="7" class="loading-cell">
                                    <div class="loading-inline">
                                        <span class="spinner" aria-hidden="true"></span>
                                        <span class="loading-text">Orders are loading, please wait...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination" data-customer-uninvoiced-pagination>
                    <button class="button ghost small" type="button" data-customer-uninvoiced-prev>Previous</button>
                    <span class="page-label" data-customer-uninvoiced-page>Page 1</span>
                    <button class="button ghost small" type="button" data-customer-uninvoiced-next>Next</button>
                </div>
            </div>

            <div class="tab-panel" data-customer-tab-panel="transactions">
                <div class="panel-header">
                    <div>
                        <h3>Transactions</h3>
                        <p>Balance activity from orders, payments, and adjustments.</p>
                    </div>
                </div>
                <?php if ($canCreatePayment): ?>
                    <form class="grid-form" data-customer-payment-form>
                        <label data-payment-from-field>
                            <span>Amount</span>
                            <input type="number" step="0.01" name="amount" data-customer-payment-amount required>
                        </label>
                        <label data-payment-type-field>
                            <span>Type</span>
                            <select name="type" data-customer-payment-type>
                                <option value="payment">Payment</option>
                                <option value="refund">Refund</option>
                                <option value="charge">Charge</option>
                                <option value="discount">Discount</option>
                            </select>
                        </label>
                        <label class="is-hidden" data-payment-reason-field>
                            <span>Reason</span>
                            <select name="reason" data-customer-payment-reason>
                                <option value="">Select reason</option>
                                <option value="Damaged item">Damaged item</option>
                                <option value="Duplicate payment">Duplicate payment</option>
                                <option value="Order canceled">Order canceled</option>
                                <option value="Overcharge correction">Overcharge correction</option>
                                <option value="Service issue">Service issue</option>
                                <option value="Other">Other</option>
                            </select>
                        </label>
                        <label>
                            <span data-payment-from-label>From account</span>
                            <select name="from_account_id" data-customer-payment-from required>
                                <option value="">Select branch account</option>
                            </select>
                        </label>
                        <label data-payment-to-field>
                            <span data-payment-to-label>To admin account</span>
                            <select name="to_account_id" data-customer-payment-to required>
                                <option value="">Select admin account</option>
                            </select>
                        </label>
                        <label>
                            <span>Payment date</span>
                            <input type="date" name="payment_date" data-customer-payment-date>
                        </label>
                        <label>
                            <span>Invoice (optional)</span>
                            <select name="invoice_id" data-customer-payment-invoice>
                                <option value="">No invoice</option>
                            </select>
                        </label>
                        <label>
                            <span>Whish phone</span>
                            <input type="text" name="whish_phone" data-customer-payment-whish placeholder="Optional">
                        </label>
                        <label>
                            <span>Note</span>
                            <input type="text" name="note" data-customer-payment-note placeholder="Optional note">
                        </label>
                        <button class="button primary small" type="submit">Record payment</button>
                    </form>
                    <div class="notice-stack" data-customer-payment-status></div>
                <?php endif; ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Account</th>
                                <th>Date</th>
                                <th>Reason</th>
                                <th>Reference</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody data-customer-transactions>
                            <tr><td colspan="7" class="muted">Loading transactions...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination" data-customer-transactions-pagination>
                    <button class="button ghost small" type="button" data-customer-transactions-prev>Previous</button>
                    <span class="page-label" data-customer-transactions-page>Page 1</span>
                    <button class="button ghost small" type="button" data-customer-transactions-next>Next</button>
                </div>
            </div>

            <div class="tab-panel" data-customer-tab-panel="orders">
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
                        <?php if ($canReassign): ?>
                            <label class="inline-field">
                                <span>Move selected to</span>
                                <select data-reassign-customer>
                                    <option value="">Select customer</option>
                                </select>
                            </label>
                            <button class="button primary small" type="button" data-reassign-submit>Change customer</button>
                        <?php endif; ?>
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
                            <tr>
                                <td colspan="9" class="loading-cell">
                                    <div class="loading-inline">
                                        <span class="spinner" aria-hidden="true"></span>
                                        <span class="loading-text">Orders are loading, please wait...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination" data-customer-orders-pagination>
                    <button class="button ghost small" type="button" data-customer-orders-prev>Previous</button>
                    <span class="page-label" data-customer-orders-page>Page 1</span>
                    <button class="button ghost small" type="button" data-customer-orders-next>Next</button>
                </div>
            </div>

            <div class="tab-panel" data-customer-tab-panel="media">
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
            </div>
        </div>
    </section>

    <div class="notice-stack" data-customer-view-status></div>
</div>
<?php
internal_page_end();
