<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/customer_auth.php';

api_require_method('GET');
$auth = customer_auth_require_user();
$customerId = $auth['customer_id'] ?? null;
if (!$customerId) {
    api_error('Customer session is invalid', 401);
}

$db = db();
$customerStmt = $db->prepare(
    'SELECT c.id, c.name, c.code, c.phone, c.address, c.balance, c.sub_branch_id, b.name AS sub_branch_name '
    . 'FROM customers c '
    . 'LEFT JOIN branches b ON b.id = c.sub_branch_id '
    . 'WHERE c.id = ? AND c.deleted_at IS NULL'
);
$customerStmt->execute([$customerId]);
$customer = $customerStmt->fetch();
if (!$customer) {
    api_error('Customer not found', 404);
}

$ordersStmt = $db->prepare(
    'SELECT o.id, o.tracking_number, o.fulfillment_status, o.total_price, o.created_at, '
    . 's.shipment_number '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'WHERE o.customer_id = ? AND o.deleted_at IS NULL '
    . 'ORDER BY o.id DESC LIMIT 20'
);
$ordersStmt->execute([$customerId]);
$orders = $ordersStmt->fetchAll();

$invoicesStmt = $db->prepare(
    'SELECT id, invoice_no, status, total, due_total, issued_at '
    . 'FROM invoices '
    . 'WHERE customer_id = ? AND deleted_at IS NULL '
    . 'ORDER BY id DESC LIMIT 20'
);
$invoicesStmt->execute([$customerId]);
$invoices = $invoicesStmt->fetchAll();

api_json([
    'ok' => true,
    'customer' => $customer,
    'orders' => $orders,
    'invoices' => $invoices,
]);
