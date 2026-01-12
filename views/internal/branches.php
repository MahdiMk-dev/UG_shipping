<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner'], true);
$createMode = isset($_GET['create']);
$pageTitle = $createMode ? 'Create Branch' : 'Branches';
$pageSubtitle = $createMode
    ? 'Add a new branch location.'
    : 'Maintain main, head, sub, and warehouse locations.';
internal_page_start($user, 'branches', $pageTitle, $pageSubtitle);
if (!$canEdit) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin and Owner roles can view branches.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-branches-page data-can-edit="<?= $canEdit ? '1' : '0' ?>" data-create-mode="<?= $createMode ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Branch filters</h3>
                <p>Search by branch name, type, or country.</p>
            </div>
        </div>
        <form class="filter-bar" data-branches-filter>
            <input type="text" name="q" placeholder="Branch name">
            <select name="type" data-branch-type-filter>
                <option value="">All types</option>
                <option value="main">Main</option>
                <option value="head">Head</option>
                <option value="sub">Sub</option>
                <option value="warehouse">Warehouse</option>
            </select>
            <select name="country_id" data-branch-country-filter>
                <option value="">All countries</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-branches-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Branches</h3>
                <p>Manage locations, phone, and address details.</p>
            </div>
            <?php if ($canEdit): ?>
                <button class="button ghost small" type="button" data-branch-payment-open>Record payment</button>
                <button class="button ghost small" type="button" data-branch-add>Add branch</button>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Country</th>
                        <th>Parent</th>
                        <th>Contact</th>
                        <th>Balance</th>
                        <?php if ($canEdit): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody data-branches-table>
                    <tr>
                        <td colspan="<?= $canEdit ? 7 : 6 ?>" class="muted">Loading branches...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-branches-pagination>
            <button class="button ghost small" type="button" data-branches-prev>Previous</button>
            <span class="page-label" data-branches-page-label>Page 1</span>
            <button class="button ghost small" type="button" data-branches-next>Next</button>
        </div>
        <div class="notice-stack" data-branches-status></div>
    </section>

    <?php if ($canEdit): ?>
        <div class="drawer" data-branch-drawer>
            <div class="drawer-scrim" data-branch-drawer-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="branch-form-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="branch-form-title" data-branch-form-title>Add branch</h3>
                        <p>Capture location details and assignments.</p>
                    </div>
                    <button class="icon-button" type="button" data-branch-drawer-close aria-label="Close branch panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-branch-form>
                    <input type="hidden" name="branch_id" data-branch-id>
                    <label>
                        <span>Name</span>
                        <input type="text" name="name" required>
                    </label>
                    <label>
                        <span>Type</span>
                        <select name="type" data-branch-type required>
                            <option value="main">Main</option>
                            <option value="head">Head</option>
                            <option value="sub">Sub</option>
                            <option value="warehouse">Warehouse</option>
                        </select>
                    </label>
                    <label>
                        <span>Country</span>
                        <select name="country_id" data-branch-country required>
                            <option value="">Select country</option>
                        </select>
                        <small class="muted is-hidden" data-branch-country-note>Warehouse branches must select a country.</small>
                    </label>
                    <label>
                        <span>Parent branch</span>
                        <select name="parent_branch_id" data-branch-parent>
                            <option value="">No parent</option>
                        </select>
                    </label>
                    <label>
                        <span>Phone</span>
                        <input type="text" name="phone">
                    </label>
                    <label class="full">
                        <span>Address</span>
                        <input type="text" name="address">
                    </label>
                    <button class="button primary small" type="submit" data-branch-submit-label>Add branch</button>
                </form>
                <div class="notice-stack" data-branch-form-status></div>
            </div>
        </div>
        <div class="drawer" data-branch-payment-drawer>
            <div class="drawer-scrim" data-branch-payment-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="branch-payment-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="branch-payment-title">Record branch payment</h3>
                        <p>Track payments from sub branches to admin accounts.</p>
                    </div>
                    <button class="icon-button" type="button" data-branch-payment-close aria-label="Close branch payment panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-branch-payment-form>
                    <label>
                        <span>From branch</span>
                        <select name="from_branch_id" data-branch-payment-from required>
                            <option value="">Select sub branch</option>
                        </select>
                    </label>
                    <label>
                        <span>From account</span>
                        <select name="from_account_id" data-branch-payment-from-account required>
                            <option value="">Select branch account</option>
                        </select>
                    </label>
                    <label>
                        <span>To admin account</span>
                        <select name="to_account_id" data-branch-payment-to-account required>
                            <option value="">Select admin account</option>
                        </select>
                    </label>
                    <label>
                        <span>Amount</span>
                        <input type="number" step="0.01" name="amount" required>
                    </label>
                    <label>
                        <span>Payment date</span>
                        <input type="date" name="transfer_date">
                    </label>
                    <label>
                        <span>Description</span>
                        <select name="description" data-branch-payment-description>
                            <option value="">Select description</option>
                            <option value="Daily closing">Daily closing</option>
                            <option value="Advance payment">Advance payment</option>
                            <option value="Customer settlements">Customer settlements</option>
                            <option value="Other">Other</option>
                        </select>
                    </label>
                    <label class="full">
                        <span>Note</span>
                        <input type="text" name="note" placeholder="Optional note">
                    </label>
                    <button class="button primary small" type="submit">Record payment</button>
                </form>
                <div class="notice-stack" data-branch-payment-status></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
internal_page_end();
