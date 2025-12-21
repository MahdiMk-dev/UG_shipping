<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$user = internal_require_user();
internal_page_start($user, 'roles', 'Roles', 'Clear access tiers for each operational team.');
?>
<section class="panel-grid">
    <article class="panel">
        <div class="panel-header">
            <div>
                <h3>Admin</h3>
                <p>Full access across all modules, settings, and audits.</p>
            </div>
        </div>
        <ul class="list">
            <li><span class="badge info">Access</span><span>All shipments, orders, finance, and users</span></li>
            <li><span class="badge neutral">Scope</span><span>All branches</span></li>
        </ul>
    </article>
    <article class="panel">
        <div class="panel-header">
            <div>
                <h3>Owner</h3>
                <p>Oversight of finance and operational controls.</p>
            </div>
        </div>
        <ul class="list">
            <li><span class="badge info">Access</span><span>Finance, invoices, transactions</span></li>
            <li><span class="badge neutral">Scope</span><span>All branches (read + approvals)</span></li>
        </ul>
    </article>
    <article class="panel">
        <div class="panel-header">
            <div>
                <h3>Main Branch</h3>
                <p>Core distribution, invoicing, and payments.</p>
            </div>
        </div>
        <ul class="list">
            <li><span class="badge info">Access</span><span>Shipments, orders, invoices, transactions</span></li>
            <li><span class="badge neutral">Scope</span><span>Main branch + sub branches</span></li>
        </ul>
    </article>
    <article class="panel">
        <div class="panel-header">
            <div>
                <h3>Sub Branch</h3>
                <p>Receiving, customer delivery, and order updates.</p>
            </div>
        </div>
        <ul class="list">
            <li><span class="badge info">Access</span><span>Orders, receiving scans, attachments</span></li>
            <li><span class="badge neutral">Scope</span><span>Assigned sub branch only</span></li>
        </ul>
    </article>
    <article class="panel">
        <div class="panel-header">
            <div>
                <h3>Warehouse</h3>
                <p>Inbound cargo and shipment preparation.</p>
            </div>
        </div>
        <ul class="list">
            <li><span class="badge info">Access</span><span>Shipments, collections, attachments</span></li>
            <li><span class="badge neutral">Scope</span><span>Assigned warehouse only</span></li>
        </ul>
    </article>
</section>
<?php
internal_page_end();
