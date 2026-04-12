<?php
// index.php  –  Root entry point: redirect based on session role
require_once __DIR__ . '/includes/config.php';
startSecureSession();


redirectTo('/auth/login.php');