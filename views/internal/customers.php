<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'customers', 'Customers', 'Manage customer profiles and balances.');
?>
<div data-customers-page>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Customer directory</h3>
                <p>Search by code, name, or branch.</p>
            </div>
        </div>
        <form class="filter-bar" data-customers-filter>
            <input type="text" name="q" placeholder="Customer name or code">
            <select name="sub_branch_id" data-branch-filter>
                <option value="">All branches</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-customers-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Customers</h3>
                <p>Active customers and system balances.</p>
            </div>
            <a class="button ghost small" href="<?= BASE_URL ?>/views/internal/customer_create">Add customer</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Branch</th>
                        <th>Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-customers-table>
                    <tr>
                        <td colspan="5" class="muted">Loading customers...</td>
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
</div>
<?php
internal_page_end();
