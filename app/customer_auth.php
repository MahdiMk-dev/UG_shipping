<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function customer_auth_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function customer_auth_user(): ?array
{
    customer_auth_start_session();

    return $_SESSION['customer_account'] ?? null;
}

function customer_auth_require_user(): array
{
    $customer = customer_auth_user();
    if (!$customer) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    return $customer;
}

function customer_auth_login(array $customer): void
{
    customer_auth_start_session();
    $_SESSION['customer_account'] = $customer;
}

function customer_auth_logout(): void
{
    customer_auth_start_session();
    unset($_SESSION['customer_account']);
}
