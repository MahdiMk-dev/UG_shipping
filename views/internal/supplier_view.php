<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch'], true);
$supplierId = $_GET['id'] ?? null;
internal_page_start($user, 'suppliers', 'Supplier Profile', 'Rates, invoices, and payments for suppliers.');
if (!in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch'], true)) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin, Owner, and Main Branch roles can view Suppliers.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-supplier-view data-supplier-id="<?= htmlspecialchars((string) $supplierId, ENT_QUOTES) ?>"
     data-can-edit="<?= $canEdit ? '1' : '0' ?>">
    <section class="panel profile-panel">
        <div class="panel-header">
            <div>
                <h3>Profile details</h3>
                <p>Contact and balance overview.</p>
            </div>
        </div>
        <div class="profile-row">
            <article class="detail-card">
                <h3>Supplier info</h3>
                <div class="detail-list">
                    <div><span>Name</span><strong data-supplier-detail="name">--</strong></div>
                    <div><span>Type</span><strong data-supplier-detail="type">--</strong></div>
                    <div><span>Phone</span><strong data-supplier-detail="phone">--</strong></div>
                    <div><span>Address</span><strong data-supplier-detail="address">--</strong></div>
                    <div><span>Notes</span><strong data-supplier-detail="note">--</strong></div>
                    <div><span>Balance</span><strong data-supplier-detail="balance">--</strong></div>
                </div>
            </article>
            <div class="stats-grid is-compact">
                <article class="stat-card is-compact">
                    <h3 data-supplier-stat="shipments_count">--</h3>
                    <div class="stat-meta">Total shipments</div>
                </article>
                <article class="stat-card is-compact">
                    <h3 data-supplier-stat="open_invoices_count">--</h3>
                    <div class="stat-meta">Open invoices</div>
                </article>
                <article class="stat-card is-compact">
                    <h3 data-supplier-stat="total_invoiced">--</h3>
                    <div class="stat-meta">Total invoiced</div>
                </article>
                <article class="stat-card is-compact">
                    <h3 data-supplier-stat="total_paid">--</h3>
                    <div class="stat-meta">Total paid</div>
                </article>
                <article class="stat-card is-compact">
                    <h3 data-supplier-stat="total_due">--</h3>
                    <div class="stat-meta">Outstanding</div>
                </article>
            </div>
        </div>
        <div class="notice-stack" data-supplier-status></div>
    </section>

    <section class="panel" data-supplier-tabs>
        <div class="panel-header">
            <div>
                <h3>Supplier sections</h3>
                <p>Review shipments, invoices, and payments for this supplier.</p>
            </div>
        </div>
        <div class="tab-nav" role="tablist">
            <button class="tab-button is-active" type="button" role="tab" data-supplier-tab="shipments" aria-selected="true">
                Shipments
            </button>
            <button class="tab-button" type="button" role="tab" data-supplier-tab="invoices" aria-selected="false">
                Invoices
            </button>
            <button class="tab-button" type="button" role="tab" data-supplier-tab="payments" aria-selected="false">
                Payments
            </button>
        </div>
        <div class="tab-panels">
            <div class="tab-panel is-active" data-supplier-tab-panel="shipments">
                <div class="panel-header">
                    <div>
                        <h3>Linked shipments</h3>
                        <p>Shipments where this supplier is the shipper or consignee.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Shipment</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Origin</th>
                                <th>View</th>
                            </tr>
                        </thead>
                        <tbody data-supplier-shipments>
                            <tr><td colspan="5" class="muted">Loading shipments...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-panel" data-supplier-tab-panel="invoices">
                <div class="panel-header">
                    <div>
                        <h3>Invoices</h3>
                        <p>Charges calculated from shipment weight and volume.</p>
                    </div>
                </div>
                <?php if ($canEdit): ?>
                    <form class="grid-form" data-supplier-invoice-form>
                        <input type="hidden" name="invoice_id" data-supplier-invoice-id>
                        <label class="full">
                            <span>Shipment search</span>
                            <input type="text" placeholder="Search shipment number" data-shipment-search>
                        </label>
                        <label>
                            <span>Shipment</span>
                            <select name="shipment_id" data-shipment-select>
                                <option value="">Select shipment</option>
                            </select>
                        </label>
                        <label>
                            <span>Total weight (kg)</span>
                            <input type="text" data-supplier-invoice-weight readonly>
                        </label>
                        <label>
                            <span>Total volume (cbm)</span>
                            <input type="text" data-supplier-invoice-volume readonly>
                        </label>
                        <label>
                            <span>Kg rate</span>
                            <input type="number" step="0.01" name="rate_kg" data-supplier-rate-kg placeholder="0.00">
                        </label>
                        <label>
                            <span>CBM rate</span>
                            <input type="number" step="0.01" name="rate_cbm" data-supplier-rate-cbm placeholder="0.00">
                        </label>
                        <label>
                            <span>Currency</span>
                            <select name="currency" data-supplier-invoice-currency>
                                <option value="USD">USD</option>
                                <option value="LBP">LBP</option>
                            </select>
                        </label>
                        <label>
                            <span>Total</span>
                            <input type="text" data-supplier-invoice-total readonly>
                        </label>
                        <label>
                            <span>Issued at</span>
                            <input type="datetime-local" name="issued_at">
                        </label>
                        <label class="full">
                            <span>Note</span>
                            <input type="text" name="note" placeholder="Optional note">
                        </label>
                        <div class="full">
                            <button class="button primary small" type="submit" data-supplier-invoice-submit>Add invoice</button>
                            <button class="button ghost small is-hidden" type="button" data-supplier-invoice-cancel-edit>
                                Cancel edit
                            </button>
                        </div>
                    </form>
                    <div class="notice-stack" data-supplier-invoice-status></div>
                <?php endif; ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Shipment</th>
                                <th>Status</th>
                                <th>Currency</th>
                                <th>Kg rate</th>
                                <th>CBM rate</th>
                                <th>Total</th>
                                <th>Due</th>
                                <th>Issued</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody data-supplier-invoices>
                            <tr><td colspan="10" class="muted">Loading invoices...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination" data-supplier-invoices-pagination>
                    <button class="button ghost small" type="button" data-supplier-invoices-prev>Previous</button>
                    <span class="page-label" data-supplier-invoices-page>Page 1</span>
                    <button class="button ghost small" type="button" data-supplier-invoices-next>Next</button>
                </div>
            </div>

            <div class="tab-panel" data-supplier-tab-panel="payments">
                <div class="panel-header">
                    <div>
                        <h3>Payments</h3>
                        <p>Record payments or balance adjustments for this supplier.</p>
                    </div>
                </div>
                <?php if ($canEdit): ?>
                    <form class="grid-form" data-supplier-transaction-form>
                        <label>
                            <span>Type</span>
                            <select name="type">
                                <option value="payment">Payment</option>
                                <option value="refund">Refund</option>
                                <option value="adjustment">Adjustment</option>
                                <option value="charge">Charge</option>
                                <option value="discount">Discount</option>
                            </select>
                        </label>
                        <label class="is-hidden" data-supplier-reason-field>
                            <span>Reason</span>
                            <select name="reason" data-supplier-transaction-reason>
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
                            <span>Invoice</span>
                            <select name="invoice_id" data-supplier-invoice-select>
                                <option value="">Apply to balance only</option>
                            </select>
                        </label>
                        <label>
                            <span>Admin account</span>
                            <select name="admin_account_id" data-supplier-payment-account required>
                                <option value="">Select admin account</option>
                            </select>
                        </label>
                        <div class="table-wrap full">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-supplier-transaction-items>
                                    <tr data-line-item>
                                        <td><input type="text" name="item_description" placeholder="Description" required></td>
                                        <td><input type="number" step="0.01" name="item_amount" placeholder="0.00" required></td>
                                        <td>
                                            <button class="button ghost small" type="button" data-line-remove>Remove</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="full">
                            <button class="button ghost small" type="button" data-supplier-add-transaction-line>Add line</button>
                        </div>
                        <label>
                            <span>Total</span>
                            <input type="text" data-supplier-transaction-total readonly>
                        </label>
                        <label>
                            <span>Payment date</span>
                            <input type="date" name="payment_date">
                        </label>
                        <label class="full">
                            <span>Note</span>
                            <input type="text" name="note" placeholder="Optional note">
                        </label>
                        <button class="button primary small" type="submit">Add payment</button>
                    </form>
                    <div class="notice-stack" data-supplier-transaction-status></div>
                <?php endif; ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Account</th>
                                <th>Amount</th>
                                <th>Invoice</th>
                                <th>Reason</th>
                                <th>Note</th>
                                <th>Print</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody data-supplier-transactions>
                            <tr><td colspan="10" class="muted">Loading payments...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination" data-supplier-transactions-pagination>
                    <button class="button ghost small" type="button" data-supplier-transactions-prev>Previous</button>
                    <span class="page-label" data-supplier-transactions-page>Page 1</span>
                    <button class="button ghost small" type="button" data-supplier-transactions-next>Next</button>
                </div>
            </div>
        </div>
    </section>
</div>
<?php
internal_page_end();
?>


