<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/customer_auth.php';

$customer = customer_auth_user();
$portalCustomer = $customer;
$portalOrders = [];
$portalInvoices = [];

if ($customer) {
    require_once __DIR__ . '/../../app/db.php';
    $customerId = (int) ($customer['customer_id'] ?? 0);
    if ($customerId > 0) {
        $db = db();
        $customerStmt = $db->prepare(
            'SELECT c.id, c.name, c.code, c.phone, c.address, c.balance, c.sub_branch_id, b.name AS sub_branch_name '
            . 'FROM customers c '
            . 'LEFT JOIN branches b ON b.id = c.sub_branch_id '
            . 'WHERE c.id = ? AND c.deleted_at IS NULL'
        );
        $customerStmt->execute([$customerId]);
        $portalCustomer = $customerStmt->fetch() ?: $customer;

        $ordersStmt = $db->prepare(
            'SELECT o.id, o.tracking_number, o.fulfillment_status, o.total_price, o.created_at, s.shipment_number '
            . 'FROM orders o '
            . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
            . 'WHERE o.customer_id = ? AND o.deleted_at IS NULL '
            . 'ORDER BY o.id DESC LIMIT 20'
        );
        $ordersStmt->execute([$customerId]);
        $portalOrders = $ordersStmt->fetchAll();

        $invoicesStmt = $db->prepare(
            'SELECT id, invoice_no, status, total, due_total, issued_at '
            . 'FROM invoices '
            . 'WHERE customer_id = ? AND deleted_at IS NULL '
            . 'ORDER BY id DESC LIMIT 20'
        );
        $invoicesStmt->execute([$customerId]);
        $portalInvoices = $invoicesStmt->fetchAll();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UG Shipping - Customer Portal</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/assets/css/main.css">
</head>
<body class="<?= $customer ? '' : 'login-body' ?>">
<?php if (!$customer) : ?>
    <main class="login-shell">
        <section class="brand-panel">
            <div class="brand">
                <div class="brand-mark">
                    <img class="brand-logo" src="<?= PUBLIC_URL ?>/assets/img/ug-logo.jpg"
                         onerror="this.onerror=null;this.src='<?= PUBLIC_URL ?>/assets/img/ug-logo.svg';"
                         alt="United Group">
                </div>
                <div>
                    <p class="brand-kicker">Customer Access</p>
                    <h1>UG Shipping Portal</h1>
                </div>
            </div>
            <p class="brand-copy">
                Track your orders, see shipment status, and download invoice copies in one place.
            </p>
            <div class="brand-stats">
                <div>
                    <span class="stat-value">Order status</span>
                    <span class="stat-label">Live fulfillment updates</span>
                </div>
                <div>
                    <span class="stat-value">Invoices</span>
                    <span class="stat-label">Balances and due totals</span>
                </div>
            </div>
        </section>
        <section class="login-card">
            <div class="card-header">
                <h2>Customer sign in</h2>
                <p>Use the portal credentials assigned by your branch.</p>
            </div>
            <form class="login-form" data-portal-login-form>
                <label class="field">
                    <span>Username</span>
                    <input type="text" name="username" autocomplete="username" required>
                </label>
                <label class="field">
                    <span>Password</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <div class="form-actions">
                    <button type="submit" class="button primary">Sign in</button>
                </div>
            </form>
            <div class="notice-stack" data-portal-login-status></div>
        </section>
    </main>
<?php else : ?>
    <main class="app-shell portal-shell" data-portal-shell>
        <header class="topbar">
            <div class="brand-mini">
                <img class="nav-logo" src="<?= PUBLIC_URL ?>/assets/img/ug-logo.jpg"
                     onerror="this.onerror=null;this.src='<?= PUBLIC_URL ?>/assets/img/ug-logo.svg';"
                     alt="UG">
                <span>Customer Portal</span>
            </div>
            <div class="user-chip">
                <span class="user-name" data-portal-user-name><?= htmlspecialchars($portalCustomer['name'] ?? 'Customer', ENT_QUOTES, 'UTF-8') ?></span>
                <span class="user-role" data-portal-user-code><?= htmlspecialchars($portalCustomer['code'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                <button class="button ghost small" type="button" data-portal-logout>Sign out</button>
            </div>
        </header>

        <div class="content">
            <section class="page-hero">
                <h1 data-portal-greeting>Welcome back, <?= htmlspecialchars($portalCustomer['name'] ?? 'Customer', ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Your latest shipment activity and invoices are shown below.</p>
                <div class="detail-grid portal-summary">
                    <article class="detail-card">
                        <h3>Account</h3>
                        <div class="detail-list">
                            <div><span>Name</span><strong data-portal-name><?= htmlspecialchars($portalCustomer['name'] ?? '--', ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div><span>Code</span><strong data-portal-code><?= htmlspecialchars($portalCustomer['code'] ?? '--', ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div><span>Branch</span><strong data-portal-branch><?= htmlspecialchars($portalCustomer['sub_branch_name'] ?? '--', ENT_QUOTES, 'UTF-8') ?></strong></div>
                        </div>
                    </article>
                    <article class="detail-card">
                        <h3>Balance</h3>
                        <div class="detail-list">
                            <div><span>Current</span><strong data-portal-balance><?= htmlspecialchars((string) ($portalCustomer['balance'] ?? '--'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div><span>Phone</span><strong data-portal-phone><?= htmlspecialchars($portalCustomer['phone'] ?? '--', ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div><span>Address</span><strong data-portal-address><?= htmlspecialchars($portalCustomer['address'] ?? '--', ENT_QUOTES, 'UTF-8') ?></strong></div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3>Recent orders</h3>
                        <p>Track delivery status for your latest orders.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Tracking</th>
                                <th>Shipment</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody data-portal-orders>
                            <?php if (!$portalOrders) : ?>
                                <tr><td colspan="5" class="muted">No orders yet.</td></tr>
                            <?php else : ?>
                                <?php foreach ($portalOrders as $order) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['tracking_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($order['shipment_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($order['fulfillment_status'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($order['total_price'] ?? '0.00'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($order['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination" data-portal-orders-pagination>
                    <button class="button ghost small" type="button" data-portal-orders-prev>Previous</button>
                    <span class="page-label" data-portal-orders-page>Page 1</span>
                    <button class="button ghost small" type="button" data-portal-orders-next>Next</button>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h3>Recent invoices</h3>
                        <p>Review invoice totals and due amounts.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Due</th>
                                <th>Issued</th>
                            </tr>
                        </thead>
                        <tbody data-portal-invoices>
                            <?php if (!$portalInvoices) : ?>
                                <tr><td colspan="5" class="muted">No invoices found.</td></tr>
                            <?php else : ?>
                                <?php foreach ($portalInvoices as $invoice) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($invoice['invoice_no'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($invoice['status'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($invoice['total'] ?? '0.00'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($invoice['due_total'] ?? '0.00'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($invoice['issued_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination" data-portal-invoices-pagination>
                    <button class="button ghost small" type="button" data-portal-invoices-prev>Previous</button>
                    <span class="page-label" data-portal-invoices-page>Page 1</span>
                    <button class="button ghost small" type="button" data-portal-invoices-next>Next</button>
                </div>
            </section>

            <div class="notice-stack" data-portal-status></div>
        </div>
    </main>
<?php endif; ?>

<script>
    window.APP_BASE = <?= json_encode(BASE_URL) ?>;
    window.PUBLIC_BASE = <?= json_encode(PUBLIC_URL) ?>;
    window.PORTAL_HOME = <?= json_encode(BASE_URL . '/views/portal/home') ?>;
</script>
<script src="<?= PUBLIC_URL ?>/assets/js/app.js"></script>
</body>
</html>
