<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Owner'], true)) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin and Owner roles can view branch balances.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}

internal_page_start($user, 'balances', 'Branch balances', 'Balances and pending customer amounts by branch.');
?>
<div data-balances-page>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Filters</h3>
                <p>Search branches and countries.</p>
            </div>
        </div>
        <form class="filter-bar" data-balances-filter>
            <input type="text" name="q" placeholder="Branch name">
            <select name="type">
                <option value="">All types</option>
                <option value="main">Main</option>
                <option value="head">Head</option>
                <option value="sub">Sub</option>
                <option value="warehouse">Warehouse</option>
            </select>
            <select name="country_id" data-balances-country>
                <option value="">All countries</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-balances-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Branch balances</h3>
                <p>View balances and outstanding customer totals.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Country</th>
                        <th>Balance</th>
                        <th>Customer due</th>
                        <th>Customers with balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody data-balances-table>
                    <tr>
                        <td colspan="7" class="muted">Loading branches...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-balances-pagination>
            <button class="button ghost small" type="button" data-balances-prev>Previous</button>
            <span class="page-label" data-balances-page>Page 1</span>
            <button class="button ghost small" type="button" data-balances-next>Next</button>
        </div>
        <div class="notice-stack" data-balances-status></div>
    </section>
</div>
<?php
internal_page_end();
