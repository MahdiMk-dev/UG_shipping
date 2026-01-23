<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
internal_page_start($user, 'partners', 'Create Partner', 'Add a new partner profile.');
if (!in_array($role, ['Admin', 'Owner'], true)) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin and Owner roles can create Partners.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-partner-create>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Partner details</h3>
                <p>Partners can be people, companies, or currency exchangers.</p>
            </div>
        </div>
        <form class="grid-form" data-partner-create-form>
            <label>
                <span>Type</span>
                <select name="type" required>
                    <option value="">Select type</option>
                    <option value="person">Person</option>
                    <option value="company">Company</option>
                    <option value="exchanger">Exchanger</option>
                    <option value="other">Other</option>
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
            <label>
                <span>Email</span>
                <input type="email" name="email">
            </label>
            <label class="full">
                <span>Address</span>
                <input type="text" name="address">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </label>
            <label>
                <span>Opening balance</span>
                <input type="number" step="0.01" name="opening_balance" value="0">
            </label>
            <div class="full">
                <button class="button primary small" type="submit">Create Partner</button>
            </div>
        </form>
        <div class="notice-stack" data-partner-create-status></div>
    </section>
</div>
<?php
internal_page_end();
?>
