<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin']);
$input = api_read_input();

$customerId = api_int($input['customer_id'] ?? ($input['id'] ?? null));
if (!$customerId) {
    api_error('customer_id is required', 422);
}

$allowedKeys = ['customer_id', 'id', 'name', 'phone', 'address', 'note', 'sub_branch_id'];
foreach (array_keys($input) as $key) {
    if (!in_array($key, $allowedKeys, true)) {
        api_error('Unsupported field for customer info update', 422);
    }
}

$name = api_string($input['name'] ?? null);
if (!$name) {
    api_error('name is required', 422);
}

$phoneProvided = array_key_exists('phone', $input);
$phone = $phoneProvided ? api_string($input['phone'] ?? null) : null;
if ($phoneProvided && $phone && strlen($phone) < 8) {
    api_error('phone must be at least 8 characters', 422);
}

$addressProvided = array_key_exists('address', $input);
$address = $addressProvided ? api_string($input['address'] ?? null) : null;
$noteProvided = array_key_exists('note', $input);
$note = $noteProvided ? api_string($input['note'] ?? null) : null;
$subBranchProvided = array_key_exists('sub_branch_id', $input);
$subBranchId = $subBranchProvided ? api_int($input['sub_branch_id'] ?? null) : null;

$db = db();
$stmt = $db->prepare(
    'SELECT id, account_id, is_system, sub_branch_id, name, phone, address, note '
    . 'FROM customers WHERE id = ? AND deleted_at IS NULL'
);
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    api_error('Customer not found', 404);
}
if ((int) $customer['is_system'] === 1) {
    api_error('System customer cannot be edited', 422);
}

$accountId = $customer['account_id'] ? (int) $customer['account_id'] : null;
$subBranchChanged = $subBranchProvided && (int) ($customer['sub_branch_id'] ?? 0) !== (int) ($subBranchId ?? 0);
if ($phoneProvided && $phone && $accountId) {
    $phoneCheck = $db->prepare('SELECT id FROM customer_accounts WHERE phone = ? AND id <> ? LIMIT 1');
    $phoneCheck->execute([$phone, $accountId]);
    if ($phoneCheck->fetch()) {
        api_error('Portal phone already exists', 409);
    }
}

$fields = ['name = ?'];
$params = [$name];
if ($phoneProvided) {
    $fields[] = 'phone = ?';
    $params[] = $phone;
}
if ($addressProvided) {
    $fields[] = 'address = ?';
    $params[] = $address;
}
if ($noteProvided) {
    $fields[] = 'note = ?';
    $params[] = $note;
}
if ($subBranchProvided) {
    $fields[] = 'sub_branch_id = ?';
    $params[] = $subBranchId;
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;

$db->beginTransaction();
try {
    if ($accountId) {
        $customerUpdate = $db->prepare(
            'UPDATE customers SET ' . implode(', ', $fields) . ' WHERE account_id = ? AND deleted_at IS NULL'
        );
        $customerUpdate->execute([...$params, $accountId]);

        $accountFields = [];
        $accountParams = [];
        if ($phoneProvided) {
            $accountFields[] = 'phone = ?';
            $accountParams[] = $phone;
        }
        if ($subBranchProvided) {
            $accountFields[] = 'sub_branch_id = ?';
            $accountParams[] = $subBranchId;
        }
        if (!empty($accountFields)) {
            $accountFields[] = 'updated_at = NOW()';
            $accountFields[] = 'updated_by_user_id = ?';
            $accountParams[] = $user['id'] ?? null;
            $accountParams[] = $accountId;
            $accountUpdate = $db->prepare(
                'UPDATE customer_accounts SET ' . implode(', ', $accountFields) . ' WHERE id = ?'
            );
            $accountUpdate->execute($accountParams);
        }

        if ($subBranchChanged) {
            $orderUpdate = $db->prepare(
                'UPDATE orders o '
                . 'JOIN customers c ON c.id = o.customer_id '
                . 'SET o.sub_branch_id = ?, o.updated_at = NOW(), o.updated_by_user_id = ? '
                . 'WHERE c.account_id = ? '
                . 'AND c.deleted_at IS NULL '
                . 'AND o.deleted_at IS NULL '
                . 'AND o.fulfillment_status IN (\'in_shipment\',\'main_branch\',\'pending_receipt\')'
            );
            $orderUpdate->execute([$subBranchId, $user['id'] ?? null, $accountId]);
        }
    } else {
        $customerUpdate = $db->prepare(
            'UPDATE customers SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL'
        );
        $customerUpdate->execute([...$params, $customerId]);

        if ($subBranchChanged) {
            $orderUpdate = $db->prepare(
                'UPDATE orders SET sub_branch_id = ?, updated_at = NOW(), updated_by_user_id = ? '
                . 'WHERE customer_id = ? '
                . 'AND deleted_at IS NULL '
                . 'AND fulfillment_status IN (\'in_shipment\',\'main_branch\',\'pending_receipt\')'
            );
            $orderUpdate->execute([$subBranchId, $user['id'] ?? null, $customerId]);
        }
    }

    $db->commit();

    $after = array_merge($customer, [
        'name' => $name,
        'phone' => $phoneProvided ? $phone : $customer['phone'],
        'address' => $addressProvided ? $address : $customer['address'],
        'note' => $noteProvided ? $note : $customer['note'],
        'sub_branch_id' => $subBranchProvided ? $subBranchId : $customer['sub_branch_id'],
    ]);
    audit_log($user, 'customer.update_info', 'customer', $customerId, $customer, $after);
} catch (PDOException $e) {
    $db->rollBack();
    api_error('Failed to update customer info', 500);
}

api_json(['ok' => true]);
