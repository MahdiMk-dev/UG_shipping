<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'orders', 'Orders', 'Review shipments with grouped orders.');
$role = $user['role'] ?? '';
$canEdit = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
$showIncome = $role !== 'Warehouse';
?>
<div data-orders-page-root data-show-income="<?= $showIncome ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Search orders</h3>
                <p>Filter shipments by tracking, customer, or shipment number.</p>
            </div>
        </div>
        <form class="filter-bar" data-orders-filter>
            <input type="text" name="q" placeholder="Shipment, tracking, or customer">
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-orders-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Orders by shipment</h3>
                <p>Grouped totals for each shipment.</p>
            </div>
            <?php if ($canEdit): ?>
                <a class="button ghost small" href="<?= BASE_URL ?>/views/internal/shipments">Create from shipment</a>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Shipment</th>
                        <th>Origin</th>
                        <th>Status</th>
                        <th>Orders</th>
                        <?php if ($showIncome): ?>
                            <th>Total</th>
                        <?php endif; ?>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody data-orders-shipments-table>
                    <tr>
                        <td colspan="<?= $showIncome ? 6 : 5 ?>" class="muted">Loading shipments...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-orders-pagination>
            <button class="button ghost small" type="button" data-orders-prev>Previous</button>
            <span class="page-label" data-orders-page>Page 1</span>
            <button class="button ghost small" type="button" data-orders-next>Next</button>
        </div>
        <div class="notice-stack" data-orders-status></div>
    </section>
</div>
<?php
internal_page_end();
?>
