<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Owner', 'Sub Branch'], true)) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin, Owner, and Sub Branch roles can view balances.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}

internal_page_start($user, 'customer_balances', 'Customer balances', 'Accounts with outstanding balances.');
?>
<div data-customer-balances-page>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Filters</h3>
                <p>Search by customer name, code, or phone.</p>
            </div>
        </div>
        <form class="filter-bar" data-customer-balances-filter>
            <input type="text" name="q" placeholder="Search customers">
            <?php if (in_array($role, ['Admin', 'Owner'], true)): ?>
                <select name="sub_branch_id" data-customer-balances-branch>
                    <option value="">All branches</option>
                </select>
            <?php endif; ?>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-customer-balances-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Balances</h3>
                <p>Grouped by customer account.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th>Profiles</th>
                        <th>Countries</th>
                        <th>Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody data-customer-balances-table>
                    <tr>
                        <td colspan="6" class="muted">Loading balances...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-customer-balances-pagination>
            <button class="button ghost small" type="button" data-customer-balances-prev>Previous</button>
            <span class="page-label" data-customer-balances-page>Page 1</span>
            <button class="button ghost small" type="button" data-customer-balances-next>Next</button>
        </div>
        <div class="notice-stack" data-customer-balances-status></div>
    </section>
</div>
<?php
internal_page_end();
