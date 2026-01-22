<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch'], true);
$createMode = isset($_GET['create']);
$pageTitle = $createMode ? 'Create Supplier' : 'Suppliers';
$pageSubtitle = $createMode
    ? 'Add a new shipper or consignee profile.'
    : 'Manage shipper and consignee profiles.';
internal_page_start($user, 'suppliers', $pageTitle, $pageSubtitle);
if (!in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch'], true)) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin, Owner, and Main Branch roles can view Suppliers.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-suppliers-page data-can-edit="<?= $canEdit ? '1' : '0' ?>" data-create-mode="<?= $createMode ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Supplier filters</h3>
                <p>Search by name, phone, or address.</p>
            </div>
        </div>
        <form class="filter-bar" data-suppliers-filter>
            <input type="text" name="q" placeholder="Supplier name or phone">
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-suppliers-refresh>Refresh</button>
            <?php if ($canEdit): ?>
                <button class="button ghost" type="button" data-suppliers-add>Add Supplier</button>
            <?php endif; ?>
        </form>
    </section>

    <div class="panel-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Shippers</h3>
                    <p>Shipper profiles and balances.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody data-suppliers-table-shipper>
                        <tr>
                            <td colspan="4" class="muted">Loading shippers...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="table-pagination" data-suppliers-pagination-shipper>
                <button class="button ghost small" type="button" data-suppliers-prev-shipper>Previous</button>
                <span class="page-label" data-suppliers-page-shipper>Page 1</span>
                <button class="button ghost small" type="button" data-suppliers-next-shipper>Next</button>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Consignees</h3>
                    <p>Consignee profiles and balances.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody data-suppliers-table-consignee>
                        <tr>
                            <td colspan="4" class="muted">Loading consignees...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="table-pagination" data-suppliers-pagination-consignee>
                <button class="button ghost small" type="button" data-suppliers-prev-consignee>Previous</button>
                <span class="page-label" data-suppliers-page-consignee>Page 1</span>
                <button class="button ghost small" type="button" data-suppliers-next-consignee>Next</button>
            </div>
        </section>
    </div>

    <div class="notice-stack" data-suppliers-status></div>

    <?php if ($canEdit): ?>
        <div class="drawer" data-suppliers-drawer>
            <div class="drawer-scrim" data-suppliers-drawer-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="Supplier-form-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="Supplier-form-title" data-suppliers-form-title>Add Supplier</h3>
                        <p>Save shipper or consignee profiles for shipments.</p>
                    </div>
                    <button class="icon-button" type="button" data-suppliers-drawer-close aria-label="Close Supplier panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-suppliers-form>
                    <input type="hidden" name="supplier_id" data-supplier-id>
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
                        <span>Phone</span>
                        <input type="text" name="phone">
                    </label>
                    <label class="full">
                        <span>Address</span>
                        <input type="text" name="address" placeholder="Optional address">
                    </label>
                    <label class="full">
                        <span>Notes</span>
                        <input type="text" name="note" placeholder="Optional notes">
                    </label>
                    <button class="button primary small" type="submit" data-suppliers-submit-label>Add Supplier</button>
                </form>
                <div class="notice-stack" data-suppliers-form-status></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
internal_page_end();
?>



