<?php
// ============================================================
// includes/config.php  –  Secure configuration & DB connection
// ============================================================

// ---- Environment Helpers -----------------------------------
function envValue(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

function appIsHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }

    return false;
}

function normaliseBasePath(string $path): string
{
    $path = trim($path);
    if ($path === '' || $path === '/') {
        return '';
    }

    $path = '/' . trim($path, '/');
    return $path;
}

// ---- App / Environment Settings ----------------------------
define('APP_ENV', envValue('APP_ENV', 'development'));
define('APP_DEBUG', APP_ENV !== 'production');

define('APP_NAME', envValue('APP_NAME', 'SecureLibrary'));
define('APP_BASE_PATH', normaliseBasePath(envValue('APP_BASE_PATH', '')));

// ---- Error Reporting ---------------------------------------
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---- Timezone ----------------------------------------------
date_default_timezone_set(envValue('APP_TIMEZONE', 'UTC'));

// ---- Secure Session Configuration --------------------------
define('SESSION_IDLE_TIMEOUT', 1800);      // 30-minute idle timeout
define('SESSION_REGEN_INTERVAL', 300);     // Regenerate session ID every 5 min

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', appIsHttps() ? '1' : '0');
ini_set('session.gc_maxlifetime', (string)SESSION_IDLE_TIMEOUT);

// ---- Database Credentials ----------------------------------
define('DB_HOST', envValue('DB_HOST', 'localhost'));
define('DB_PORT', envValue('DB_PORT', '3306'));
define('DB_NAME', envValue('DB_NAME', 'library_db'));
define('DB_USER', envValue('DB_USER', 'root'));
define('DB_PASS', envValue('DB_PASS', ''));
define('DB_CHARSET', envValue('DB_CHARSET', 'utf8mb4'));

// ---- Security Constants ------------------------------------
define('MAX_LOGIN_ATTEMPTS', 5);           // Lock after 5 failed logins
define('LOCKOUT_TIME', 900);               // 15 minutes lock-out (seconds)

// ---- Application Constants ---------------------------------
define('BORROW_DAYS', 14);                 // Default loan period

// ============================================================
// URL / Redirect Helpers
// ============================================================
function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return APP_BASE_PATH . $path;
}

function redirectTo(string $path, int $statusCode = 302): void
{
    header('Location: ' . url($path), true, $statusCode);
    exit;
}

// ============================================================
// Singleton PDO connection with secure options
// ============================================================
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB Connection Error: ' . $e->getMessage());
            http_response_code(503);
            exit('Service temporarily unavailable. Please try again later.');
        }
    }

    return $pdo;
}

// ============================================================
// Secure Session Management
// ============================================================
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('securelibrary_session');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => APP_BASE_PATH !== '' ? APP_BASE_PATH : '/',
            'domain'   => '',
            'secure'   => appIsHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
    }

    // Enforce idle timeout server-side
    if (isset($_SESSION['last_activity']) &&
        (time() - (int)$_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 3600,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }

        session_destroy();
        session_start();
    }

    $_SESSION['last_activity'] = time();

    // Regenerate session ID periodically to reduce fixation risk
    if (!isset($_SESSION['last_regen'])) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    } elseif ((time() - (int)$_SESSION['last_regen']) > SESSION_REGEN_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

// ============================================================
// CSRF Token Helpers
// ============================================================
function generateCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        startSecureSession();
    }

    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        strlen($_SESSION['csrf_token']) < 64
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        startSecureSession();
    }

    return isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && $token !== ''
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(generateCsrfToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        . '">';
}

// ============================================================
// Output Sanitisation
// ============================================================
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ============================================================
// Request / Utility Helpers
// ============================================================
function getClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ============================================================
// Audit Logging
// ============================================================
function auditLog(string $action, ?string $detail = null, ?int $userId = null): void
{
    try {
        $pdo = getDB();
        $uid = $userId ?? ($_SESSION['user_id'] ?? null);
        $ip  = getClientIp();

        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (user_id, action, detail, ip_address) VALUES (?,?,?,?)'
        );
        $stmt->execute([$uid, $action, $detail, $ip]);
    } catch (Throwable $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

// ============================================================
// Access Control Helpers
// ============================================================
function requireLogin(): void
{
    startSecureSession();

    if (empty($_SESSION['user_id'])) {
        redirectTo('/auth/login.php');
    }
}

function requireAdmin(): void
{
    requireLogin();

    if (($_SESSION['role'] ?? '') !== 'admin') {
        auditLog('UNAUTHORISED_ACCESS', 'Attempted admin access');
        http_response_code(403);
        include __DIR__ . '/../public/403.php';
        exit;
    }
}

function requireMember(): void
{
    requireLogin();

    if (($_SESSION['role'] ?? '') !== 'member') {
        auditLog('UNAUTHORISED_ACCESS', 'Attempted member access');
        http_response_code(403);
        include __DIR__ . '/../public/403.php';
        exit;
    }
}

// ============================================================
// Input Validation Helpers
// ============================================================
function sanitiseString(string $input, int $maxLen = 255): string
{
    return mb_substr(trim(strip_tags($input)), 0, $maxLen);
}

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && strlen($email) <= 150;
}

function validatePassword(string $pw): bool
{
    return strlen($pw) >= 8
        && preg_match('/[A-Z]/', $pw)
        && preg_match('/[a-z]/', $pw)
        && preg_match('/[0-9]/', $pw)
        && preg_match('/[\W_]/', $pw);
}

// ============================================================
// Rate-limiting / Brute-Force Protection
// ============================================================
function isLockedOut(string $email, string $ip): bool
{
    $pdo   = getDB();
    $since = date('Y-m-d H:i:s', time() - LOCKOUT_TIME);

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE (email = ? OR ip_address = ?)
           AND success = 0
           AND attempted_at > ?'
    );
    $stmt->execute([$email, $ip, $since]);

    return (int)$stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
}

function recordLoginAttempt(string $email, string $ip, bool $success): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (email, ip_address, success) VALUES (?,?,?)'
    );
    $stmt->execute([$email, $ip, $success ? 1 : 0]);
}