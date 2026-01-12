<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch'], true);
$partnerId = $_GET['id'] ?? null;
internal_page_start($user, 'partners', 'Partner Profile', 'Invoices and receipts for shippers and consignees.');
if (!in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true)) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin, Owner, Main Branch, and Warehouse roles can view partners.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-partner-view data-partner-id="<?= htmlspecialchars((string) $partnerId, ENT_QUOTES) ?>"
     data-can-edit="<?= $canEdit ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Profile details</h3>
                <p>Contact and balance overview.</p>
            </div>
        </div>
        <div class="detail-grid">
            <article class="detail-card">
                <h3>Partner info</h3>
                <div class="detail-list">
                    <div><span>Name</span><strong data-partner-detail="name">--</strong></div>
                    <div><span>Type</span><strong data-partner-detail="type">--</strong></div>
                    <div><span>Phone</span><strong data-partner-detail="phone">--</strong></div>
                    <div><span>Address</span><strong data-partner-detail="address">--</strong></div>
                    <div><span>Notes</span><strong data-partner-detail="note">--</strong></div>
                    <div><span>Balance</span><strong data-partner-detail="balance">--</strong></div>
                </div>
            </article>
        </div>
        <div class="notice-stack" data-partner-status></div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Linked shipments</h3>
                <p>Shipments where this partner is the shipper or consignee.</p>
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
                <tbody data-partner-shipments>
                    <tr><td colspan="5" class="muted">Loading shipments...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($canEdit): ?>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Create invoice</h3>
                    <p>Record charges for the partner. Invoice numbers are generated automatically.</p>
                </div>
            </div>
            <form class="grid-form" data-partner-invoice-form>
                <input type="hidden" name="invoice_id" data-partner-invoice-id>
                <label class="full">
                    <span>Shipment search</span>
                    <input type="text" placeholder="Search shipment number" data-shipment-search>
                </label>
                <label>
                    <span>Shipment</span>
                    <select name="shipment_id" data-shipment-select>
                        <option value="">No shipment</option>
                    </select>
                </label>
                <label>
                    <span>Currency</span>
                    <select name="currency" data-partner-invoice-currency>
                        <option value="USD">USD</option>
                        <option value="LBP">LBP</option>
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
                        <tbody data-partner-invoice-items>
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
                    <button class="button ghost small" type="button" data-partner-add-invoice-line>Add line</button>
                </div>
                <label>
                    <span>Total</span>
                    <input type="text" data-partner-invoice-total readonly>
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
                    <button class="button primary small" type="submit" data-partner-invoice-submit>Add invoice</button>
                    <button class="button ghost small is-hidden" type="button" data-partner-invoice-cancel-edit>
                        Cancel edit
                    </button>
                </div>
            </form>
            <div class="notice-stack" data-partner-invoice-status></div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Invoices</h3>
                <p>Outstanding balances and invoice history.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Shipment</th>
                        <th>Status</th>
                        <th>Currency</th>
                        <th>Total</th>
                        <th>Due</th>
                        <th>Issued</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-partner-invoices>
                    <tr><td colspan="8" class="muted">Loading invoices...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-partner-invoices-pagination>
            <button class="button ghost small" type="button" data-partner-invoices-prev>Previous</button>
            <span class="page-label" data-partner-invoices-page>Page 1</span>
            <button class="button ghost small" type="button" data-partner-invoices-next>Next</button>
        </div>
    </section>

    <?php if ($canEdit): ?>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Record transaction</h3>
                    <p>Log receipts, refunds, or adjustments for this partner.</p>
                </div>
            </div>
            <form class="grid-form" data-partner-transaction-form>
                <label>
                    <span>Type</span>
                    <select name="type">
                        <option value="receipt">Receipt</option>
                        <option value="refund">Refund</option>
                        <option value="adjustment">Adjustment</option>
                    </select>
                </label>
                <label class="is-hidden" data-partner-reason-field>
                    <span>Reason</span>
                    <select name="reason" data-partner-transaction-reason>
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
                    <select name="invoice_id" data-partner-invoice-select>
                        <option value="">Apply to balance only</option>
                    </select>
                </label>
                <label>
                    <span>Admin account</span>
                    <select name="admin_account_id" data-partner-payment-account required>
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
                        <tbody data-partner-transaction-items>
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
                    <button class="button ghost small" type="button" data-partner-add-transaction-line>Add line</button>
                </div>
                <label>
                    <span>Total</span>
                    <input type="text" data-partner-transaction-total readonly>
                </label>
                <label>
                    <span>Payment date</span>
                    <input type="date" name="payment_date">
                </label>
                <label class="full">
                    <span>Note</span>
                    <input type="text" name="note" placeholder="Optional note">
                </label>
                <button class="button primary small" type="submit">Add transaction</button>
            </form>
            <div class="notice-stack" data-partner-transaction-status></div>
        </section>
    <?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Transactions</h3>
                    <p>Receipts, refunds, and adjustments for this partner.</p>
                </div>
            </div>
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
                <tbody data-partner-transactions>
                    <tr><td colspan="10" class="muted">Loading transactions...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-partner-transactions-pagination>
            <button class="button ghost small" type="button" data-partner-transactions-prev>Previous</button>
            <span class="page-label" data-partner-transactions-page>Page 1</span>
            <button class="button ghost small" type="button" data-partner-transactions-next>Next</button>
        </div>
    </section>
</div>
<?php
internal_page_end();
?>
