<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner'], true);
$createMode = isset($_GET['create']);
$pageTitle = $createMode ? 'Create User' : 'Users';
$pageSubtitle = $createMode
    ? 'Add a new internal user.'
    : 'Control internal access and branch assignments.';
internal_page_start($user, 'users', $pageTitle, $pageSubtitle);
if (!$canEdit) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin and Owner roles can view users.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-users-page data-can-edit="<?= $canEdit ? '1' : '0' ?>" data-create-mode="<?= $createMode ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>User search</h3>
                <p>Filter by role, branch, or username.</p>
            </div>
        </div>
        <form class="filter-bar" data-users-filter>
            <input type="text" name="q" placeholder="Username or name">
            <select name="role_id" data-user-role-filter>
                <option value="">All roles</option>
            </select>
            <select name="branch_id" data-user-branch-filter>
                <option value="">All branches</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-users-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Team members</h3>
                <p>Assign roles, reset passwords, and manage access.</p>
            </div>
            <?php if ($canEdit): ?>
                <button class="button ghost small" type="button" data-user-add>Add user</button>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Contact</th>
                        <?php if ($canEdit): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody data-users-table>
                    <tr>
                        <td colspan="<?= $canEdit ? 6 : 5 ?>" class="muted">Loading users...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-users-pagination>
            <button class="button ghost small" type="button" data-users-prev>Previous</button>
            <span class="page-label" data-users-page-label>Page 1</span>
            <button class="button ghost small" type="button" data-users-next>Next</button>
        </div>
        <div class="notice-stack" data-users-status></div>
    </section>

    <?php if ($canEdit): ?>
        <div class="drawer" data-user-drawer>
            <div class="drawer-scrim" data-user-drawer-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="user-form-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="user-form-title" data-user-form-title>Add user</h3>
                        <p>Create new accounts and manage access roles.</p>
                    </div>
                    <button class="icon-button" type="button" data-user-drawer-close aria-label="Close user panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-user-form>
                    <input type="hidden" name="user_id" data-user-id>
                    <label>
                        <span>Name</span>
                        <input type="text" name="name" required>
                    </label>
                    <label>
                        <span>Username</span>
                        <input type="text" name="username" required>
                    </label>
                    <label>
                        <span>Password</span>
                        <input type="password" name="password" autocomplete="new-password">
                        <small class="muted">Leave blank to keep the current password.</small>
                    </label>
                    <label>
                        <span>Role</span>
                        <select name="role_id" data-user-role required>
                            <option value="">Select role</option>
                        </select>
                    </label>
                    <label>
                        <span>Branch</span>
                        <select name="branch_id" data-user-branch>
                            <option value="">No branch</option>
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
                    <button class="button primary small" type="submit" data-user-submit-label>Add user</button>
                </form>
                <div class="notice-stack" data-user-form-status></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
internal_page_end();
