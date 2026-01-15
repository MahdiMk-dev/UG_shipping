<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
internal_page_start($user, 'customers', 'Edit Customer Info', 'Update customer contact details.');
if ($role !== 'Admin') {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin users can edit customer information.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<?php
$customerId = $_GET['id'] ?? null;
?>
<div data-customer-info-edit data-customer-id="<?= htmlspecialchars((string) $customerId, ENT_QUOTES) ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Customer info</h3>
                <p>Update the customer name, phone, and address details.</p>
            </div>
        </div>
        <form class="grid-form" data-customer-info-edit-form>
            <label>
                <span>Name</span>
                <input type="text" name="name" required>
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
                <span>Sub branch</span>
                <select name="sub_branch_id" data-branch-select>
                    <option value="">Select sub-branch</option>
                </select>
            </label>
            <button class="button primary" type="submit">Save changes</button>
            <a class="button ghost" href="<?= BASE_URL ?>/views/internal/customers">Back to list</a>
        </form>
        <div class="notice-stack" data-customer-info-edit-status></div>
    </section>
</div>
<?php
internal_page_end();
