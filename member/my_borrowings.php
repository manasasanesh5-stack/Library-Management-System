<?php
// member/my_borrowings.php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pdo       = getDB();
$uid       = (int)$_SESSION['user_id'];
$filter    = trim($_GET['filter'] ?? 'all');
$pageTitle = 'My Borrowings – ' . APP_NAME;

$sqlMap = [
    'all'      => '1=1',
    'active'   => "b.status IN ('borrowed','overdue')",
    'returned' => "b.status='returned'",
    'overdue'  => "b.status='overdue'",
];
$where = $sqlMap[$filter] ?? '1=1';

$stmt = $pdo->prepare(
    "SELECT b.*,bk.title,bk.author,bk.genre FROM borrowings b JOIN books bk ON b.book_id=bk.id
     WHERE b.user_id=? AND $where ORDER BY b.borrowed_at DESC"
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
    <a href="?filter=all"      class="tab <?= $filter==='all'      ? 'active':'' ?>">All</a>
    <a href="?filter=active"   class="tab <?= $filter==='active'   ? 'active':'' ?>">Active</a>
    <a href="?filter=overdue"  class="tab <?= $filter==='overdue'  ? 'active':'' ?>">Overdue</a>
    <a href="?filter=returned" class="tab <?= $filter==='returned' ? 'active':'' ?>">Returned</a>
  </div>
  <table class="data-table">
    <thead>
      <tr><th>Book</th><th>Author</th><th>Genre</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['title'],  ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($r['author'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($r['genre'],  ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(date('d M Y', strtotime($r['borrowed_at'])), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(date('d M Y', strtotime($r['due_date'])),    ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $r['returned_at'] ? htmlspecialchars(date('d M Y', strtotime($r['returned_at'])), ENT_QUOTES, 'UTF-8') : '–' ?></td>
        <td><span class="badge <?= $r['status']==='returned' ? 'badge-success':($r['status']==='overdue' ? 'badge-danger':'badge-info') ?>"><?= ucfirst(htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8')) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($records)): ?><tr><td colspan="7" class="empty-row">No records found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
