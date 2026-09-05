<?php
// QUIZLY Global Configuration & Compatibility Helpers (PHP 7.4+ & 8.x Compatible)

// Output buffering & error suppression for clean JSON API outputs
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
if (ob_get_level() === 0) {
    ob_start();
}

// Load environment variables from .env if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        list($envKey, $envVal) = explode('=', $line, 2);
        $envKey = trim($envKey);
        $envVal = trim($envVal);
        if (preg_match('/^(["\'])(.*)\1$/', $envVal, $m)) {
            $envVal = $m[2];
        }
        if (getenv($envKey) === false) {
            putenv("$envKey=$envVal");
        }
        if (!isset($_ENV[$envKey])) {
            $_ENV[$envKey] = $envVal;
        }
    }
}

// Global Centralized Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_path', '/');
    @ini_set('session.cookie_httponly', 1);
    @ini_set('session.use_only_cookies', 1);
    
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    if (function_exists('session_set_cookie_params')) {
        @session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    @session_start();
}

// Polyfills for PHP 7.4 Compatibility on Shared Hosting (InfinityFree/cPanel)
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

define('APP_NAME', 'QUIZLY');
define('APP_TAGLINE', 'Production-Grade Real-Time Quiz Platform');

// Dynamic BASE_URL detection (Supports root domain, subfolder, or XAMPP localhost automatically)
if (!defined('BASE_URL')) {
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    $projRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
    if ($docRoot !== '' && strpos($projRoot, $docRoot) === 0) {
        $baseUrl = substr($projRoot, strlen($docRoot));
    } else {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $baseUrl = (strpos($scriptDir, '/quiz') !== false) ? '/quiz' : '';
    }
    define('BASE_URL', rtrim($baseUrl, '/'));
}

// Auto-detect Localhost vs InfinityFree / Live Hosting Server
$hostHeader = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$isLocalhost = (
    strpos($hostHeader, 'localhost') !== false ||
    strpos($hostHeader, '127.0.0.1') !== false ||
    strpos($hostHeader, '10.') !== false ||
    strpos($hostHeader, '192.168.') !== false
);

if ($isLocalhost) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_NAME', getenv('DB_NAME') ?: 'quizly_db');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
} else {
    define('DB_HOST', getenv('DB_HOST') ?: 'sql306.infinityfree.com');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_NAME', getenv('DB_NAME') ?: 'if0_42705449_quizly_db');
    define('DB_USER', getenv('DB_USER') ?: 'if0_42705449');
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

define('DB_CHARSET', 'utf8mb4');

// Google Gemini AI Service Settings (Primary)
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash-lite');

// OpenRouter AI Service Settings (Legacy / Fallback)
define('OPENROUTER_API_KEY', getenv('OPENROUTER_API_KEY') ?: '');
define('OPENROUTER_MODEL', getenv('OPENROUTER_MODEL') ?: 'openrouter/free');

// Quiz & Timing Defaults
define('DEFAULT_QUESTION_TIMER', 20); // seconds
define('DEFAULT_MAX_POINTS', 1000);
define('ROOM_CODE_LENGTH', 6);

// Rate Limiting Constants
define('MAX_JOIN_ATTEMPTS_PER_MIN', 10);
define('MAX_ANSWERS_PER_MIN', 30);
