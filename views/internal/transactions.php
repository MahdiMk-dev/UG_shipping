<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
$canCancel = in_array($role, ['Admin', 'Owner', 'Main Branch', 'Sub Branch'], true);
internal_page_start($user, 'transactions', 'Transactions', 'Review customer payments by date.');
?>
<div data-transactions-page data-can-cancel="<?= $canCancel ? '1' : '0' ?>">
    <section class="panel detail-grid is-hidden" data-branch-balance-panel>
        <article class="detail-card">
            <h3>Branch balance</h3>
            <div class="detail-list">
                <div><span>Current</span><strong data-branch-balance>--</strong></div>
                <div><span>Meaning</span><strong>Due (+) / Credit (-)</strong></div>
            </div>
        </article>
    </section>
    <section class="panel is-hidden" data-customer-balance-panel>
        <div class="panel-header">
            <div>
                <h3>Customers with balances</h3>
                <p>Customers in your branch with non-zero balances.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Code</th>
                        <th>Phone</th>
                        <th>Balance</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody data-customer-balance-table>
                    <tr><td colspan="5" class="muted">Loading customers...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Transaction filters</h3>
                <p>Select a date range to load payments.</p>
            </div>
        </div>
        <form class="filter-bar" data-transactions-filter>
            <label class="inline-field">
                <span>From</span>
                <input type="date" name="date_from" data-transactions-from required>
            </label>
            <label class="inline-field">
                <span>To</span>
                <input type="date" name="date_to" data-transactions-to required>
            </label>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-transactions-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Transactions</h3>
                <p>Payments recorded within the selected dates.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Account</th>
                        <th>Currency</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th>Note</th>
                        <th>Receipt</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-transactions-table>
                    <tr><td colspan="12" class="muted">Select a date range to load transactions.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="notice-stack" data-transactions-status></div>
    </section>
</div>
<?php
internal_page_end();
?>
