<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'company', 'Company Settings', 'Update details used on printable documents.');
if (!in_array($user['role'] ?? '', ['Admin', 'Owner'], true)) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin and Owner roles can update company settings.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<section class="panel" data-company-settings>
    <div class="panel-header">
        <div>
            <h3>Company profile</h3>
            <p>These details appear on invoices and receipts.</p>
        </div>
    </div>
    <form class="grid-form" data-company-form>
        <label>
            <span>Company name</span>
            <input type="text" name="name" required>
        </label>
        <label>
            <span>Phone</span>
            <input type="text" name="phone">
        </label>
        <label class="full">
            <span>Address</span>
            <input type="text" name="address">
        </label>
        <label>
            <span>Email</span>
            <input type="email" name="email">
        </label>
        <label>
            <span>Website</span>
            <input type="text" name="website">
        </label>
        <label class="full">
            <span>Company logo</span>
            <input type="hidden" name="logo_url">
            <div class="company-logo">
                <div class="company-logo-preview">
                    <img src="" alt="Company logo" data-company-logo-preview>
                </div>
                <div class="company-logo-actions">
                    <input type="file" accept="image/*" data-company-logo-input>
                    <div class="company-logo-buttons">
                        <button class="button small" type="button" data-company-logo-upload>Upload logo</button>
                        <button class="button ghost small" type="button" data-company-logo-delete>Remove logo</button>
                    </div>
                    <small class="muted">PNG, JPG, GIF, or WebP up to 10 MB.</small>
                </div>
            </div>
        </label>
        <button class="button primary small" type="submit">Save settings</button>
    </form>
    <div class="notice-stack" data-company-status></div>
</section>
<?php
internal_page_end();
?>
