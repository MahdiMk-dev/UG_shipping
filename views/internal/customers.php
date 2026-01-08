<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
$canCreateCustomer = in_array($role, ['Admin', 'Owner', 'Main Branch', 'Sub Branch'], true);
$showBalance = $role !== 'Warehouse';
internal_page_start($user, 'customers', 'Customers', 'Manage customer accounts and profiles.');
?>
<div data-customers-page>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Customer directory</h3>
                <p>Search by name, code, branch, or profile country.</p>
            </div>
        </div>
        <form class="filter-bar" data-customers-filter>
            <input type="text" name="q" placeholder="Customer name or code">
            <select name="sub_branch_id" data-branch-filter>
                <option value="">All branches</option>
            </select>
            <select name="profile_country_id" data-country-filter>
                <option value="">All profile countries</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-customers-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Customers</h3>
                <p>Accounts with shared logins and linked profiles.</p>
            </div>
            <?php if ($canCreateCustomer): ?>
                <a class="button ghost small" href="<?= BASE_URL ?>/views/internal/customer_create">Add customer</a>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Portal</th>
                        <th>Phone</th>
                        <th>Branch</th>
                        <th>Profiles</th>
                        <th>Countries</th>
                        <?php if ($showBalance): ?>
                            <th>Balance</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-customers-table>
                    <tr>
                        <td colspan="<?= $showBalance ? 8 : 7 ?>" class="muted">Loading customers...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-customers-pagination>
            <button class="button ghost small" type="button" data-customers-prev>Previous</button>
            <span class="page-label" data-customers-page>Page 1</span>
            <button class="button ghost small" type="button" data-customers-next>Next</button>
        </div>
        <div class="notice-stack" data-customers-status></div>
    </section>

    <div class="drawer" data-customer-profiles-drawer>
        <div class="drawer-scrim" data-customer-profiles-close></div>
        <div class="drawer-panel">
            <div class="drawer-header">
                <div>
                    <h3 data-customer-profiles-title>Customer profiles</h3>
                    <p class="muted" data-customer-profiles-subtitle>Details for selected account.</p>
                </div>
                <button class="button ghost small" type="button" data-customer-profiles-close>Close</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Orders</th>
                            <th>Created</th>
                            <th>Code</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody data-customer-profiles-table>
                        <tr><td colspan="5" class="muted">Select a customer to view profiles.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
internal_page_end();
