<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
internal_page_start($user, 'customers', 'Edit Customer', 'Update the profile code.');
if ($role !== 'Admin') {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin users can edit customer profiles.</p>
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
<div data-customer-edit data-customer-id="<?= htmlspecialchars((string) $customerId, ENT_QUOTES) ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Customer details</h3>
                <p>Only Admin users can update profile codes.</p>
            </div>
        </div>
        <form class="grid-form" data-customer-edit-form>
            <label>
                <span>Code</span>
                <input type="text" name="code" required>
            </label>
            <button class="button primary" type="submit">Save changes</button>
            <a class="button ghost" href="<?= BASE_URL ?>/views/internal/customers">Back to list</a>
        </form>
        <div class="notice-stack" data-customer-edit-status></div>
    </section>
</div>
<?php
internal_page_end();
