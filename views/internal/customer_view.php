<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
$canCreatePayment = in_array($role, ['Admin', 'Owner', 'Main Branch', 'Sub Branch'], true);
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
    <section class="panel detail-grid">
        <article class="detail-card">
            <h3>Customer info</h3>
            <div class="detail-list">
                <div><span>Name</span><strong data-detail="name">--</strong></div>
                <div><span>Code</span><strong data-detail="code">--</strong></div>
                <div><span>Profile country</span><strong data-detail="profile_country_name">--</strong></div>
                <div><span>Phone</span><strong data-detail="phone">--</strong></div>
                <div><span>Address</span><strong data-detail="address">--</strong></div>
                <div><span>Portal username</span><strong data-detail="portal_username">--</strong></div>
                <div><span>Portal phone</span><strong data-detail="portal_phone">--</strong></div>
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
                <h3>Profiles</h3>
                <p>All profiles linked to this portal account.</p>
            </div>
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
                <h3>Un-invoiced orders</h3>
                <p>Orders received at the sub branch that are ready to invoice.</p>
            </div>
            <button class="button primary small" type="button" id="create-invoice-from-orders" style="display:none;">Create Invoice</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-uninvoiced"></th>
                        <th>Tracking</th>
                        <th>Shipment</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody data-customer-uninvoiced>
                    <tr><td colspan="6" class="muted">Loading orders...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-customer-uninvoiced-pagination>
            <button class="button ghost small" type="button" data-customer-uninvoiced-prev>Previous</button>
            <span class="page-label" data-customer-uninvoiced-page>Page 1</span>
            <button class="button ghost small" type="button" data-customer-uninvoiced-next>Next</button>
        </div>
        <script>
        // Add JS for multi-select and invoice creation
        (function() {
            const table = document.querySelector('[data-customer-uninvoiced]');
            const selectAll = document.getElementById('select-all-uninvoiced');
            const createBtn = document.getElementById('create-invoice-from-orders');
            const statusStack = document.querySelector('[data-customer-view-status]');
            let selected = new Set();
            function showNotice(message, type = 'error') {
                if (!statusStack) {
                    return;
                }
                const notice = document.createElement('div');
                notice.className = `notice ${type}`;
                notice.textContent = message;
                statusStack.appendChild(notice);
                setTimeout(() => notice.remove(), 6000);
            }
            function updateButton() {
                createBtn.style.display = selected.size > 0 ? '' : 'none';
            }
            function refreshSelection() {
                selected.clear();
                table.querySelectorAll('input[type=checkbox][data-order-id]:checked').forEach(cb => {
                    selected.add(cb.getAttribute('data-order-id'));
                });
                updateButton();
            }
            table.addEventListener('change', function(e) {
                if (e.target.matches('input[type=checkbox][data-order-id]')) {
                    refreshSelection();
                }
            });
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    const checked = selectAll.checked;
                    table.querySelectorAll('input[type=checkbox][data-order-id]').forEach(cb => {
                        cb.checked = checked;
                    });
                    refreshSelection();
                });
            }
            createBtn.addEventListener('click', async function() {
                if (!selected.size) return;
                createBtn.disabled = true;
                createBtn.textContent = 'Creating...';
                const customerId = document.querySelector('[data-customer-view]').getAttribute('data-customer-id');
                const orderIds = Array.from(selected);
                try {
                    const resp = await fetch(window.APP_BASE + '/api/invoices/create.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ customer_id: customerId, order_ids: orderIds })
                    });
                    const data = await resp.json();
                    if (data.ok) {
                        window.location.reload();
                    } else {
                        showNotice(data.error || 'Failed to create invoice', 'error');
                    }
                } catch (e) {
                    showNotice('Failed to create invoice', 'error');
                }
                createBtn.disabled = false;
                createBtn.textContent = 'Create Invoice';
            });
            // Patch table rendering to add checkboxes
            const origRender = window.renderCustomerUninvoicedOrders;
            window.renderCustomerUninvoicedOrders = function(rows) {
                let html = '';
                if (!rows.length) {
                    html = '<tr><td colspan="6" class="muted">No orders found.</td></tr>';
                } else {
                    html = rows.map(row =>
                        `<tr>`+
                        `<td><input type="checkbox" data-order-id="${row.id}"></td>`+
                        `<td>${row.tracking_number || '-'}</td>`+
                        `<td>${row.shipment_number || '-'}</td>`+
                        `<td>${row.fulfillment_status || '-'}</td>`+
                        `<td>${row.total_price ? Number(row.total_price).toFixed(2) : '-'}</td>`+
                        `<td>${row.created_at ? row.created_at.substr(0,10) : '-'}</td>`+
                        `</tr>`
                    ).join('');
                }
                table.innerHTML = html;
                refreshSelection();
            };
        })();
        </script>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Transactions</h3>
                <p>Balance activity from orders, payments, and adjustments.</p>
            </div>
        </div>
        <?php if ($canCreatePayment): ?>
            <form class="grid-form" data-customer-payment-form>
                <label>
                    <span>Amount</span>
                    <input type="number" step="0.01" name="amount" data-customer-payment-amount required>
                </label>
                <label>
                    <span>Payment method</span>
                    <select name="payment_method_id" data-customer-payment-method required>
                        <option value="">Select method</option>
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
                        <th>Method</th>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody data-customer-transactions>
                    <tr><td colspan="6" class="muted">Loading transactions...</td></tr>
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
