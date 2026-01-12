<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$GLOBALS['internal_styles'] = array_merge($GLOBALS['internal_styles'] ?? [], [
    'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css',
]);
$GLOBALS['internal_scripts'] = array_merge($GLOBALS['internal_scripts'] ?? [], [
    'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js',
]);

$user = internal_require_user();
internal_page_start($user, 'orders', 'Create Order', 'Orders must be created within a shipment.');
?>
<?php
$shipmentId = $_GET['shipment_id'] ?? null;
$shipmentNumber = $_GET['shipment_number'] ?? null;
$collectionId = $_GET['collection_id'] ?? null;
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true);
$isWarehouse = ($user['role'] ?? '') === 'Warehouse';
?>
<div
    data-order-create
    data-shipment-id="<?= htmlspecialchars((string) $shipmentId, ENT_QUOTES) ?>"
    data-shipment-number="<?= htmlspecialchars((string) $shipmentNumber, ENT_QUOTES) ?>"
    data-collection-id="<?= htmlspecialchars((string) $collectionId, ENT_QUOTES) ?>"
>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Order details</h3>
                <p>This order will be tied to a shipment and collection.</p>
            </div>
        </div>
        <?php if (!$canEdit): ?>
            <p class="muted">You have view-only access. Creating orders is restricted to Admin, Owner, Main Branch, and Warehouse roles.</p>
        <?php else: ?>
        <form class="grid-form order-create-form" data-orders-create>
            <label>
                <span>Collection</span>
                <select name="collection_id" data-collection-select>
                    <option value="">No collection</option>
                </select>
            </label>
            <label>
                <span>Tracking number</span>
                <input type="text" name="tracking_number" required>
            </label>
            <label>
                <span>Customer</span>
                <select name="customer_id" data-customer-select data-placeholder="Type to search (2+ chars)" required>
                    <option value="">Type to search (2+ chars)</option>
                </select>
            </label>
            <label>
                <span>Assigned sub branch</span>
                <input type="text" data-sub-branch-display readonly>
            </label>
            <input type="hidden" name="delivery_type" value="pickup">
            <label class="order-create-unit">
                <span>Unit</span>
                <input type="text" data-unit-display readonly>
                <input type="hidden" name="unit_type" data-unit-type>
            </label>
            <label class="order-create-weight-type">
                <span>Weight type</span>
                <div class="option-group option-group-equal" data-weight-type-group>
                    <label class="option-pill">
                        <input type="radio" name="weight_type" value="actual" data-weight-type checked required>
                        <span>Actual (KG)</span>
                    </label>
                    <label class="option-pill">
                        <input type="radio" name="weight_type" value="volumetric" data-weight-type>
                        <span>Volumetric (CBM)</span>
                    </label>
                </div>
            </label>
            <label data-weight-actual>
                <span>Actual weight</span>
                <input type="number" step="0.01" name="actual_weight">
            </label>
            <label data-weight-volume>
                <span>Width (w)</span>
                <input type="number" step="0.01" name="w">
            </label>
            <label data-weight-volume>
                <span>Depth (d)</span>
                <input type="number" step="0.01" name="d">
            </label>
            <label data-weight-volume>
                <span>Height (h)</span>
                <input type="number" step="0.01" name="h">
            </label>
            <label class="<?= $isWarehouse ? 'is-hidden' : '' ?>">
                <span>Rate</span>
                <input type="number" step="0.01" name="rate">
            </label>
            <label class="full">
                <span>Notes</span>
                <textarea name="note" rows="3" placeholder="Add any notes for this order"></textarea>
            </label>
            <button class="button primary" type="submit">Create order</button>
            <a class="button ghost" href="<?= BASE_URL ?>/views/internal/shipments">Back to shipments</a>
        </form>
        <?php endif; ?>
        <div class="notice-stack" data-orders-status></div>
    </section>
</div>
<?php
internal_page_end();
