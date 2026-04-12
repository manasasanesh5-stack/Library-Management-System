<?php
// index.php  –  Root entry point for SecureLibrary (intermediate version)
require_once __DIR__ . '/includes/config.php';
startSecureSession();

$pageTitle = 'Welcome – ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="section-box">
  <h2>Welcome to <?= e(APP_NAME) ?></h2>
  <p>This project has been initialized successfully.</p>
  <p>Authentication and dashboard modules will be added in the next development stage.</p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>