<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
internal_page_start($user, 'accounts', 'Account Details', 'Balance and account activity.');
if (!in_array($role, ['Admin', 'Owner', 'Sub Branch'], true)) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin, Owner, and Sub Branch roles can view accounts.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}

$accountId = $_GET['id'] ?? null;
?>
<div data-account-view data-account-id="<?= htmlspecialchars((string) $accountId, ENT_QUOTES) ?>">
    <section class="panel detail-grid">
        <article class="detail-card">
            <h3>Account info</h3>
            <div class="detail-list">
                <div><span>Account ID</span><strong data-detail="id">--</strong></div>
                <div><span>Name</span><strong data-detail="name">--</strong></div>
                <div><span>Owner</span><strong data-detail="owner_label">--</strong></div>
                <div><span>Type</span><strong data-detail="account_type">--</strong></div>
                <div><span>Payment method</span><strong data-detail="payment_method_name">--</strong></div>
                <div><span>Currency</span><strong data-detail="currency">--</strong></div>
                <div><span>Status</span><strong data-detail="status">--</strong></div>
            </div>
        </article>
        <article class="detail-card">
            <h3>Balance</h3>
            <div class="detail-list">
                <div><span>Current</span><strong data-detail="balance">--</strong></div>
            </div>
        </article>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Transactions</h3>
                <p>Latest account entries and transfers.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Transfer</th>
                        <th>Status</th>
                        <th>Reference</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody data-account-entries>
                    <tr><td colspan="7" class="muted">Loading entries...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-account-entries-pagination>
            <button class="button ghost small" type="button" data-account-entries-prev>Previous</button>
            <span class="page-label" data-account-entries-page>Page 1</span>
            <button class="button ghost small" type="button" data-account-entries-next>Next</button>
        </div>
        <div class="notice-stack" data-account-view-status></div>
    </section>
</div>
<?php
internal_page_end();
