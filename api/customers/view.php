<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = auth_require_user();

$customerId = api_int($_GET['customer_id'] ?? ($_GET['id'] ?? null));
$customerCode = api_string($_GET['code'] ?? null);
$orderNote = api_string($_GET['order_note'] ?? null);

if (!$customerId && !$customerCode) {
    api_error('customer_id or code is required', 422);
}

$role = $user['role'] ?? '';
$fullAccess = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
$metaAccess = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);

$db = db();
if ($customerId) {
    $stmt = $db->prepare(
        'SELECT c.*, b.name AS sub_branch_name, ca.username AS portal_username, ca.phone AS portal_phone, '
        . 'co.name AS profile_country_name '
        . 'FROM customers c '
        . 'LEFT JOIN branches b ON b.id = c.sub_branch_id '
        . 'LEFT JOIN customer_accounts ca ON ca.id = c.account_id '
        . 'LEFT JOIN countries co ON co.id = c.profile_country_id '
        . 'WHERE c.id = ? AND c.deleted_at IS NULL'
    );
    $stmt->execute([$customerId]);
} else {
    $stmt = $db->prepare(
        'SELECT c.*, b.name AS sub_branch_name, ca.username AS portal_username, ca.phone AS portal_phone, '
        . 'co.name AS profile_country_name '
        . 'FROM customers c '
        . 'LEFT JOIN branches b ON b.id = c.sub_branch_id '
        . 'LEFT JOIN customer_accounts ca ON ca.id = c.account_id '
        . 'LEFT JOIN countries co ON co.id = c.profile_country_id '
        . 'WHERE c.code = ? AND c.deleted_at IS NULL'
    );
    $stmt->execute([$customerCode]);
}

$customer = $stmt->fetch();
if (!$customer) {
    api_error('Customer not found', 404);
}

if (!$fullAccess) {
    if (($role ?? '') === 'Warehouse') {
        $warehouseCountryId = get_branch_country_id($user);
        if (!$warehouseCountryId || (int) $customer['profile_country_id'] !== (int) $warehouseCountryId) {
            api_error('Forbidden', 403);
        }
    } else {
        $branchId = $user['branch_id'] ?? null;
        if (!$branchId || (int) $customer['sub_branch_id'] !== (int) $branchId) {
            api_error('Forbidden', 403);
        }
    }
}

$invoicesStmt = $db->prepare(
    'SELECT id, invoice_no, status, total, due_total, issued_at '
    . 'FROM invoices WHERE customer_id = ? AND deleted_at IS NULL '
    . 'ORDER BY id DESC LIMIT 50'
);
$invoicesStmt->execute([$customer['id']]);
$invoices = $invoicesStmt->fetchAll();

$transactions = [];
try {
    $transactionsStmt = $db->prepare(
        'SELECT e.id, e.entry_type, e.amount, e.reference_type, e.reference_id, e.note, e.created_at, '
        . 't.payment_date, pm.name AS payment_method, '
        . 'o.tracking_number, s.shipment_number, '
        . '(SELECT GROUP_CONCAT(DISTINCT i.invoice_no ORDER BY i.invoice_no SEPARATOR \', \') '
        . 'FROM transaction_allocations ta '
        . 'JOIN invoices i ON i.id = ta.invoice_id AND i.deleted_at IS NULL '
        . 'WHERE ta.transaction_id = t.id) AS invoice_nos '
        . 'FROM customer_balance_entries e '
        . 'LEFT JOIN transactions t ON t.id = e.reference_id AND e.reference_type = \'transaction\' '
        . 'LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id '
        . 'LEFT JOIN orders o ON o.id = e.reference_id AND e.reference_type = \'order\' '
        . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
        . 'WHERE e.customer_id = ? '
        . 'ORDER BY e.id DESC LIMIT 50'
    );
    $transactionsStmt->execute([$customer['id']]);
    $transactions = $transactionsStmt->fetchAll();
} catch (Throwable $error) {
    error_log('Customer transactions load failed: ' . $error->getMessage());
}

$ordersSql = 'SELECT o.id, o.tracking_number, o.shipment_id, s.shipment_number, o.fulfillment_status, '
    . 'o.total_price, o.note, o.created_at, o.updated_at, cu.name AS created_by_name, uu.name AS updated_by_name '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'LEFT JOIN users cu ON cu.id = o.created_by_user_id '
    . 'LEFT JOIN users uu ON uu.id = o.updated_by_user_id '
    . 'WHERE o.customer_id = ? AND o.deleted_at IS NULL';
$ordersParams = [$customer['id']];
if ($orderNote) {
    $ordersSql .= ' AND o.note LIKE ?';
    $ordersParams[] = '%' . $orderNote . '%';
}
$ordersSql .= ' ORDER BY o.id DESC LIMIT 50';

$ordersStmt = $db->prepare($ordersSql);
$ordersStmt->execute($ordersParams);
$orders = $ordersStmt->fetchAll();

if (!$metaAccess) {
    foreach ($orders as &$order) {
        unset($order['created_by_name'], $order['updated_by_name']);
    }
    unset($order);
}

api_json([
    'ok' => true,
    'customer' => $customer,
    'invoices' => $invoices,
    'transactions' => $transactions,
    'orders' => $orders,
]);
