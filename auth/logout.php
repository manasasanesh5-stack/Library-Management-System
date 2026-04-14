<?php
// auth/logout.php
// Logout triggered by a simple GET request — no CSRF token required.
//     Any page can silently log the user out with an <img> tag or link.

require_once __DIR__ . '/../includes/config.php';
startSession();

// No POST check, no CSRF token verification
session_destroy();
header('Location: /vulnerable_app/auth/login.php');
exit;
