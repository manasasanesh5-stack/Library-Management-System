<?php
// includes/config.php
// VULNERABLE VERSION — No CSRF, No rate-limiting, No RBAC enforcement helpers

// Basic DB credentials
define('DB_HOST',    'localhost');
define('DB_NAME',    'library_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME',   'LibraryApp');

// Plain session start — no security flags set
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        // No cookie_httponly, no SameSite, no use_strict_mode
        // No session_regenerate_id() ever called
    }
}

// Plain PDO connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// Only checks if a user is logged in — does NOT check role
// No role-based access control
function requireLogin(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: /vulnerable_app/auth/login.php');
        exit;
    }
    // Missing: role check — any logged-in user can access any page
}

