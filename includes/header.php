<?php
// includes/header.php
// No X-Frame-Options, no CSP, no CSRF token in nav logout form
$role = $_SESSION['role'] ?? 'guest';
$name = $_SESSION['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- No security headers: no X-Content-Type-Options, no X-Frame-Options, no CSP -->
  <title><?= isset($pageTitle) ? $pageTitle : APP_NAME ?></title>
  <link rel="stylesheet" href="/vulnerable_app/public/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<nav class="navbar">
  <div class="nav-brand">
    <i class="fa fa-book-open"></i>
    <span><?= APP_NAME ?></span>
  </div>
  <ul class="nav-links">
    <?php if ($role === 'admin'): ?>
      <li><a href="/vulnerable_app/admin/dashboard.php"><i class="fa fa-tachometer-alt"></i> Dashboard</a></li>
      <li><a href="/vulnerable_app/admin/books.php"><i class="fa fa-book"></i> Books</a></li>
      <li><a href="/vulnerable_app/admin/members.php"><i class="fa fa-users"></i> Members</a></li>
      <li><a href="/vulnerable_app/admin/borrowings.php"><i class="fa fa-exchange-alt"></i> Borrowings</a></li>
    <?php elseif ($role === 'member'): ?>
      <li><a href="/vulnerable_app/member/dashboard.php"><i class="fa fa-home"></i> Home</a></li>
      <li><a href="/vulnerable_app/member/browse.php"><i class="fa fa-search"></i> Browse Books</a></li>
      <li><a href="/vulnerable_app/member/my_borrowings.php"><i class="fa fa-book"></i> My Borrowings</a></li>
    <?php endif; ?>
  </ul>
  <div class="nav-user">
    <span class="user-badge <?= $role ?>">
      <i class="fa fa-user-circle"></i> <?= $name ?> (<?= ucfirst($role) ?>)
    </span>
    <!-- Logout via GET link — no CSRF token, no POST required -->
    <a href="/vulnerable_app/auth/logout.php" class="btn-logout">
      <i class="fa fa-sign-out-alt"></i> Logout
    </a>
  </div>
</nav>

<main class="main-content">
