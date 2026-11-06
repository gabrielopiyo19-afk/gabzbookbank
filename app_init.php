<?php
declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): void
{
    start_secure_session();
    if (empty($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new RuntimeException('Your session has expired. Refresh the page and try again.');
    }
}

function require_role(string $role, bool $json = false): void
{
    start_secure_session();
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== $role) {
        if ($json) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Please sign in with the appropriate account.']);
        } else {
            header('Location: login.html');
        }
        exit;
    }
}
