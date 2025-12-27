<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch'], true);
internal_page_start($user, 'partners', 'Partners', 'Manage shipper and consignee profiles.');
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
<div data-partners-page data-can-edit="<?= $canEdit ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Partner filters</h3>
                <p>Search by name, phone, or country.</p>
            </div>
        </div>
        <form class="filter-bar" data-partners-filter>
            <input type="text" name="q" placeholder="Partner name or phone">
            <select name="type">
                <option value="">All types</option>
                <option value="shipper">Shipper</option>
                <option value="consignee">Consignee</option>
            </select>
            <select name="country_id" data-country-filter>
                <option value="">All countries</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-partners-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Partner profiles</h3>
                <p>Active shipper and consignee contacts.</p>
            </div>
            <?php if ($canEdit): ?>
                <button class="button ghost small" type="button" data-partners-add>Add partner</button>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Country</th>
                        <th>Phone</th>
                        <th>Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-partners-table>
                    <tr>
                        <td colspan="6" class="muted">Loading partners...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-partners-pagination>
            <button class="button ghost small" type="button" data-partners-prev>Previous</button>
            <span class="page-label" data-partners-page>Page 1</span>
            <button class="button ghost small" type="button" data-partners-next>Next</button>
        </div>
        <div class="notice-stack" data-partners-status></div>
    </section>

    <?php if ($canEdit): ?>
        <div class="drawer" data-partners-drawer>
            <div class="drawer-scrim" data-partners-drawer-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="partner-form-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="partner-form-title" data-partners-form-title>Add partner</h3>
                        <p>Save shipper or consignee profiles for shipments.</p>
                    </div>
                    <button class="icon-button" type="button" data-partners-drawer-close aria-label="Close partner panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-partners-form>
                    <input type="hidden" name="partner_id" data-partner-id>
                    <label>
                        <span>Type</span>
                        <select name="type" required>
                            <option value="">Select type</option>
                            <option value="shipper">Shipper</option>
                            <option value="consignee">Consignee</option>
                        </select>
                    </label>
                    <label>
                        <span>Name</span>
                        <input type="text" name="name" required>
                    </label>
                    <label>
                        <span>Country</span>
                        <select name="country_id" data-country-select required>
                            <option value="">Select country</option>
                        </select>
                    </label>
                    <label>
                        <span>Phone</span>
                        <input type="text" name="phone">
                    </label>
                    <label class="full">
                        <span>Address</span>
                        <input type="text" name="address" placeholder="Optional address">
                    </label>
                    <button class="button primary small" type="submit" data-partners-submit-label>Add partner</button>
                </form>
                <div class="notice-stack" data-partners-form-status></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
internal_page_end();
?>
