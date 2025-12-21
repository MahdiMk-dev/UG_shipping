<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function auth_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function auth_user(): ?array
{
    auth_start_session();

    return $_SESSION['user'] ?? null;
}

function auth_require_user(): array
{
    $user = auth_user();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    return $user;
}

function auth_login(array $user): void
{
    auth_start_session();
    $_SESSION['user'] = $user;
}

function auth_logout(): void
{
    auth_start_session();
    unset($_SESSION['user']);
}
