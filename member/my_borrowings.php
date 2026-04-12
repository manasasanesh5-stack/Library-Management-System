<?php
// member/my_borrowings.php  –  Member Borrowing History
require_once __DIR__ . '/../includes/config.php';
startSecureSession();
requireLogin();

if (($_SESSION['role'] ?? '') !== 'member') {
    redirectTo('/admin/dashboard.php');
}

$pdo       = getDB();
$uid       = (int)$_SESSION['user_id'];
$pageTitle = 'My Borrowings – ' . APP_NAME;
$filter    = sanitiseString($_GET['filter'] ?? 'all', 10);

$sqlMap = [
    'all'      => '1=1',
    'active'   => "b.status IN ('borrowed','overdue')",
    'returned' => "b.status='returned'",
    'overdue'  => "b.status='overdue'",
];
$where = $sqlMap[$filter] ?? '1=1';

$stmt = $pdo->prepare(
    "SELECT b.*, bk.title, bk.author, bk.genre
     FROM borrowings b
     JOIN books bk ON b.book_id = bk.id
     WHERE b.user_id=? AND $where
     ORDER BY b.borrowed_at DESC"
);
$stmt->execute([$uid]);
$records = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-list"></i> My Borrowing History</h2>
</div>

<div class="section-box">
  <div class="tab-bar">
    <a href="?filter=all"      class="tab <?= $filter==='all' ? 'active' : '' ?>">All</a>
    <a href="?filter=active"   class="tab <?= $filter==='active' ? 'active' : '' ?>">Active</a>
    <a href="?filter=overdue"  class="tab <?= $filter==='overdue' ? 'active' : '' ?>">Overdue</a>
    <a href="?filter=returned" class="tab <?= $filter==='returned' ? 'active' : '' ?>">Returned</a>
  </div>

  <table class="data-table">
    <thead>
      <tr>
        <th>Book</th><th>Author</th><th>Genre</th>
        <th>Borrowed On</th><th>Due Date</th><th>Returned</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td><?= e($r['title']) ?></td>
        <td><?= e($r['author']) ?></td>
        <td><?= e($r['genre']) ?></td>
        <td><?= e(date('d M Y', strtotime($r['borrowed_at']))) ?></td>
        <td><?= e(date('d M Y', strtotime($r['due_date']))) ?></td>
        <td><?= $r['returned_at'] ? e(date('d M Y', strtotime($r['returned_at']))) : '–' ?></td>
        <td>
          <span class="badge <?=
            $r['status'] === 'returned' ? 'badge-success' :
            ($r['status'] === 'overdue' ? 'badge-danger' : 'badge-info') ?>">
            <?= ucfirst(e($r['status'])) ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($records)): ?>
        <tr><td colspan="7" class="empty-row">No borrowing records found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>