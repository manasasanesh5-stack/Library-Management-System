<?php
// includes/header.php  –  Shared page header
// Call startSecureSession() & requireLogin() BEFORE including this file.

$csrf = generateCsrfToken();
$role = $_SESSION['role'] ?? 'guest';
$name = $_SESSION['name'] ?? '';

// Compute project base path dynamically.
// Examples:
//   /admin/books.php              -> ''
//   /library_system/admin/books.php -> '/library_system'
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}

$assetCssPath = $basePath . '/public/css/style.css';
$logoutPath   = $basePath . '/auth/logout.php';

$dashboardPath    = '#';
$booksPath        = '#';
$membersPath      = '#';
$borrowingsPath   = '#';
$auditPath        = '#';
$memberHomePath   = '#';
$browsePath       = '#';
$myBorrowingsPath = '#';
$profilePath      = '#';

if ($role === 'admin') {
    $dashboardPath  = $basePath . '/admin/dashboard.php';
    $booksPath      = $basePath . '/admin/books.php';
    $membersPath    = $basePath . '/admin/members.php';
    $borrowingsPath = $basePath . '/admin/borrowings.php';
    $auditPath      = $basePath . '/admin/audit.php';
    $profilePath    = $basePath . '/admin/profile.php';
} elseif ($role === 'member') {
    $memberHomePath   = $basePath . '/member/dashboard.php';
    $browsePath       = $basePath . '/member/browse.php';
    $myBorrowingsPath = $basePath . '/member/my_borrowings.php';
    $profilePath      = $basePath . '/member/profile.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Security meta headers (CSP enforced server-side via .htaccess too) -->
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="X-Frame-Options" content="DENY">
  <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
  <title><?= e($pageTitle ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e($assetCssPath) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<nav class="navbar">
  <div class="nav-brand">
    <i class="fa fa-book-open"></i>
    <span><?= e(APP_NAME) ?></span>
  </div>

  <ul class="nav-links">
    <?php if ($role === 'admin'): ?>
      <li><a href="<?= e($dashboardPath) ?>"><i class="fa fa-tachometer-alt"></i> Dashboard</a></li>
      <li><a href="<?= e($booksPath) ?>"><i class="fa fa-books"></i> Books</a></li>
      <li><a href="<?= e($membersPath) ?>"><i class="fa fa-users"></i> Members</a></li>
      <li><a href="<?= e($borrowingsPath) ?>"><i class="fa fa-exchange-alt"></i> Borrowings</a></li>
      <li><a href="<?= e($auditPath) ?>"><i class="fa fa-shield-alt"></i> Audit Log</a></li>
    <?php elseif ($role === 'member'): ?>
      <li><a href="<?= e($memberHomePath) ?>"><i class="fa fa-home"></i> Home</a></li>
      <li><a href="<?= e($browsePath) ?>"><i class="fa fa-search"></i> Browse Books</a></li>
      <li><a href="<?= e($myBorrowingsPath) ?>"><i class="fa fa-book"></i> My Borrowings</a></li>
    <?php endif; ?>
  </ul>

  <div class="nav-user">
    <a href="<?= e($profilePath) ?>" class="user-badge <?= e($role) ?>"
       style="text-decoration:none; cursor:pointer;" title="My Profile">
      <i class="fa fa-user-circle"></i> <?= e($name) ?> (<?= e(ucfirst($role)) ?>)
    </a>

    <form method="POST" action="<?= e($logoutPath) ?>" style="display:inline">
      <?= csrfField() ?>
      <button type="submit" class="btn-logout">
        <i class="fa fa-sign-out-alt"></i> Logout
      </button>
    </form>
  </div>
</nav>

<main class="main-content">