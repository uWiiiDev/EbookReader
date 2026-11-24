<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_set_cookie_params([
            'lifetime' => 86400 * 30, // 30 days
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

function isLoggedIn(): bool
{
    initSession();
    return !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? 'User'
    ];
}

function loginUser(int $userId, string $username): void
{
    initSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
}

function logoutUser(): void
{
    initSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

function getCsrfToken(): string
{
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verifyCsrf(): bool
{
    initSession();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string) $token);
}

function isApiRequest(): bool
{
    if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
        return true;
    }
    if (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
        return true;
    }
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, '/api/');
}

function requireAuth(): void
{
    initSession();

    if (!hasUsers()) {
        if (isApiRequest()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Setup erforderlich. Bitte erstellen Sie zuerst ein Administratorkonto.']);
            exit;
        }
        header('Location: login.php?setup=1');
        exit;
    }

    if (!isLoggedIn()) {
        if (isApiRequest()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Nicht autorisiert. Bitte einloggen.']);
            exit;
        }
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header('Location: login.php?redirect=' . $redirect);
        exit;
    }
}

function requireCsrf(): void
{
    if (!verifyCsrf()) {
        http_response_code(403);
        if (isApiRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Ungültiges oder abgelaufenes CSRF-Token. Bitte Seite neu laden.']);
        } else {
            echo 'Ungültiges CSRF-Token. Bitte gehen Sie zurück und versuchen Sie es erneut.';
        }
        exit;
    }
}
