<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
if (!in_array($role, ['Owner', 'Sub Branch'], true)) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Owner and Sub Branch roles can view branch overview.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}

$branchId = (int) ($_GET['branch_id'] ?? 0);
if ($role === 'Sub Branch') {
    $branchId = (int) ($user['branch_id'] ?? 0);
}

internal_page_start($user, 'branch_overview', 'Branch overview', 'Stats, contacts, and customer balances.');
?>
<div data-branch-overview-page data-branch-id="<?= htmlspecialchars((string) $branchId, ENT_QUOTES) ?>">
    <section class="panel detail-grid">
        <article class="detail-card">
            <h3>Branch info</h3>
            <div class="detail-list">
                <div><span>Name</span><strong data-branch-detail="name">--</strong></div>
                <div><span>Type</span><strong data-branch-detail="type">--</strong></div>
                <div><span>Country</span><strong data-branch-detail="country_name">--</strong></div>
                <div><span>Parent</span><strong data-branch-detail="parent_branch_name">--</strong></div>
                <div><span>Phone</span><strong data-branch-detail="phone">--</strong></div>
                <div><span>Address</span><strong data-branch-detail="address">--</strong></div>
            </div>
        </article>
        <article class="detail-card">
            <h3>Balance</h3>
            <div class="detail-list">
                <div><span>Current</span><strong data-branch-detail="balance">--</strong></div>
                <div><span>Customer due</span><strong data-branch-detail="due_total">--</strong></div>
                <div><span>Customer credit</span><strong data-branch-detail="credit_total">--</strong></div>
            </div>
        </article>
        <article class="detail-card">
            <h3>Customer stats</h3>
            <div class="detail-list">
                <div><span>Profiles</span><strong data-branch-detail="profile_count">--</strong></div>
                <div><span>Accounts</span><strong data-branch-detail="account_count">--</strong></div>
                <div><span>Accounts with balance</span><strong data-branch-detail="balance_count">--</strong></div>
            </div>
        </article>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Customers with balance</h3>
                <p>Non-zero balances for this branch.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Profiles</th>
                        <th>Countries</th>
                        <th>Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody data-branch-overview-customers>
                    <tr>
                        <td colspan="5" class="muted">Loading customers...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-branch-overview-pagination>
            <button class="button ghost small" type="button" data-branch-overview-prev>Previous</button>
            <span class="page-label" data-branch-overview-page>Page 1</span>
            <button class="button ghost small" type="button" data-branch-overview-next>Next</button>
        </div>
        <div class="notice-stack" data-branch-overview-status></div>
    </section>
</div>
<?php
internal_page_end();
