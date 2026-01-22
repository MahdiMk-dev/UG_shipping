<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'shipments', 'Create Shipment', 'Add a new shipment and set its origin.');
$role = $user['role'] ?? '';
$canEdit = in_array($role, ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true);
$isWarehouse = $role === 'Warehouse';
$showRates = $role !== 'Warehouse';
?>
<div data-shipment-create class="shipment-create-shell">
    <section class="panel shipment-create-panel">
        <div class="panel-header shipment-create-header">
            <div>
                <h3>Shipment details</h3>
                <p>Required fields are shipment number, origin, type, and goods.</p>
            </div>
        </div>
        <?php if (!$canEdit): ?>
            <p class="muted">You have view-only access. Creating shipments is restricted to Admin, Owner, Main Branch, and Warehouse roles.</p>
        <?php else: ?>
        <form class="grid-form shipment-create-form" data-shipments-create>
            <label>
                <span>Shipment number</span>
                <input type="text" name="shipment_number" placeholder="UG-2025-0001" required>
            </label>
            <label>
                <span>Origin country</span>
                <select name="origin_country_id" data-origin-select required>
                    <option value="">Select origin</option>
                </select>
            </label>
            <label>
                <span>Shipping type</span>
                <div class="option-group" data-shipping-type-group>
                    <label class="option-pill">
                        <input type="radio" name="shipping_type" value="air" required>
                        <span>Air</span>
                    </label>
                    <label class="option-pill">
                        <input type="radio" name="shipping_type" value="sea">
                        <span>Sea</span>
                    </label>
                    <label class="option-pill">
                        <input type="radio" name="shipping_type" value="land">
                        <span>Land</span>
                    </label>
                </div>
            </label>
            <label>
                <span>Type of goods</span>
                <select name="type_of_goods" data-goods-select required>
                    <option value="">Select type</option>
                </select>
            </label>
            <?php if (!$isWarehouse): ?>
                <label>
                    <span>Shipper profile</span>
                    <select name="shipper_profile_id" data-supplier-select data-supplier-type="shipper">
                        <option value="">Select shipper (optional)</option>
                    </select>
                </label>
                <label>
                    <span>Consignee profile</span>
                    <select name="consignee_profile_id" data-supplier-select data-supplier-type="consignee">
                        <option value="">Select consignee (optional)</option>
                    </select>
                </label>
            <?php endif; ?>
            <?php if ($isWarehouse): ?>
                <input type="hidden" name="status" value="active">
            <?php else: ?>
                <label>
                    <span>Status</span>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="departed">Departed</option>
                        <option value="airport">Airport</option>
                        <option value="arrived">Arrived</option>
                        <option value="partially_distributed">Partially distributed</option>
                        <option value="distributed">Distributed</option>
                    </select>
                </label>
            <?php endif; ?>
            <label>
                <span>Expected departure date</span>
                <input type="date" name="departure_date">
            </label>
            <label>
                <span>Expected arrival date</span>
                <input type="date" name="arrival_date">
            </label>
            <?php if ($showRates): ?>
                <label>
                    <span>Default rate (KG)</span>
                    <input type="number" step="0.01" name="default_rate_kg" placeholder="0.00">
                </label>
                <label>
                    <span>Default rate (CBM)</span>
                    <input type="number" step="0.01" name="default_rate_cbm" placeholder="0.00">
                </label>
            <?php endif; ?>
            <label class="full">
                <span>Notes</span>
                <input type="text" name="note" placeholder="Optional notes">
            </label>
            <button class="button primary" type="submit">Create shipment</button>
            <a class="button ghost" href="<?= BASE_URL ?>/views/internal/shipments">Back to list</a>
        </form>
        <?php endif; ?>
        <div class="notice-stack" data-shipments-status></div>
    </section>
</div>
<?php
internal_page_end();


