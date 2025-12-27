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
                    <div><span>Country</span><strong data-partner-detail="country_name">--</strong></div>
                    <div><span>Phone</span><strong data-partner-detail="phone">--</strong></div>
                    <div><span>Address</span><strong data-partner-detail="address">--</strong></div>
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
                    <p>Record charges for the partner.</p>
                </div>
            </div>
            <form class="grid-form" data-partner-invoice-form>
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
                    <span>Invoice #</span>
                    <input type="text" name="invoice_no" placeholder="Auto-generate if blank">
                </label>
                <label>
                    <span>Total</span>
                    <input type="number" step="0.01" name="total" placeholder="0.00" required>
                </label>
                <label>
                    <span>Issued at</span>
                    <input type="datetime-local" name="issued_at">
                </label>
                <label class="full">
                    <span>Note</span>
                    <input type="text" name="note" placeholder="Optional note">
                </label>
                <button class="button primary small" type="submit">Add invoice</button>
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
                        <th>Total</th>
                        <th>Due</th>
                        <th>Issued</th>
                        <th>Print</th>
                    </tr>
                </thead>
                <tbody data-partner-invoices>
                    <tr><td colspan="7" class="muted">Loading invoices...</td></tr>
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
                    <h3>Record receipt</h3>
                    <p>Log payments received from the partner.</p>
                </div>
            </div>
            <form class="grid-form" data-partner-transaction-form>
                <label>
                    <span>Invoice</span>
                    <select name="invoice_id" data-partner-invoice-select>
                        <option value="">Apply to balance only</option>
                    </select>
                </label>
                <label>
                    <span>Branch</span>
                    <select name="branch_id" data-branch-select required>
                        <option value="">Select branch</option>
                    </select>
                </label>
                <label>
                    <span>Payment method</span>
                    <select name="payment_method_id" data-payment-method-select required>
                        <option value="">Select method</option>
                    </select>
                </label>
                <label>
                    <span>Amount</span>
                    <input type="number" step="0.01" name="amount" placeholder="0.00" required>
                </label>
                <label>
                    <span>Payment date</span>
                    <input type="date" name="payment_date">
                </label>
                <label class="full">
                    <span>Note</span>
                    <input type="text" name="note" placeholder="Optional note">
                </label>
                <button class="button primary small" type="submit">Add receipt</button>
            </form>
            <div class="notice-stack" data-partner-transaction-status></div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Transactions</h3>
                <p>Receipts and adjustments for this partner.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Invoice</th>
                        <th>Note</th>
                        <th>Print</th>
                    </tr>
                </thead>
                <tbody data-partner-transactions>
                    <tr><td colspan="7" class="muted">Loading transactions...</td></tr>
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
