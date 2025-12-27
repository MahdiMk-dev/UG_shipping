<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../app/db.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
$canAdminReports = in_array($role, ['Admin', 'Owner'], true);
$canBranchReports = in_array($role, ['Admin', 'Owner', 'Sub Branch'], true);

if (!$canBranchReports) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Reports are not available for this role.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}

$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-t');
$branchId = $user['branch_id'] ?? null;
$branchName = '';
if ($branchId) {
    $stmt = db()->prepare('SELECT name FROM branches WHERE id = ?');
    $stmt->execute([$branchId]);
    $branchName = (string) ($stmt->fetchColumn() ?: '');
}

internal_page_start($user, 'reports', 'Reports', 'Generate printable financial and operational reports.');
?>
<div data-reports-page>
    <?php if ($canAdminReports): ?>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Expenses by shipment</h3>
                    <p>Track shipment-linked expenses by shipment or date range.</p>
                </div>
            </div>
            <form class="grid-form" method="get" target="_blank" action="<?= BASE_URL ?>/views/internal/report_expenses_shipment">
                <label>
                    <span>Shipment (optional)</span>
                    <select name="shipment_id" data-report-shipment-select>
                        <option value="">All shipments</option>
                    </select>
                </label>
                <label>
                    <span>Date from</span>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    <span>Date to</span>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($defaultTo, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <button class="button primary small" type="submit">Generate report</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Company expenses</h3>
                    <p>Includes all operating, staff, and shipment expenses.</p>
                </div>
            </div>
            <form class="grid-form" method="get" target="_blank" action="<?= BASE_URL ?>/views/internal/report_expenses_company">
                <label>
                    <span>Date from</span>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    <span>Date to</span>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($defaultTo, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <button class="button primary small" type="submit">Generate report</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Net report</h3>
                    <p>Combines orders (income) with expenses for a date range.</p>
                </div>
            </div>
            <form class="grid-form" method="get" target="_blank" action="<?= BASE_URL ?>/views/internal/report_net">
                <label>
                    <span>Date from</span>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    <span>Date to</span>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($defaultTo, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <button class="button primary small" type="submit">Generate report</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Branch orders status</h3>
                <p>Orders in shipment, in main branch, and received at the sub branch.</p>
            </div>
        </div>
        <form class="grid-form" method="get" target="_blank" action="<?= BASE_URL ?>/views/internal/report_branch_orders">
            <?php if ($canAdminReports): ?>
                <label>
                    <span>Branch</span>
                    <select name="branch_id" data-report-branch-select required>
                        <option value="">Select branch</option>
                    </select>
                </label>
            <?php else: ?>
                <input type="hidden" name="branch_id" value="<?= htmlspecialchars((string) $branchId, ENT_QUOTES, 'UTF-8') ?>">
                <label>
                    <span>Branch</span>
                    <input type="text" value="<?= htmlspecialchars($branchName ?: 'Your branch', ENT_QUOTES, 'UTF-8') ?>" disabled>
                </label>
            <?php endif; ?>
            <label>
                <span>Date from</span>
                <input type="date" name="date_from" value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>
                <span>Date to</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($defaultTo, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <button class="button primary small" type="submit">Generate report</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Branch balances</h3>
                <p>Order receipts, reversals, transfers, and adjustments by branch.</p>
            </div>
        </div>
        <form class="grid-form" method="get" target="_blank" action="<?= BASE_URL ?>/views/internal/report_branch_balances">
            <?php if ($canAdminReports): ?>
                <label>
                    <span>Branch (optional)</span>
                    <select name="branch_id" data-report-branch-select>
                        <option value="">All branches</option>
                    </select>
                </label>
            <?php else: ?>
                <input type="hidden" name="branch_id" value="<?= htmlspecialchars((string) $branchId, ENT_QUOTES, 'UTF-8') ?>">
                <label>
                    <span>Branch</span>
                    <input type="text" value="<?= htmlspecialchars($branchName ?: 'Your branch', ENT_QUOTES, 'UTF-8') ?>" disabled>
                </label>
            <?php endif; ?>
            <label>
                <span>Date from</span>
                <input type="date" name="date_from" value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>
                <span>Date to</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($defaultTo, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <button class="button primary small" type="submit">Generate report</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Transactions in &amp; out</h3>
                <p>View inflows and outflows by branch or company-wide.</p>
            </div>
        </div>
        <form class="grid-form" method="get" target="_blank" action="<?= BASE_URL ?>/views/internal/report_transactions">
            <?php if ($canAdminReports): ?>
                <label>
                    <span>Branch (optional)</span>
                    <select name="branch_id" data-report-branch-select>
                        <option value="">All branches</option>
                    </select>
                </label>
            <?php else: ?>
                <input type="hidden" name="branch_id" value="<?= htmlspecialchars((string) $branchId, ENT_QUOTES, 'UTF-8') ?>">
                <label>
                    <span>Branch</span>
                    <input type="text" value="<?= htmlspecialchars($branchName ?: 'Your branch', ENT_QUOTES, 'UTF-8') ?>" disabled>
                </label>
            <?php endif; ?>
            <label>
                <span>Date from</span>
                <input type="date" name="date_from" value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>
                <span>Date to</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($defaultTo, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <button class="button primary small" type="submit">Generate report</button>
        </form>
    </section>
</div>
<?php
internal_page_end();
?>
