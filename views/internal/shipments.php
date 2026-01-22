<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'shipments', 'Shipments', 'Track cargo movement and milestones.');
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true);
?>
<div data-shipments-page>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Search shipments</h3>
                <p>Filter by status, shipping type, or origin country.</p>
            </div>
        </div>
        <form class="filter-bar" data-shipments-filter>
            <input type="text" name="q" placeholder="Shipment number or keyword">
            <select name="origin_country_id" data-origin-select>
                <option value="">All origins</option>
            </select>
            <select name="status">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="departed">Departed</option>
                <option value="airport">Airport</option>
                <option value="arrived">Arrived</option>
                <option value="partially_distributed">Partially distributed</option>
                <option value="distributed">Distributed</option>
            </select>
            <div class="option-group" role="group" aria-label="Shipping type">
                <label class="option-pill">
                    <input type="radio" name="shipping_type" value="" checked>
                    <span>All types</span>
                </label>
                <label class="option-pill">
                    <input type="radio" name="shipping_type" value="air">
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
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-shipments-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Shipment list</h3>
                <p>Current records stored in the system.</p>
            </div>
            <?php if ($canEdit): ?>
                <a class="button ghost small" href="<?= BASE_URL ?>/views/internal/shipment_create">Add shipment</a>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Shipment</th>
                        <th>Origin</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Expected departure</th>
                        <th>Expected arrival</th>
                        <th class="meta-col">Created</th>
                        <th class="meta-col">Updated</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody data-shipments-table>
                    <tr>
                        <td colspan="9" class="loading-cell">
                            <div class="loading-inline">
                                <span class="spinner" aria-hidden="true"></span>
                                <span class="loading-text">Shipments are loading, please wait...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-shipments-pagination>
            <button class="button ghost small" type="button" data-shipments-prev>Previous</button>
            <span class="page-label" data-shipments-page>Page 1</span>
            <button class="button ghost small" type="button" data-shipments-next>Next</button>
        </div>
        <div class="notice-stack" data-shipments-status></div>
    </section>
</div>
<?php
internal_page_end();
