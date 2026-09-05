<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/response.php';

function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function requireLogin(): array {
    if (!isLoggedIn()) {
        if (isApiRequest()) {
            jsonError('Authentication required', 'UNAUTHORIZED', 401);
        } else {
            $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
            if (!str_contains($currentScript, 'login.php')) {
                @session_write_close();
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            }
        }
    }
    return $_SESSION['user'] ?? [];
}

function requireRole($roles): array {
    $user = requireLogin();
    $allowed = is_array($roles) ? $roles : [$roles];
    
    if (!in_array($user['role'] ?? '', $allowed, true)) {
        if (isApiRequest()) {
            jsonError('Forbidden: Insufficient privileges', 'FORBIDDEN', 403);
        } else {
            $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
            if (str_contains($currentScript, 'dashboard/index.php')) {
                return $user;
            }
            @session_write_close();
            header('Location: ' . BASE_URL . '/dashboard/index.php?error=unauthorized');
            exit;
        }
    }
    return $user;
}

function getAuthOrgId(): int {
    $user = requireLogin();
    return (int)($user['organization_id'] ?? 0);
}

function isApiRequest(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, '/api/') || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
}

function sanitizeInput(string $input): string {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

function loginUser(array $user): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['user'] = $user;
    @session_regenerate_id(true);
    @session_write_close();
}

function logoutUser(): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            '/', $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();
    @session_write_close();
}
