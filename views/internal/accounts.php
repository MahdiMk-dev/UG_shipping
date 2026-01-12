<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
$canEdit = in_array($role, ['Admin', 'Owner'], true);
$canView = in_array($role, ['Admin', 'Owner', 'Sub Branch'], true);
$createMode = isset($_GET['create']);
$pageTitle = $createMode ? 'Create Account' : 'Accounts';
$pageSubtitle = $createMode
    ? 'Add a new payment account.'
    : 'Manage payment accounts for admin and sub branches.';
internal_page_start($user, 'accounts', $pageTitle, $pageSubtitle);
if (!$canView) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin, Owner, and Sub Branch roles can view accounts.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-accounts-page data-can-edit="<?= $canEdit ? '1' : '0' ?>" data-create-mode="<?= $createMode ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Account filters</h3>
                <p>Search by account name, owner, or payment method.</p>
            </div>
        </div>
        <form class="filter-bar" data-accounts-filter>
            <input type="text" name="q" placeholder="Account name or owner">
            <select name="owner_type" data-account-owner-type-filter>
                <option value="">All owners</option>
                <option value="admin">Admin</option>
                <option value="branch">Branch</option>
            </select>
            <select name="owner_id" data-account-owner-filter class="is-hidden">
                <option value="">All owners</option>
            </select>
            <select name="payment_method_id" data-account-method-filter>
                <option value="">All methods</option>
            </select>
            <select name="is_active" data-account-status-filter>
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-accounts-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Accounts</h3>
                <p>Track balances by owner and payment method.</p>
            </div>
            <?php if ($canEdit): ?>
                <button class="button ghost small" type="button" data-account-add>Add account</button>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Owner</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Currency</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-accounts-table>
                    <tr>
                        <td colspan="8" class="muted">Loading accounts...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="notice-stack" data-accounts-status></div>
    </section>

    <section class="panel is-hidden" data-account-preview>
        <div class="panel-header">
            <div>
                <h3>Account preview</h3>
                <p>Balance and key details for the selected account.</p>
            </div>
            <button class="icon-button" type="button" data-account-preview-close aria-label="Close account preview">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
            </button>
        </div>
        <div class="detail-grid">
            <article class="detail-card">
                <h3>Account info</h3>
                <div class="detail-list">
                    <div><span>Name</span><strong data-preview="name">--</strong></div>
                    <div><span>Owner</span><strong data-preview="owner">--</strong></div>
                    <div><span>Type</span><strong data-preview="type">--</strong></div>
                    <div><span>Payment method</span><strong data-preview="method">--</strong></div>
                    <div><span>Currency</span><strong data-preview="currency">--</strong></div>
                    <div><span>Status</span><strong data-preview="status">--</strong></div>
                </div>
            </article>
            <article class="detail-card">
                <h3>Balance</h3>
                <div class="detail-list">
                    <div><span>Current</span><strong data-preview="balance">--</strong></div>
                </div>
            </article>
        </div>
    </section>

    <?php if ($canEdit): ?>
        <div class="drawer" data-account-drawer>
            <div class="drawer-scrim" data-account-drawer-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="account-form-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="account-form-title" data-account-form-title>Add account</h3>
                        <p>Create new payment accounts or deactivate old ones.</p>
                    </div>
                    <button class="icon-button" type="button" data-account-drawer-close aria-label="Close account panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-account-form>
                    <input type="hidden" name="account_id" data-account-id>
                    <label>
                        <span>Owner type</span>
                        <select name="owner_type" data-account-owner-type required>
                            <option value="admin">Admin</option>
                            <option value="branch">Branch</option>
                        </select>
                    </label>
                    <label data-account-owner-field class="is-hidden">
                        <span>Owner</span>
                        <select name="owner_id" data-account-owner>
                            <option value="">Select owner</option>
                        </select>
                    </label>
                    <label>
                        <span>Name</span>
                        <input type="text" name="name" placeholder="Auto-generated if empty">
                    </label>
                    <label>
                        <span>Payment method</span>
                        <select name="payment_method_id" data-account-method required>
                            <option value="">Select method</option>
                        </select>
                    </label>
                    <label>
                        <span>Currency</span>
                        <select name="currency" data-account-currency>
                            <option value="USD">USD</option>
                            <option value="LBP">LBP</option>
                        </select>
                    </label>
                    <label>
                        <span>Status</span>
                        <select name="is_active" data-account-active>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </label>
                    <button class="button primary small" type="submit" data-account-submit-label>Add account</button>
                </form>
                <div class="notice-stack" data-account-form-status></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
internal_page_end();
?>
