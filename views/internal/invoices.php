<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
$canView = in_array($role, ['Admin', 'Owner', 'Main Branch', 'Sub Branch'], true);
$canEdit = in_array($role, ['Admin', 'Owner', 'Main Branch', 'Sub Branch'], true);

internal_page_start($user, 'invoices', 'Invoices', 'Issue and track customer invoices.');
if (!$canView) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Invoices are available to Admin, Owner, Main Branch, and Sub Branch roles.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-invoices-page data-can-edit="<?= $canEdit ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Invoice filters</h3>
                <p>Search by invoice number, status, or customer.</p>
            </div>
        </div>
        <form class="filter-bar" data-invoices-filter>
            <input type="text" name="q" placeholder="Invoice number">
            <input type="text" data-invoices-customer-input list="invoice-customer-options"
                   placeholder="Customer name or code">
            <input type="hidden" name="customer_id" data-invoices-customer-id>
            <select name="status">
                <option value="">All statuses</option>
                <option value="open">Open</option>
                <option value="partially_paid">Partially paid</option>
                <option value="paid">Paid</option>
                <option value="void">Canceled</option>
            </select>
            <select name="branch_id" data-branch-filter>
                <option value="">All branches</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-invoices-refresh>Refresh</button>
        </form>
        <datalist id="invoice-customer-options"></datalist>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Invoices</h3>
                <p>Invoice history and outstanding balances.</p>
            </div>
            <?php if ($canEdit): ?>
                <button class="button ghost small" type="button" data-invoices-add>New invoice</button>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Currency</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Issued</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-invoices-table>
                    <tr>
                        <td colspan="10" class="muted">Loading invoices...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-invoices-pagination>
            <button class="button ghost small" type="button" data-invoices-prev>Previous</button>
            <span class="page-label" data-invoices-page>Page 1</span>
            <button class="button ghost small" type="button" data-invoices-next>Next</button>
        </div>
        <div class="notice-stack" data-invoices-status></div>
    </section>

    <?php if ($canEdit): ?>
        <div class="drawer" data-invoices-drawer>
            <div class="drawer-scrim" data-invoices-drawer-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="invoice-form-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="invoice-form-title" data-invoice-form-title>Create invoice</h3>
                        <p>Select un-invoiced orders and issue a statement.</p>
                    </div>
                    <button class="icon-button" type="button" data-invoices-drawer-close aria-label="Close invoice panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-invoices-form>
                    <input type="hidden" name="invoice_id" data-invoice-id>
                    <input type="hidden" name="customer_id" data-invoice-customer-id>
                    <input type="hidden" name="branch_id" data-invoice-branch-id>
                    <label>
                        <span>Customer</span>
                        <input type="text" data-invoice-customer-input list="invoice-create-customer-options"
                               placeholder="Search by name or code" required>
                    </label>
                    <label>
                        <span>Branch</span>
                        <input type="text" data-invoice-branch-label placeholder="Auto from orders" readonly>
                    </label>
                    <label class="full">
                        <span>Delivery method</span>
                        <div class="option-group option-group-equal invoice-delivery-toggle">
                            <label class="option-pill">
                                <input type="radio" name="delivery_type" value="delivery" required>
                                <span>
                                    <svg class="pill-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 7h11v7H3z"></path>
                                        <path d="M14 10h4l3 3v1h-7z"></path>
                                        <path d="M6.5 17.5a1.5 1.5 0 1 1-3 0"></path>
                                        <path d="M17.5 17.5a1.5 1.5 0 1 1-3 0"></path>
                                    </svg>
                                    Delivery
                                </span>
                            </label>
                            <label class="option-pill">
                                <input type="radio" name="delivery_type" value="pickup" required>
                                <span>
                                    <svg class="pill-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 7l9-4 9 4-9 4-9-4z"></path>
                                        <path d="M3 7v10l9 4 9-4V7"></path>
                                        <path d="M12 11v10"></path>
                                    </svg>
                                    Pickup
                                </span>
                            </label>
                        </div>
                    </label>
                    <label>
                        <span>Invoice number (optional)</span>
                        <input type="text" name="invoice_no" placeholder="Auto-generated if empty">
                    </label>
                    <label>
                        <span>Currency</span>
                        <select name="currency" data-invoice-currency>
                            <option value="USD">USD</option>
                            <option value="LBP">LBP</option>
                        </select>
                    </label>
                    <label>
                        <span>Issued at</span>
                        <input type="datetime-local" name="issued_at">
                    </label>
                    <label class="full">
                        <span>Note</span>
                        <input type="text" name="note" placeholder="Optional note">
                    </label>
                    <label>
                        <span>Use points</span>
                        <input type="number" name="points_used" min="0" step="1" placeholder="0" data-invoice-points-input>
                    </label>
                    <label>
                        <span>Available points</span>
                        <input type="text" value="0" readonly data-invoice-points-available>
                    </label>
                    <div class="full muted" data-invoice-points-summary>
                        Points discount: 0.00 | Due after points: 0.00 | Point value: 0.00
                    </div>
                    <div class="full">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" data-invoice-orders-all></th>
                                        <th>Tracking</th>
                                        <th>Shipment</th>
                                        <th>Total</th>
                                        <th>Received</th>
                                        <th>Branch</th>
                                    </tr>
                                </thead>
                                <tbody data-invoice-orders-table>
                                    <tr>
                                        <td colspan="6" class="muted">Select a customer to load orders.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="muted" data-invoice-orders-total>Selected total: 0.00</div>
                    </div>
                    <div class="full">
                        <button class="button primary small" type="submit" data-invoice-submit>Create invoice</button>
                        <button class="button ghost small is-hidden" type="button" data-invoice-edit-cancel>
                            Cancel edit
                        </button>
                    </div>
                </form>
                <datalist id="invoice-create-customer-options"></datalist>
                <div class="notice-stack" data-invoice-form-status></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
internal_page_end();
?>
