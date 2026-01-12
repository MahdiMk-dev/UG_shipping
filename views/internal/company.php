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
        <label>
            <span>Point price (amount per point)</span>
            <input type="number" name="points_price" step="0.01" min="0" placeholder="0.00">
        </label>
        <label>
            <span>Point value (discount per point)</span>
            <input type="number" name="points_value" step="0.01" min="0" placeholder="0.00">
        </label>
        <label>
            <span>USD to LBP rate (1 USD = ? LBP)</span>
            <input type="number" name="usd_to_lbp" step="0.01" min="0" placeholder="0.00">
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
<section class="panel" data-goods-types-panel>
    <div class="panel-header">
        <div>
            <h3>Goods types</h3>
            <p>Manage the goods list used in shipment creation.</p>
        </div>
    </div>
    <form class="grid-form" data-goods-types-form>
        <label>
            <span>Type name</span>
            <input type="text" name="name" data-goods-types-input required>
        </label>
        <button class="button primary small" type="submit">Add type</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody data-goods-types-table>
                <tr><td colspan="2" class="muted">Loading goods types...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="notice-stack" data-goods-types-status></div>
</section>
<?php if (($user['role'] ?? '') === 'Owner'): ?>
    <section class="panel" data-roles-panel>
        <div class="panel-header">
            <div>
                <h3>Roles</h3>
                <p>Manage internal roles available for staff logins.</p>
            </div>
        </div>
        <form class="grid-form" data-roles-form>
            <label>
                <span>Role name</span>
                <input type="text" name="name" data-roles-input required>
            </label>
            <button class="button primary small" type="submit">Add role</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-roles-table>
                    <tr><td colspan="2" class="muted">Loading roles...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="notice-stack" data-roles-status></div>
    </section>
<?php else: ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Roles</h3>
                <p>Only Owner role can manage roles.</p>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php
internal_page_end();
?>
