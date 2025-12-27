<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/customer_auth.php';

api_require_method('GET');

$invoiceId = api_int($_GET['invoice_id'] ?? ($_GET['id'] ?? null));
$invoiceNo = api_string($_GET['invoice_no'] ?? null);

if (!$invoiceId && !$invoiceNo) {
    api_error('invoice_id or invoice_no is required', 422);
}

$user = auth_user();
$customer = customer_auth_user();

if (!$user && !$customer) {
    api_error('Unauthorized', 401);
}

$db = db();
$showMeta = false;
$branchScopeId = null;
if ($user) {
    $role = $user['role'] ?? '';
    if (in_array($role, ['Warehouse', 'Staff'], true)) {
        api_error('Forbidden', 403);
    }
    $showMeta = in_array($role, ['Admin', 'Owner', 'Main Branch'], true);
    if ($role === 'Sub Branch') {
        $branchScopeId = (int) ($user['branch_id'] ?? 0);
        if ($branchScopeId <= 0) {
            api_error('Branch scope required', 403);
        }
    }
}

if ($invoiceId) {
    if ($showMeta) {
        $stmt = $db->prepare(
            'SELECT i.*, iu.name AS issued_by_name, uu.name AS updated_by_name '
            . 'FROM invoices i '
            . 'LEFT JOIN users iu ON iu.id = i.issued_by_user_id '
            . 'LEFT JOIN users uu ON uu.id = i.updated_by_user_id '
            . 'WHERE i.id = ? AND i.deleted_at IS NULL'
        );
        $stmt->execute([$invoiceId]);
    } else {
        $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$invoiceId]);
    }
} else {
    if ($showMeta) {
        $stmt = $db->prepare(
            'SELECT i.*, iu.name AS issued_by_name, uu.name AS updated_by_name '
            . 'FROM invoices i '
            . 'LEFT JOIN users iu ON iu.id = i.issued_by_user_id '
            . 'LEFT JOIN users uu ON uu.id = i.updated_by_user_id '
            . 'WHERE i.invoice_no = ? AND i.deleted_at IS NULL'
        );
        $stmt->execute([$invoiceNo]);
    } else {
        $stmt = $db->prepare('SELECT * FROM invoices WHERE invoice_no = ? AND deleted_at IS NULL');
        $stmt->execute([$invoiceNo]);
    }
}

$invoice = $stmt->fetch();
if (!$invoice) {
    api_error('Invoice not found', 404);
}

if ($branchScopeId && (int) ($invoice['branch_id'] ?? 0) !== $branchScopeId) {
    api_error('Forbidden', 403);
}

if ($customer) {
    $accountId = $customer['account_id'] ?? null;
    if (!$accountId) {
        api_error('Customer session is invalid', 401);
    }
    $check = $db->prepare(
        'SELECT id FROM customers WHERE id = ? AND account_id = ? AND deleted_at IS NULL AND is_system = 0'
    );
    $check->execute([(int) $invoice['customer_id'], (int) $accountId]);
    if (!$check->fetch()) {
        api_error('Forbidden', 403);
    }
}

$itemStmt = $db->prepare(
    'SELECT id, order_id, order_snapshot_json, line_total '
    . 'FROM invoice_items WHERE invoice_id = ?'
);
$itemStmt->execute([$invoice['id']]);
$items = [];

while ($row = $itemStmt->fetch()) {
    $row['order_snapshot'] = json_decode($row['order_snapshot_json'], true);
    unset($row['order_snapshot_json']);
    $items[] = $row;
}

api_json(['ok' => true, 'invoice' => $invoice, 'items' => $items]);
