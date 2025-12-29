<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'customers', 'Create Customer', 'Add a new customer with a unique code.');
if (($user['role'] ?? '') === 'Warehouse') {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Warehouse users cannot create customer profiles.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-customer-create>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Customer details</h3>
                <p>Branch assignment controls where the customer is managed.</p>
            </div>
        </div>
        <form class="grid-form" data-customer-create-form>
            <label>
                <span>Name</span>
                <input type="text" name="name" required>
            </label>
            <label>
                <span>Code</span>
                <input type="text" name="code" required>
            </label>
            <label>
                <span>Phone</span>
                <input type="text" name="phone" minlength="8" required>
            </label>
            <label>
                <span>Address</span>
                <input type="text" name="address">
            </label>
            <label>
                <span>Profile country</span>
                <select name="profile_country_id" data-country-select required>
                    <option value="">Select country</option>
                </select>
            </label>
            <label>
                <span>Portal username</span>
                <input type="text" name="portal_username" required>
            </label>
            <label>
                <span>Portal password</span>
                <input type="password" name="portal_password" autocomplete="new-password" placeholder="Leave blank for existing account">
            </label>
            <p class="muted full">Portal username links profiles. Leave password blank to reuse existing login.</p>
            <label data-branch-field>
                <span>Sub branch</span>
                <select name="sub_branch_id" data-branch-select>
                    <option value="">Select sub-branch</option>
                </select>
            </label>
            <button class="button primary" type="submit">Create customer</button>
            <a class="button ghost" href="<?= BASE_URL ?>/views/internal/customers">Back to list</a>
        </form>
        <div class="notice-stack" data-customer-create-status></div>
    </section>
</div>
<?php
internal_page_end();
