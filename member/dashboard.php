<?php
// member/dashboard.php  –  Member Home
require_once __DIR__ . '/../includes/config.php';
startSecureSession();
requireLogin();

if (($_SESSION['role'] ?? '') !== 'member') {
    redirectTo('/admin/dashboard.php');
}

$pdo       = getDB();
$uid       = (int)$_SESSION['user_id'];
$pageTitle = 'My Dashboard – ' . APP_NAME;

$activeBorrows = $pdo->prepare(
    "SELECT b.*, bk.title, bk.author, bk.isbn
     FROM borrowings b
     JOIN books bk ON b.book_id = bk.id
     WHERE b.user_id = ? AND b.status IN ('borrowed','overdue')
     ORDER BY b.due_date ASC"
);
$activeBorrows->execute([$uid]);
$active = $activeBorrows->fetchAll();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM borrowings WHERE user_id = ?');
$countStmt->execute([$uid]);
$totalBorrowed = (int)$countStmt->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-home"></i> Welcome, <?= e($_SESSION['name']) ?>!</h2>
</div>

<!-- Summary Cards -->
<div class="stats-grid">
  <div class="stat-card blue">
    <i class="fa fa-book fa-2x"></i>
    <div>
      <span class="stat-num"><?= count($active) ?></span>
      <span class="stat-label">Currently Borrowed</span>
    </div>
  </div>
  <div class="stat-card green">
    <i class="fa fa-history fa-2x"></i>
    <div>
      <span class="stat-num"><?= $totalBorrowed ?></span>
      <span class="stat-label">Total Borrowed (All Time)</span>
    </div>
  </div>
</div>

<!-- Active Borrowings -->
<?php if (!empty($active)): ?>
<div class="section-box">
  <h3><i class="fa fa-clock"></i> Your Active Borrowings</h3>
  <table class="data-table">
    <thead>
      <tr><th>Book</th><th>Author</th><th>Borrowed</th><th>Due Date</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($active as $b): ?>
      <tr>
        <td><?= e($b['title']) ?></td>
        <td><?= e($b['author']) ?></td>
        <td><?= e(date('d M Y', strtotime($b['borrowed_at']))) ?></td>
        <td><?= e(date('d M Y', strtotime($b['due_date']))) ?></td>
        <td>
          <span class="badge <?= $b['status'] === 'overdue' ? 'badge-danger' : 'badge-info' ?>">
            <?= ucfirst(e($b['status'])) ?>
          </span>
          <?php if ($b['status'] === 'overdue'): ?>
            <small class="text-danger"> – Please return immediately</small>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Quick Links -->
<div class="section-box">
  <h3><i class="fa fa-bolt"></i> Quick Actions</h3>
  <div class="action-buttons">
    <a href="<?= e(url('/member/browse.php')) ?>" class="btn-primary"><i class="fa fa-search"></i> Browse Books</a>
    <a href="<?= e(url('/member/my_borrowings.php')) ?>" class="btn-secondary"><i class="fa fa-list"></i> Full History</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>