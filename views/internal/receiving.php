<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$canManage = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch'], true);
internal_page_start($user, 'receiving', 'Receiving', 'Scan and confirm deliveries at the main or sub branch.');
$unmatchedCols = $canManage ? 7 : 6;
$reportedCols = $canManage ? 7 : 6;
?>
<div data-receiving-page>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Receiving queue</h3>
                <p>Shipments with orders waiting to be received.</p>
            </div>
            <div class="panel-actions">
                <button class="button ghost small" type="button" data-receiving-refresh>Refresh</button>
            </div>
        </div>
        <form class="grid-form" data-receiving-filter>
            <label>
                <span>Search</span>
                <input type="text" name="q" data-receiving-search placeholder="Shipment, tracking, or customer">
            </label>
            <?php if ($canManage): ?>
                <label>
                    <span>Sub branch</span>
                    <select name="sub_branch_id" data-receiving-branch>
                        <option value="">All sub branches</option>
                    </select>
                </label>
            <?php endif; ?>
            <button class="button ghost small" type="submit">Apply filters</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Shipment</th>
                        <th>Origin</th>
                        <th>Status</th>
                        <th>Awaiting</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody data-receiving-shipments-table>
                    <tr><td colspan="5" class="muted">Loading pending receipts...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <button class="button ghost small" type="button" data-receiving-shipments-prev>Previous</button>
            <span class="page-label" data-receiving-shipments-page>Page 1</span>
            <button class="button ghost small" type="button" data-receiving-shipments-next>Next</button>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Unmatched scans</h3>
                <p>Tracking numbers that did not match any order.</p>
            </div>
            <div class="panel-actions">
                <button class="button ghost small" type="button" data-receiving-unmatched-refresh>Refresh</button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Shipment</th>
                        <th>Branch</th>
                        <th>Match</th>
                        <th>Scanned at</th>
                        <th>Note</th>
                        <?php if ($canManage): ?>
                            <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody data-receiving-unmatched-table>
                    <tr><td colspan="<?= $unmatchedCols ?>" class="muted">Loading unmatched scans...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <button class="button ghost small" type="button" data-receiving-unmatched-prev>Previous</button>
            <span class="page-label" data-receiving-unmatched-page>Page 1</span>
            <button class="button ghost small" type="button" data-receiving-unmatched-next>Next</button>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Reported weight differences</h3>
                <p>Orders received with a reported weight for admin follow-up.</p>
            </div>
            <div class="panel-actions">
                <button class="button ghost small" type="button" data-receiving-reported-refresh>Refresh</button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Shipment</th>
                        <th>Branch</th>
                        <th>System weight</th>
                        <th>Reported weight</th>
                        <th>Reported at</th>
                        <?php if ($canManage): ?>
                            <th>Reported by</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody data-receiving-reported-table>
                    <tr><td colspan="<?= $reportedCols ?>" class="muted">Loading reported orders...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            <button class="button ghost small" type="button" data-receiving-reported-prev>Previous</button>
            <span class="page-label" data-receiving-reported-page>Page 1</span>
            <button class="button ghost small" type="button" data-receiving-reported-next>Next</button>
        </div>
    </section>

    <div class="notice-stack" data-receiving-status></div>
</div>
<?php
internal_page_end();
