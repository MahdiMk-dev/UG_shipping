<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

function require_role(array $roles): array
{
    $user = auth_require_user();
    $roleName = $user['role'] ?? null;

    if ($roleName === null || !in_array($roleName, $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }

    return $user;
}

function is_read_only_role(array $user): bool
{
    return in_array($user['role'] ?? '', ['Sub Branch', 'Warehouse', 'Staff'], true);
}

function get_branch_country_id(array $user): ?int
{
    $countryId = $user['branch_country_id'] ?? null;
    if ($countryId !== null && $countryId !== '') {
        return (int) $countryId;
    }

    $branchId = $user['branch_id'] ?? null;
    if (!$branchId) {
        return null;
    }

    $stmt = db()->prepare('SELECT country_id FROM branches WHERE id = ?');
    $stmt->execute([$branchId]);
    $row = $stmt->fetch();

    if (!$row || $row['country_id'] === null || $row['country_id'] === '') {
        return null;
    }

    return (int) $row['country_id'];
}
