<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'audit', 'Audit Log', 'Owner-only activity trail for changes and approvals.');

if (($user['role'] ?? '') !== 'Owner') {
    http_response_code(403);
    ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Access denied</h3>
                <p>Only the Owner role can view audit logs.</p>
            </div>
        </div>
    </section>
    <?php
    internal_page_end();
    exit;
}
?>
<div data-audit-page>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Audit entries</h3>
                <p>All tracked actions with before/after snapshots.</p>
            </div>
        </div>
        <form class="filter-bar" data-audit-filter>
            <input type="text" name="action" placeholder="Action (ex: shipments.create)">
            <input type="text" name="entity_type" placeholder="Entity (shipment, order, invoice)">
            <input type="number" name="entity_id" placeholder="Entity ID">
            <button class="button primary" type="submit">Filter</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody data-audit-table>
                    <tr><td colspan="5" class="muted">Loading audit logs...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" data-audit-pagination>
            <button class="button ghost small" type="button" data-audit-prev>Previous</button>
            <span class="page-label" data-audit-page>Page 1</span>
            <button class="button ghost small" type="button" data-audit-next>Next</button>
        </div>
        <div class="notice-stack" data-audit-status></div>
    </section>
</div>
<?php
internal_page_end();
