<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
$canCreate = in_array($role, ['Admin', 'Owner'], true);
internal_page_start($user, 'partners', 'Partners', 'Track partner balances and ledger activity.');
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
<div data-partners-page data-can-create="<?= $canCreate ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Partner filters</h3>
                <p>Search by name, phone, or email.</p>
            </div>
        </div>
        <form class="filter-bar" data-partners-filter>
            <input type="text" name="q" placeholder="Partner name, phone, or email">
            <select name="type">
                <option value="">All types</option>
                <option value="person">Person</option>
                <option value="company">Company</option>
                <option value="exchanger">Exchanger</option>
                <option value="other">Other</option>
            </select>
            <select name="status">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-partners-refresh>Refresh</button>
            <?php if ($canCreate): ?>
                <a class="button ghost" href="<?= BASE_URL ?>/views/internal/partner_create">Create Partner</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Partner list</h3>
                <p>Balances follow the signed convention: positive = payable, negative = receivable.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-partners-table>
                    <tr><td colspan="5" class="muted">Loading partners...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <button class="button ghost small" type="button" data-partners-prev>Previous</button>
            <span class="page-label" data-partners-page-label>Page 1</span>
            <button class="button ghost small" type="button" data-partners-next>Next</button>
        </div>
    </section>

    <div class="notice-stack" data-partners-status></div>
</div>
<?php
internal_page_end();
?>
