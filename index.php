<?php
// index.php
require_once __DIR__ . '/includes/config.php';
startSession();
if (!empty($_SESSION['user_id'])) {
    header('Location: /vulnerable_app/' . $_SESSION['role'] . '/dashboard.php');
} else {
    header('Location: /vulnerable_app/auth/login.php');
}
exit;
