<?php
// index.php  –  Root entry point: redirect based on session role
require_once __DIR__ . '/includes/config.php';
startSecureSession();

if (!empty($_SESSION['user_id'])) {
    $role = ($_SESSION['role'] === 'admin') ? 'admin' : 'member';
    redirectTo('/' . $role . '/dashboard.php');
}

redirectTo('/auth/login.php');