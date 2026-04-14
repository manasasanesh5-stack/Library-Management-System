<?php
// admin/dashboard.php
// VULNERABILITY: No role-based access control.
//     Any logged-in user (including members) can visit this URL directly
//     and gain full access to the admin dashboard.

require_once __DIR__ . '/../includes/config.php';
requireLogin(); // Only checks login — does NOT verify role = 'admin'

$pageTitle = 'Dashboard – ' . APP_NAME;
$pdo = getDB();

$stats['total_books']    = (int)$pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
$stats['total_members']  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn();
$stats['active_borrows'] = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='borrowed'")->fetchColumn();
$stats['overdue']        = (int)$pdo->query(
    "SELECT COUNT(*) FROM borrowings WHERE status='borrowed' AND due_date < NOW()"
)->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-tachometer-alt"></i> Admin Dashboard</h2>
  <span class="page-sub">Welcome, <?= htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') ?></span>
</div>

<div class="stats-grid">
  <div class="stat-card blue">
    <i class="fa fa-books fa-2x"></i>
    <div><span class="stat-num"><?= $stats['total_books'] ?></span><span class="stat-label">Total Books</span></div>
  </div>
  <div class="stat-card green">
    <i class="fa fa-users fa-2x"></i>
    <div><span class="stat-num"><?= $stats['total_members'] ?></span><span class="stat-label">Members</span></div>
  </div>
  <div class="stat-card orange">
    <i class="fa fa-exchange-alt fa-2x"></i>
    <div><span class="stat-num"><?= $stats['active_borrows'] ?></span><span class="stat-label">Active Borrowings</span></div>
  </div>
  <div class="stat-card red">
    <i class="fa fa-exclamation-circle fa-2x"></i>
    <div><span class="stat-num"><?= $stats['overdue'] ?></span><span class="stat-label">Overdue</span></div>
  </div>
</div>

<div class="section-box">
  <h3><i class="fa fa-bolt"></i> Quick Actions</h3>
  <div class="action-buttons">
    <a href="/vulnerable_app/admin/books.php?action=add" class="btn-primary"><i class="fa fa-plus"></i> Add Book</a>
    <a href="/vulnerable_app/admin/members.php"          class="btn-secondary"><i class="fa fa-users"></i> Manage Members</a>
    <a href="/vulnerable_app/admin/borrowings.php"       class="btn-secondary"><i class="fa fa-exchange-alt"></i> Borrowings</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
