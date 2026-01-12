<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'customers', 'Edit Customer', 'Update profile and branch assignment.');
?>
<?php
$customerId = $_GET['id'] ?? null;
?>
<div data-customer-edit data-customer-id="<?= htmlspecialchars((string) $customerId, ENT_QUOTES) ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Customer details</h3>
                <p>Admins and assigned branches can modify this customer.</p>
            </div>
        </div>
        <form class="grid-form" data-customer-edit-form>
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
                <input type="text" name="phone" minlength="8">
            </label>
            <label>
                <span>Address</span>
                <input type="text" name="address">
            </label>
            <label class="full">
                <span>Notes</span>
                <input type="text" name="note" placeholder="Optional notes">
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
                <span>Reset portal password</span>
                <input type="password" name="portal_password" autocomplete="new-password" placeholder="Leave blank to keep current">
            </label>
            <p class="muted full">Resetting a portal password does not require the current password.</p>
            <label data-branch-field>
                <span>Sub branch</span>
                <select name="sub_branch_id" data-branch-select>
                    <option value="">Select sub-branch</option>
                </select>
            </label>
            <button class="button primary" type="submit">Save changes</button>
            <a class="button ghost" href="<?= BASE_URL ?>/views/internal/customers">Back to list</a>
        </form>
        <div class="notice-stack" data-customer-edit-status></div>
    </section>
</div>
<?php
internal_page_end();
