<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/customer_auth.php';

api_require_method('GET');
$auth = customer_auth_require_user();
$accountId = $auth['account_id'] ?? null;
if (!$accountId) {
    api_error('Customer session is invalid', 401);
}

$db = db();
$profilesStmt = $db->prepare(
    'SELECT c.id, c.name, c.code, c.phone, c.address, c.balance, c.sub_branch_id, b.name AS sub_branch_name, '
    . 'c.profile_country_id, co.name AS profile_country_name '
    . 'FROM customers c '
    . 'LEFT JOIN branches b ON b.id = c.sub_branch_id '
    . 'LEFT JOIN countries co ON co.id = c.profile_country_id '
    . 'WHERE c.account_id = ? AND c.deleted_at IS NULL AND c.is_system = 0 '
    . 'ORDER BY c.id DESC'
);
$profilesStmt->execute([$accountId]);
$profiles = $profilesStmt->fetchAll();
if (!$profiles) {
    api_error('No profiles found', 404);
}

$profileIds = array_map(static fn($row) => (int) $row['id'], $profiles);
$placeholders = implode(',', array_fill(0, count($profileIds), '?'));

$ordersStmt = $db->prepare(
    'SELECT o.id, o.customer_id, c.name AS customer_name, c.code AS customer_code, '
    . 'o.tracking_number, o.fulfillment_status, o.total_price, o.created_at, '
    . 's.shipment_number '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'WHERE o.customer_id IN (' . $placeholders . ') AND o.deleted_at IS NULL '
    . "AND o.fulfillment_status IN ('in_shipment','main_branch') "
    . 'ORDER BY o.id DESC LIMIT 50'
);
$ordersStmt->execute($profileIds);
$orders = $ordersStmt->fetchAll();

$invoicesStmt = $db->prepare(
    'SELECT i.id, i.customer_id, c.name AS customer_name, c.code AS customer_code, '
    . 'i.invoice_no, i.status, i.total, i.due_total, i.issued_at '
    . 'FROM invoices i '
    . 'LEFT JOIN customers c ON c.id = i.customer_id '
    . 'WHERE i.customer_id IN (' . $placeholders . ') AND i.deleted_at IS NULL '
    . 'ORDER BY i.id DESC LIMIT 50'
);
$invoicesStmt->execute($profileIds);
$invoices = $invoicesStmt->fetchAll();

$transactionsStmt = $db->prepare(
    'SELECT t.id, t.customer_id, c.name AS customer_name, c.code AS customer_code, '
    . 't.type, t.amount, t.payment_date, t.created_at, pm.name AS payment_method '
    . 'FROM transactions t '
    . 'LEFT JOIN customers c ON c.id = t.customer_id '
    . 'LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id '
    . 'WHERE t.customer_id IN (' . $placeholders . ') AND t.deleted_at IS NULL '
    . 'ORDER BY t.id DESC LIMIT 50'
);
$transactionsStmt->execute($profileIds);
$transactions = $transactionsStmt->fetchAll();

api_json([
    'ok' => true,
    'account' => [
        'account_id' => (int) $accountId,
        'username' => $auth['username'] ?? null,
        'phone' => $auth['phone'] ?? null,
        'sub_branch_id' => $auth['sub_branch_id'] ?? null,
    ],
    'profiles' => $profiles,
    'orders' => $orders,
    'invoices' => $invoices,
    'transactions' => $transactions,
]);
