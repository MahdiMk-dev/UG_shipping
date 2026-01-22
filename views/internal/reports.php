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
<div data-reports-page class="reports-shell"
     data-can-admin="<?= $canAdminReports ? '1' : '0' ?>"
     data-branch-id="<?= htmlspecialchars((string) $branchId, ENT_QUOTES, 'UTF-8') ?>"
     data-branch-name="<?= htmlspecialchars($branchName, ENT_QUOTES, 'UTF-8') ?>">
    <section class="panel reports-panel">
        <div class="panel-header">
            <div>
                <h3>Report builder</h3>
                <p>Select a report and filters, then generate it below.</p>
            </div>
        </div>
        <form class="grid-form reports-form" data-reports-form>
            <label class="full">
                <span>Report</span>
                <select name="report_key" data-report-type required>
                    <option value="">Select report</option>
                    <?php if ($canAdminReports): ?>
                        <option value="expenses_shipment"
                                data-url="<?= BASE_URL ?>/views/internal/report_expenses_shipment"
                                data-branch="none" data-shipment="optional" data-mode="0"
                                data-description="Track shipment-linked expenses by shipment or date range.">
                            Expenses by shipment
                        </option>
                        <option value="net_shipment"
                                data-url="<?= BASE_URL ?>/views/internal/report_net_shipment"
                                data-branch="none" data-shipment="optional" data-mode="0"
                                data-description="Net per shipment using order income vs shipment expenses, plus paid/unpaid orders.">
                            Net by shipment
                        </option>
                        <option value="expenses_company"
                                data-url="<?= BASE_URL ?>/views/internal/report_expenses_company"
                                data-branch="none" data-shipment="none" data-mode="1"
                                data-description="Company-wide operating, staff, and shipment expenses.">
                            Company expenses
                        </option>
                        <option value="net"
                                data-url="<?= BASE_URL ?>/views/internal/report_net"
                                data-branch="none" data-shipment="none" data-mode="1"
                                data-description="Net report combining income and expenses.">
                            Net report
                        </option>
                    <?php endif; ?>
                    <option value="branch_orders"
                            data-url="<?= BASE_URL ?>/views/internal/report_branch_orders"
                            data-branch="required" data-shipment="none" data-mode="0"
                            data-description="Orders in shipment, in main branch, and received at sub branches.">
                        Branch orders status
                    </option>
                    <option value="branch_balances"
                            data-url="<?= BASE_URL ?>/views/internal/report_branch_balances"
                            data-branch="optional" data-shipment="none" data-mode="0"
                            data-description="Order receipts, reversals, transfers, and adjustments by branch.">
                        Branch balances
                    </option>
                    <option value="transactions"
                            data-url="<?= BASE_URL ?>/views/internal/report_transactions"
                            data-branch="optional" data-shipment="none" data-mode="0"
                            data-description="Transactions in and out by branch or company-wide.">
                        Transactions in &amp; out
                    </option>
                </select>
            </label>

            <label data-report-field="shipment">
                <span>Shipment (optional)</span>
                <select name="shipment_id" data-report-shipment-select>
                    <option value="">All shipments</option>
                </select>
            </label>

            <label data-report-field="origin" class="is-hidden">
                <span>Origin country (optional)</span>
                <select name="origin_country_id" data-report-origin-select>
                    <option value="">All origins</option>
                </select>
            </label>

            <div data-report-field="branch">
                <?php if ($canAdminReports): ?>
                    <label data-report-branch-select-field>
                        <span>Branch</span>
                        <select name="branch_id" data-report-branch-select>
                            <option value="">All branches</option>
                        </select>
                    </label>
                <?php else: ?>
                    <input type="hidden" name="branch_id" data-report-branch-fixed
                           value="<?= htmlspecialchars((string) $branchId, ENT_QUOTES, 'UTF-8') ?>">
                    <label data-report-branch-fixed-field>
                        <span>Branch</span>
                        <input type="text" value="<?= htmlspecialchars($branchName ?: 'Your branch', ENT_QUOTES, 'UTF-8') ?>" disabled>
                    </label>
                <?php endif; ?>
            </div>

            <label>
                <span>Date from</span>
                <input type="date" name="date_from" data-report-date-from value="<?= htmlspecialchars($defaultFrom, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>
                <span>Date to</span>
                <input type="date" name="date_to" data-report-date-to value="<?= htmlspecialchars($defaultTo, ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label data-report-field="mode">
                <span>View mode</span>
                <select name="mode" data-report-mode>
                    <option value="detailed">Detailed</option>
                    <option value="brief">Brief</option>
                </select>
            </label>

            <div class="reports-actions full">
                <button class="button primary" type="submit">Generate report</button>
            </div>
        </form>
        <div class="notice-stack" data-report-status></div>
    </section>

    <section class="panel reports-preview">
        <div class="panel-header">
            <div>
                <h3>Report preview</h3>
                <p data-report-description>Choose a report to preview it here.</p>
            </div>
            <div class="panel-actions">
                <a class="button ghost small is-disabled" data-report-open href="#" target="_blank" rel="noopener">Open</a>
                <button class="button ghost small" type="button" data-report-print disabled>Print</button>
            </div>
        </div>
        <div class="reports-frame-wrap">
            <iframe data-report-frame title="Report preview"></iframe>
        </div>
    </section>
</div>
<?php
internal_page_end();
?>
