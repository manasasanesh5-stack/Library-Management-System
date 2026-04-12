<?php
// public/403.php  –  Access Denied page
$pageTitle = '403 – Forbidden';

// Compute project base path dynamically.
// Examples:
//   /public/403.php                  -> ''
//   /library_system/public/403.php   -> '/library_system'
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}

$cssPath   = $basePath . '/public/css/style.css';
$loginPath = $basePath . '/auth/login.php';
$jsPath    = $basePath . '/public/js/main.js';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-Frame-Options" content="DENY">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-body">
<div class="auth-container">
  <div class="auth-card" style="text-align:center">
    <div style="color:#dc2626; font-size:4rem; margin-bottom:1rem;">
      <i class="fa fa-ban"></i>
    </div>
    <h1 style="color:#dc2626; margin-bottom:.5rem;">403 – Forbidden</h1>
    <p style="color:#64748b; margin-bottom:1.5rem;">
      You do not have permission to access this page.
    </p>
    <div style="display:flex; gap:.75rem; justify-content:center; flex-wrap:wrap;">
      <button onclick="history.back()" class="btn-secondary">
        <i class="fa fa-arrow-left"></i> Go Back
      </button>
      <a href="<?= htmlspecialchars($loginPath, ENT_QUOTES, 'UTF-8') ?>" class="btn-primary">
        <i class="fa fa-home"></i> Home
      </a>
    </div>
  </div>
</div>
<script src="<?= htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>