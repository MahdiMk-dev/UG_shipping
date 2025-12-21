<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function api_error(string $message, int $status = 400, array $extra = []): void
{
    api_json(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

function api_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        api_error('Method Not Allowed', 405);
    }
}

function api_read_input(): array
{
    $raw = file_get_contents('php://input');
    $trimmed = trim((string) $raw);

    if ($trimmed !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            api_error('Invalid JSON payload', 400);
        }
    }

    return $_POST ?? [];
}

function api_string($value, ?string $default = null): ?string
{
    if ($value === null) {
        return $default;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return $default;
    }

    return $value;
}

function api_int($value, ?int $default = null): ?int
{
    if ($value === null || $value === '') {
        return $default;
    }

    if (!is_numeric($value)) {
        return $default;
    }

    return (int) $value;
}

function api_float($value, ?float $default = null): ?float
{
    if ($value === null || $value === '') {
        return $default;
    }

    if (!is_numeric($value)) {
        return $default;
    }

    return (float) $value;
}

function api_bool($value, bool $default = false): bool
{
    if ($value === null || $value === '') {
        return $default;
    }

    if (is_bool($value)) {
        return $value;
    }

    $value = strtolower((string) $value);
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}
