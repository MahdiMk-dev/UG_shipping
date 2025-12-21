<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$username = trim((string) ($input['username'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Username and password are required']);
    exit;
}

$stmt = db()->prepare(
    'SELECT u.id, u.name, u.username, u.password_hash, u.role_id, r.name AS role, '
    . 'u.branch_id, b.country_id AS branch_country_id '
    . 'FROM users u '
    . 'LEFT JOIN roles r ON r.id = u.role_id '
    . 'LEFT JOIN branches b ON b.id = u.branch_id '
    . 'WHERE u.username = ? AND u.deleted_at IS NULL '
    . 'LIMIT 1'
);
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid credentials']);
    exit;
}

unset($user['password_hash']);

auth_login($user);

echo json_encode(['ok' => true, 'user' => $user]);
