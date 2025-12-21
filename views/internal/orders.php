<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'orders', 'Orders', 'Manage parcel-level pricing and fulfillment.');
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch'], true);
?>
<div data-orders-page-root>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Search orders</h3>
                <p>Search by customer name, code, shipment number, or tracking number.</p>
            </div>
        </div>
        <form class="filter-bar" data-orders-filter>
            <input type="text" name="q" placeholder="Customer, code, shipment, or tracking">
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-orders-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Orders in shipment</h3>
                <p>Orders still moving with the shipment.</p>
            </div>
            <?php if ($canEdit): ?>
                <a class="button ghost small" href="<?= BASE_URL ?>/views/internal/shipments">Create from shipment</a>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Customer</th>
                        <th>Shipment</th>
                        <th>Sub branch</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Fulfillment</th>
                        <th class="meta-col">Created</th>
                        <th class="meta-col">Updated</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody data-orders-table="in_shipment">
                    <tr>
                        <td colspan="10" class="muted">Loading orders...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <button class="button ghost small" type="button" data-orders-prev="in_shipment">Previous</button>
            <span class="page-label" data-orders-page="in_shipment">Page 1</span>
            <button class="button ghost small" type="button" data-orders-next="in_shipment">Next</button>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Orders at main branch</h3>
                <p>Orders now ready at the main branch.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Customer</th>
                        <th>Shipment</th>
                        <th>Sub branch</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Fulfillment</th>
                        <th class="meta-col">Created</th>
                        <th class="meta-col">Updated</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody data-orders-table="main_branch">
                    <tr>
                        <td colspan="10" class="muted">Loading orders...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <button class="button ghost small" type="button" data-orders-prev="main_branch">Previous</button>
            <span class="page-label" data-orders-page="main_branch">Page 1</span>
            <button class="button ghost small" type="button" data-orders-next="main_branch">Next</button>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Orders pending receive</h3>
                <p>Orders distributed to sub-branches but not received yet.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Customer</th>
                        <th>Shipment</th>
                        <th>Sub branch</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Fulfillment</th>
                        <th class="meta-col">Created</th>
                        <th class="meta-col">Updated</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody data-orders-table="pending_receipt">
                    <tr>
                        <td colspan="10" class="muted">Loading orders...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <button class="button ghost small" type="button" data-orders-prev="pending_receipt">Previous</button>
            <span class="page-label" data-orders-page="pending_receipt">Page 1</span>
            <button class="button ghost small" type="button" data-orders-next="pending_receipt">Next</button>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Orders received</h3>
                <p>Orders confirmed by sub-branches.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Customer</th>
                        <th>Shipment</th>
                        <th>Sub branch</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Fulfillment</th>
                        <th class="meta-col">Created</th>
                        <th class="meta-col">Updated</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody data-orders-table="received_subbranch">
                    <tr>
                        <td colspan="10" class="muted">Loading orders...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <button class="button ghost small" type="button" data-orders-prev="received_subbranch">Previous</button>
            <span class="page-label" data-orders-page="received_subbranch">Page 1</span>
            <button class="button ghost small" type="button" data-orders-next="received_subbranch">Next</button>
        </div>
    </section>

    <div class="notice-stack" data-orders-status></div>
</div>
<?php
internal_page_end();
