<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
$canEdit = in_array($user['role'] ?? '', ['Admin', 'Owner', 'Sub Branch'], true);
internal_page_start($user, 'staff', 'Staff Details', 'Salary adjustments and expense tracking.');
$staffId = $_GET['id'] ?? null;
if (!$canEdit) {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only Admin, Owner, and Sub Branch roles can view staff.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-staff-view data-staff-id="<?= htmlspecialchars((string) $staffId, ENT_QUOTES) ?>" data-can-edit="<?= $canEdit ? '1' : '0' ?>">
    <section class="panel detail-grid">
        <article class="detail-card">
            <h3>Staff info</h3>
            <div class="detail-list">
                <div><span>Name</span><strong data-staff-detail="name">--</strong></div>
                <div><span>Phone</span><strong data-staff-detail="phone">--</strong></div>
                <div><span>Position</span><strong data-staff-detail="position">--</strong></div>
                <div><span>Branch</span><strong data-staff-detail="branch_name">--</strong></div>
                <div><span>Status</span><strong data-staff-detail="status">--</strong></div>
                <div><span>Hired at</span><strong data-staff-detail="hired_at">--</strong></div>
                <div><span>Notes</span><strong data-staff-detail="note">--</strong></div>
            </div>
        </article>
        <article class="detail-card">
            <h3>Salary</h3>
            <div class="detail-list">
                <div><span>Base salary</span><strong data-staff-detail="base_salary">--</strong></div>
            </div>
            <?php if ($canEdit): ?>
                <button class="button ghost small" type="button" data-staff-delete>Delete staff</button>
            <?php endif; ?>
        </article>
    </section>

    <?php if ($canEdit): ?>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Adjust salary</h3>
                    <p>Update the base salary. Adjustments are tracked but not counted as expenses.</p>
                </div>
            </div>
            <form class="grid-form" data-staff-salary-form>
                <label>
                    <span>New salary</span>
                    <input type="number" step="0.01" name="new_salary" required>
                </label>
                <label>
                    <span>Adjustment date</span>
                    <input type="date" name="expense_date">
                </label>
                <label class="full">
                    <span>Note</span>
                    <input type="text" name="note" placeholder="Optional note">
                </label>
                <button class="button primary" type="submit">Save adjustment</button>
            </form>
            <div class="notice-stack" data-staff-salary-status></div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Pay salary</h3>
                    <p>Record a salary payment for a specific month.</p>
                </div>
            </div>
            <form class="grid-form" data-staff-pay-form>
                <label>
                    <span>Salary month</span>
                    <input type="month" name="salary_month" required>
                </label>
                <label>
                    <span>Amount</span>
                    <input type="number" step="0.01" name="amount" required>
                </label>
                <label>
                    <span>Payment date</span>
                    <input type="date" name="payment_date">
                </label>
                <label class="full">
                    <span>Note</span>
                    <input type="text" name="note" placeholder="Optional note">
                </label>
                <button class="button primary" type="submit">Record salary payment</button>
            </form>
            <div class="notice-stack" data-staff-pay-status></div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Advance payment</h3>
                    <p>Record money given in advance.</p>
                </div>
            </div>
            <form class="grid-form" data-staff-advance-form>
                <label>
                    <span>Amount</span>
                    <input type="number" step="0.01" name="amount" required>
                </label>
                <label>
                    <span>Advance date</span>
                    <input type="date" name="expense_date">
                </label>
                <label class="full">
                    <span>Note</span>
                    <input type="text" name="note" placeholder="Optional note">
                </label>
                <button class="button primary" type="submit">Record advance</button>
            </form>
            <div class="notice-stack" data-staff-advance-status></div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h3>Bonus payment</h3>
                    <p>Record bonus payments as expenses.</p>
                </div>
            </div>
            <form class="grid-form" data-staff-bonus-form>
                <label>
                    <span>Amount</span>
                    <input type="number" step="0.01" name="amount" required>
                </label>
                <label>
                    <span>Bonus date</span>
                    <input type="date" name="expense_date">
                </label>
                <label class="full">
                    <span>Note</span>
                    <input type="text" name="note" placeholder="Optional note">
                </label>
                <button class="button primary" type="submit">Record bonus</button>
            </form>
            <div class="notice-stack" data-staff-bonus-status></div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Expense history</h3>
                <p>Salary payments, advances, bonuses, and adjustments.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Salary before</th>
                        <th>Salary after</th>
                        <th>Salary month</th>
                        <th>Date</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody data-staff-expenses>
                    <tr><td colspan="7" class="muted">Loading expenses...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-staff-expenses-pagination>
            <button class="button ghost small" type="button" data-staff-expenses-prev>Previous</button>
            <span class="page-label" data-staff-expenses-page>Page 1</span>
            <button class="button ghost small" type="button" data-staff-expenses-next>Next</button>
        </div>
    </section>

    <div class="notice-stack" data-staff-view-status></div>
</div>
<?php
internal_page_end();
?>
