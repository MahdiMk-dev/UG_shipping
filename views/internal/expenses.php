<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner'], true);
internal_page_start($user, 'expenses', 'Expenses', 'Track operational expenses such as rent and utilities.');
if (!$canEdit) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin and Owner roles can view expenses.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-expenses-page data-can-edit="<?= $canEdit ? '1' : '0' ?>">
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Expense filters</h3>
                <p>Search by title, note, or shipment.</p>
            </div>
        </div>
        <form class="filter-bar" data-expenses-filter>
            <input type="text" name="q" placeholder="Expense title or note">
            <select name="branch_id" data-branch-filter>
                <option value="">All branches</option>
            </select>
            <button class="button primary" type="submit">Search</button>
            <button class="button ghost" type="button" data-expenses-refresh>Refresh</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Expense list</h3>
                <p>Recorded operating expenses.</p>
            </div>
            <?php if ($canEdit): ?>
                <button class="button ghost small" type="button" data-expenses-add>Add expense</button>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Branch</th>
                        <th>Shipment</th>
                        <th>Amount</th>
                        <th>Note</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody data-expenses-table>
                    <tr>
                        <td colspan="7" class="muted">Loading expenses...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-expenses-pagination>
            <button class="button ghost small" type="button" data-expenses-prev>Previous</button>
            <span class="page-label" data-expenses-page>Page 1</span>
            <button class="button ghost small" type="button" data-expenses-next>Next</button>
        </div>
        <div class="notice-stack" data-expenses-status></div>
    </section>

    <?php if ($canEdit): ?>
        <div class="drawer" data-expenses-drawer>
            <div class="drawer-scrim" data-expenses-drawer-close></div>
            <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="expense-form-title">
                <div class="drawer-header">
                    <div>
                        <h3 id="expense-form-title" data-expenses-form-title>Add expense</h3>
                        <p>Capture operational expenses for reporting.</p>
                    </div>
                    <button class="icon-button" type="button" data-expenses-drawer-close aria-label="Close expense panel">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6l-12 12"></path></svg>
                    </button>
                </div>
                <form class="grid-form" data-expenses-form>
                    <input type="hidden" name="expense_id" data-expense-id>
                    <label>
                        <span>Branch (optional)</span>
                        <select name="branch_id" data-branch-select>
                            <option value="">No branch</option>
                        </select>
                    </label>
                    <label>
                        <span>Title</span>
                        <input type="text" name="title" required>
                    </label>
                    <label>
                        <span>Amount</span>
                        <input type="number" step="0.01" name="amount" placeholder="0.00" required>
                    </label>
                    <label>
                        <span>Expense date</span>
                        <input type="date" name="expense_date">
                    </label>
                    <label class="full">
                        <span>Note</span>
                        <input type="text" name="note" placeholder="Optional note">
                    </label>
                    <button class="button primary small" type="submit" data-expenses-submit-label>Add expense</button>
                </form>
                <div class="notice-stack" data-expenses-form-status></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
internal_page_end();
?>
