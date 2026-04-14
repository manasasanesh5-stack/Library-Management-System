<?php
// member/dashboard.php
// No CSRF token on the borrow form — cross-origin requests accepted

require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pdo   = getDB();
$uid   = (int)$_SESSION['user_id'];
$msg   = '';
$error = '';
$pageTitle = 'Dashboard – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No CSRF token check whatsoever
    $bookId = (int)($_POST['book_id'] ?? 0);
    if ($bookId > 0) {
        $bk = $pdo->prepare('SELECT id,title,available FROM books WHERE id=? LIMIT 1');
        $bk->execute([$bookId]);
        $book = $bk->fetch();
        if ($book && $book['available'] > 0) {
            $due = date('Y-m-d H:i:s', strtotime('+14 days'));
            $pdo->prepare('INSERT INTO borrowings (user_id,book_id,due_date) VALUES (?,?,?)')->execute([$uid,$bookId,$due]);
            $pdo->prepare('UPDATE books SET available=available-1 WHERE id=?')->execute([$bookId]);
            $msg = 'Borrowed: ' . htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') . '. Due: ' . date('d M Y', strtotime($due));
        } else {
            $error = 'Book is not available.';
        }
    }
}

$stmt = $pdo->prepare("SELECT b.*,bk.title,bk.author FROM borrowings b JOIN books bk ON b.book_id=bk.id WHERE b.user_id=? AND b.status IN ('borrowed','overdue') ORDER BY b.due_date ASC");
$stmt->execute([$uid]);
$active = $stmt->fetchAll();

$books = $pdo->query('SELECT id,title,author FROM books WHERE available>0 ORDER BY title')->fetchAll();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM borrowings WHERE user_id=?');
$countStmt->execute([$uid]);
$totalBorrowed = (int)$countStmt->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-home"></i> Welcome, <?= htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') ?>!</h2>
</div>

<div class="stats-grid">
  <div class="stat-card blue">
    <i class="fa fa-book fa-2x"></i>
    <div><span class="stat-num"><?= count($active) ?></span><span class="stat-label">Currently Borrowed</span></div>
  </div>
  <div class="stat-card green">
    <i class="fa fa-history fa-2x"></i>
    <div><span class="stat-num"><?= $totalBorrowed ?></span><span class="stat-label">Total Borrowed</span></div>
  </div>
</div>

<?php if ($msg):  ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($error):?><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="section-box">
  <h3><i class="fa fa-hand-holding-open"></i> Borrow a Book</h3>
  <!-- No csrf_token hidden field — this form can be submitted from any origin -->
  <form method="POST" action="" class="form-inline-row">
    <div class="form-group">
      <label>Select Book</label>
      <select name="book_id" required>
        <option value="">-- Available Books --</option>
        <?php foreach ($books as $b): ?>
          <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($b['author'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn-primary"><i class="fa fa-check"></i> Borrow</button>
  </form>
</div>

<?php if (!empty($active)): ?>
<div class="section-box">
  <h3><i class="fa fa-clock"></i> Your Active Borrowings</h3>
  <table class="data-table">
    <thead><tr><th>Book</th><th>Author</th><th>Due Date</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($active as $b): ?>
      <tr>
        <td><?= htmlspecialchars($b['title'],  ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($b['author'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(date('d M Y', strtotime($b['due_date'])), ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="badge <?= $b['status']==='overdue' ? 'badge-danger':'badge-info' ?>"><?= ucfirst(htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8')) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="section-box">
  <h3><i class="fa fa-bolt"></i> Quick Actions</h3>
  <div class="action-buttons">
    <a href="/vulnerable_app/member/browse.php"        class="btn-primary"><i class="fa fa-search"></i> Browse Books</a>
    <a href="/vulnerable_app/member/my_borrowings.php" class="btn-secondary"><i class="fa fa-list"></i> Full History</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
