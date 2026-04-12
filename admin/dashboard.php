<?php
// admin/dashboard.php  –  Admin Overview Dashboard
require_once __DIR__ . '/../includes/config.php';
startSecureSession();
requireAdmin();

$pageTitle = 'Admin Dashboard – ' . APP_NAME;

$pdo   = getDB();
$stats = [];

$stats['total_books']    = (int)$pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
$stats['total_members']  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn();
$stats['active_borrows'] = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='borrowed'")->fetchColumn();
$stats['overdue']        = (int)$pdo->query(
    "SELECT COUNT(*) FROM borrowings WHERE status='borrowed' AND due_date < NOW()"
)->fetchColumn();

// Recent activity
$recent = $pdo->query(
    "SELECT a.action, a.detail, a.ip_address, a.created_at, u.name
     FROM audit_log a LEFT JOIN users u ON a.user_id = u.id
     ORDER BY a.created_at DESC LIMIT 10"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-tachometer-alt"></i> Admin Dashboard</h2>
  <span class="page-sub">Welcome back, <?= e($_SESSION['name']) ?></span>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
  <div class="stat-card blue">
    <i class="fa fa-books fa-2x"></i>
    <div>
      <span class="stat-num"><?= $stats['total_books'] ?></span>
      <span class="stat-label">Total Books</span>
    </div>
  </div>
  <div class="stat-card green">
    <i class="fa fa-users fa-2x"></i>
    <div>
      <span class="stat-num"><?= $stats['total_members'] ?></span>
      <span class="stat-label">Members</span>
    </div>
  </div>
  <div class="stat-card orange">
    <i class="fa fa-exchange-alt fa-2x"></i>
    <div>
      <span class="stat-num"><?= $stats['active_borrows'] ?></span>
      <span class="stat-label">Active Borrowings</span>
    </div>
  </div>
  <div class="stat-card red">
    <i class="fa fa-exclamation-circle fa-2x"></i>
    <div>
      <span class="stat-num"><?= $stats['overdue'] ?></span>
      <span class="stat-label">Overdue</span>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="section-box">
  <h3><i class="fa fa-bolt"></i> Quick Actions</h3>
  <div class="action-buttons">
    <a href="<?= e(url('/admin/books.php?action=add')) ?>" class="btn-primary"><i class="fa fa-plus"></i> Add Book</a>
    <a href="<?= e(url('/admin/members.php')) ?>"          class="btn-secondary"><i class="fa fa-users"></i> Manage Members</a>
    <a href="<?= e(url('/admin/borrowings.php')) ?>"       class="btn-secondary"><i class="fa fa-exchange-alt"></i> Borrowings</a>
    <a href="<?= e(url('/admin/audit.php')) ?>"            class="btn-secondary"><i class="fa fa-shield-alt"></i> Audit Log</a>
  </div>
</div>

<!-- Recent Audit Activity -->
<div class="section-box">
  <h3><i class="fa fa-history"></i> Recent Activity</h3>
  <table class="data-table">
    <thead>
      <tr><th>Time</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $row): ?>
      <tr>
        <td><?= e($row['created_at']) ?></td>
        <td><?= e($row['name'] ?? 'System') ?></td>
        <td><span class="badge <?= str_contains($row['action'],'FAIL')||str_contains($row['action'],'LOCK') ? 'badge-danger' : 'badge-info' ?>">
          <?= e($row['action']) ?>
        </span></td>
        <td><?= e($row['detail'] ?? '') ?></td>
        <td><?= e($row['ip_address']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>