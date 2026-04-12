<?php
// auth/logout.php  –  Secure logout
require_once __DIR__ . '/../includes/config.php';
startSecureSession();

// Compute project base path dynamically.
// Examples:
//   /auth/logout.php                 -> ''
//   /library_system/auth/logout.php  -> '/library_system'
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}

$loginPath = $basePath . '/auth/login.php';

// Only allow POST with valid CSRF to prevent logout CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    auditLog('LOGOUT', null, $_SESSION['user_id'] ?? null);

    // Destroy session completely
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

header('Location: ' . $loginPath);
exit;