<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Sub Branch'], true);
$createMode = isset($_GET['create']);
$pageTitle = $createMode ? 'Create Staff' : 'Staff';
$pageSubtitle = $createMode
    ? 'Add a new staff member.'
    : 'Manage staff profiles, salaries, and expenses.';
internal_page_start($user, 'staff', $pageTitle, $pageSubtitle);
if (!$canEdit) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin, Owner, and Sub Branch roles can view staff.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-staff-page data-can-edit="<?= $canEdit ? '1' : '0' ?>" data-create-mode="<?= $createMode ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Staff filters</h3>
                <p>Search by name, phone, or position.</p>
            </div>
        </div>
        <form class="filter-bar" data-staff-filter>
            <input type="text" name="q" placeholder="Name, phone, or position">
            <select name="branch_id" data-branch-filter>
                <option value="">All branches</option>
            </select>
            <select name="status">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-staff-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Staff members</h3>
                <p>Track salary baselines and active status.</p>
            </div>
            <?php if ($canEdit): ?>
                <button class="button ghost small" type="button" data-staff-add>Add staff</button>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Branch</th>
                        <th>Position</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-staff-table>
                    <tr>
                        <td colspan="6" class="muted">Loading staff...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-staff-pagination>
            <button class="button ghost small" type="button" data-staff-prev>Previous</button>
            <span class="page-label" data-staff-page>Page 1</span>
            <button class="button ghost small" type="button" data-staff-next>Next</button>
        </div>
        <div class="notice-stack" data-staff-status></div>
    </section>

    <?php if ($canEdit): ?>
        <div class="drawer" data-staff-drawer>
            <div class="drawer-scrim" data-staff-drawer-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="staff-form-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="staff-form-title" data-staff-form-title>Add staff</h3>
                        <p>Capture staff details and salary baseline.</p>
                    </div>
                    <button class="icon-button" type="button" data-staff-drawer-close aria-label="Close staff panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-staff-form>
                    <input type="hidden" name="staff_id" data-staff-id>
                    <label>
                        <span>Name</span>
                        <input type="text" name="name" required>
                    </label>
                    <label>
                        <span>Phone</span>
                        <input type="text" name="phone">
                    </label>
                    <label>
                        <span>Position</span>
                        <input type="text" name="position">
                    </label>
                    <label data-branch-field>
                        <span>Branch</span>
                        <select name="branch_id" data-branch-select>
                            <option value="">Select branch</option>
                        </select>
                    </label>
                    <label>
                        <span>Base salary</span>
                        <input type="number" step="0.01" name="base_salary" placeholder="0.00">
                    </label>
                    <label>
                        <span>Status</span>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </label>
                    <label>
                        <span>Hired at</span>
                        <input type="date" name="hired_at">
                    </label>
                    <label class="full">
                        <span>Notes</span>
                        <input type="text" name="note" placeholder="Optional notes">
                    </label>
                    <label class="full" data-staff-user-toggle-field>
                        <span>Login access</span>
                        <div class="inline-check">
                            <input type="checkbox" name="create_user" value="1" data-staff-user-toggle>
                            <span>Create login for this staff member</span>
                        </div>
                        <small class="muted" data-staff-user-note>Optional: create a linked user account for staff access.</small>
                    </label>
                    <label data-staff-user-field class="is-hidden">
                        <span>Username</span>
                        <input type="text" name="user_username" autocomplete="username">
                    </label>
                    <label data-staff-user-field class="is-hidden">
                        <span>Password</span>
                        <input type="password" name="user_password" autocomplete="new-password">
                    </label>
                    <label data-staff-user-field class="is-hidden">
                        <span>Role</span>
                        <select name="user_role_id" data-staff-user-role>
                            <option value="">Select role</option>
                        </select>
                    </label>
                    <div class="login-note full is-hidden" data-staff-user-field>
                        Leave blank to keep the current password.
                    </div>
                    <button class="button primary small" type="submit" data-staff-submit-label>Add staff</button>
                </form>
                <div class="notice-stack" data-staff-form-status></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
internal_page_end();
?>
