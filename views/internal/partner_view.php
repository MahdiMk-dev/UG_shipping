<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
$canEdit = in_array($role, ['Admin', 'Owner'], true);
$partnerId = $_GET['id'] ?? null;
internal_page_start($user, 'partners', 'Partner Profile', 'Balances and ledger activity for partner accounts.');
if (!in_array($role, ['Admin', 'Owner', 'Main Branch', 'Sub Branch'], true)) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin, Owner, Main Branch, and Sub Branch roles can view Partners.</p>
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
    <section class="panel profile-panel">
        <div class="panel-header">
            <div>
                <h3>Partner details</h3>
                <p>Contact information and signed balance status.</p>
            </div>
        </div>
        <div class="profile-row">
            <article class="detail-card">
                <h3>Partner info</h3>
                <div class="detail-list">
                    <div><span>Name</span><strong data-partner-detail="name">--</strong></div>
                    <div><span>Type</span><strong data-partner-detail="type">--</strong></div>
                    <div><span>Status</span><strong data-partner-detail="status">--</strong></div>
                    <div><span>Phone</span><strong data-partner-detail="phone">--</strong></div>
                    <div><span>Email</span><strong data-partner-detail="email">--</strong></div>
                    <div><span>Address</span><strong data-partner-detail="address">--</strong></div>
                    <div><span>Opening balance</span><strong data-partner-detail="opening_balance">--</strong></div>
                    <div><span>Current balance</span><strong data-partner-detail="current_balance">--</strong></div>
                </div>
            </article>
            <article class="detail-card">
                <h3>Balance status</h3>
                <div class="detail-list">
                    <div><span>Label</span><strong data-partner-balance-label>--</strong></div>
                    <div><span>As of</span><strong data-partner-balance-date>--</strong></div>
                </div>
            </article>
        </div>
        <div class="notice-stack" data-partner-status></div>
    </section>

    <?php if ($canEdit): ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Update partner</h3>
                <p>Edit contact details or status.</p>
            </div>
        </div>
        <form class="grid-form" data-partner-update-form>
            <input type="hidden" name="id" value="<?= htmlspecialchars((string) $partnerId, ENT_QUOTES) ?>">
            <label>
                <span>Type</span>
                <select name="type" required>
                    <option value="">Select type</option>
                    <option value="person">Person</option>
                    <option value="company">Company</option>
                    <option value="exchanger">Exchanger</option>
                    <option value="other">Other</option>
                </select>
            </label>
            <label>
                <span>Name</span>
                <input type="text" name="name" required>
            </label>
            <label>
                <span>Phone</span>
                <input type="text" name="phone">
            </label>
            <label>
                <span>Email</span>
                <input type="email" name="email">
            </label>
            <label class="full">
                <span>Address</span>
                <input type="text" name="address">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </label>
            <div class="full">
                <button class="button primary small" type="submit">Save changes</button>
            </div>
        </form>
        <div class="notice-stack" data-partner-update-status></div>
    </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Quick actions</h3>
                <p>Record partner ledger events and cash movements.</p>
            </div>
        </div>
        <form class="grid-form" data-partner-tx-form>
            <label>
                <span>Transaction type</span>
                <select name="tx_type" required>
                    <option value="WE_OWE_PARTNER">We owe partner (payable)</option>
                    <option value="PARTNER_OWES_US">Partner owes us (receivable/refund)</option>
                    <option value="WE_PAY_PARTNER">We pay partner</option>
                    <option value="PARTNER_PAYS_US">Partner pays us</option>
                    <option value="ADJUST_PLUS">Adjustment (increase payable)</option>
                    <option value="ADJUST_MINUS">Adjustment (increase receivable/refund)</option>
                </select>
            </label>
            <label>
                <span>Currency</span>
                <input type="text" name="currency_code" value="USD" maxlength="3" required>
            </label>
            <label>
                <span>Amount</span>
                <input type="number" step="0.01" name="amount" required>
            </label>
            <label class="full">
                <span>Description</span>
                <input type="text" name="description" placeholder="Optional description">
            </label>
            <label>
                <span>Transaction date</span>
                <input type="datetime-local" name="tx_date">
            </label>
            <label>
                <span>Reference no</span>
                <input type="text" name="reference_no">
            </label>
            <label data-partner-from-account>
                <span>From admin account</span>
                <select name="from_admin_account_id">
                    <option value="">Select account</option>
                </select>
            </label>
            <label data-partner-to-account>
                <span>To admin account</span>
                <select name="to_admin_account_id">
                    <option value="">Select account</option>
                </select>
            </label>
            <div class="full">
                <button class="button primary small" type="submit">Record transaction</button>
            </div>
        </form>
        <div class="notice-stack" data-partner-tx-status></div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Partner to partner transfer</h3>
                <p>Move obligations between partners without affecting admin accounts.</p>
            </div>
        </div>
        <form class="grid-form" data-partner-transfer-form>
            <label>
                <span>From partner</span>
                <select name="from_partner_id" required>
                    <option value="">Select partner</option>
                </select>
            </label>
            <label>
                <span>To partner</span>
                <select name="to_partner_id" required>
                    <option value="">Select partner</option>
                </select>
            </label>
            <label>
                <span>Currency</span>
                <input type="text" name="currency_code" value="USD" maxlength="3" required>
            </label>
            <label>
                <span>Amount</span>
                <input type="number" step="0.01" name="amount" required>
            </label>
            <label class="full">
                <span>Description</span>
                <input type="text" name="description" placeholder="Optional description">
            </label>
            <label>
                <span>Transfer date</span>
                <input type="datetime-local" name="tx_date">
            </label>
            <div class="full">
                <button class="button primary small" type="submit">Record transfer</button>
            </div>
        </form>
        <div class="notice-stack" data-partner-transfer-status></div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Transactions</h3>
                <p>Movement is non-cash obligations; payment is cash in/out.</p>
            </div>
        </div>
        <form class="filter-bar" data-partner-statement-filter>
            <input type="date" name="from" placeholder="From">
            <input type="date" name="to" placeholder="To">
            <select name="status">
                <option value="posted">Posted</option>
                <option value="voided">Voided</option>
                <option value="all">All</option>
            </select>
            <button class="button primary" type="submit">Filter</button>
            <button class="button ghost" type="button" data-partner-statement-refresh>Refresh</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Movement</th>
                        <th>Payment</th>
                        <th>Admin account</th>
                        <th>Description</th>
                        <th>Status</th>
                        <?php if ($canEdit): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody data-partner-transactions>
                    <tr><td colspan="<?= $canEdit ? '8' : '7' ?>" class="muted">Loading transactions...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <button class="button ghost small" type="button" data-partner-transactions-prev>Previous</button>
            <span class="page-label" data-partner-transactions-page>Page 1</span>
            <button class="button ghost small" type="button" data-partner-transactions-next>Next</button>
        </div>
        <div class="notice-stack" data-partner-transactions-status></div>
    </section>
</div>
<?php
internal_page_end();
?>
