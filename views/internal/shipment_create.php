<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'shipments', 'Create Shipment', 'Add a new shipment and set its origin.');
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true);
?>
<div data-shipment-create>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Shipment details</h3>
                <p>Required fields are shipment number, origin, and type.</p>
            </div>
        </div>
        <?php if (!$canEdit): ?>
            <p class="muted">You have view-only access. Creating shipments is restricted to Admin, Owner, Main Branch, and Warehouse roles.</p>
        <?php else: ?>
        <form class="grid-form" data-shipments-create>
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
                <select name="shipping_type" required>
                    <option value="">Select type</option>
                    <option value="air">Air</option>
                    <option value="sea">Sea</option>
                    <option value="land">Land</option>
                </select>
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="departed">Departed</option>
                    <option value="airport">Airport</option>
                    <option value="arrived">Arrived</option>
                    <option value="distributed">Distributed</option>
                </select>
            </label>
            <label>
                <span>Departure date</span>
                <input type="date" name="departure_date">
            </label>
            <label>
                <span>Arrival date</span>
                <input type="date" name="arrival_date">
            </label>
            <label>
                <span>Default rate</span>
                <input type="number" step="0.01" name="default_rate" placeholder="0.00">
            </label>
            <label>
                <span>Rate unit</span>
                <select name="default_rate_unit">
                    <option value="">Select unit</option>
                    <option value="kg">KG</option>
                    <option value="cbm">CBM</option>
                </select>
            </label>
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
