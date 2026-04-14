<?php
// admin/borrowings.php
// No CSRF tokens on Issue / Return forms
// No role check

require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pdo    = getDB();
$msg    = '';
$error  = '';
$pageTitle = 'Borrowings – ' . APP_NAME;

// Mark overdue
$pdo->query("UPDATE borrowings SET status='overdue' WHERE status='borrowed' AND due_date < NOW()");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No CSRF token check
    $postAction = trim($_POST['post_action'] ?? '');

    if ($postAction === 'issue') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $bookId = (int)($_POST['book_id'] ?? 0);
        if ($userId > 0 && $bookId > 0) {
            $due  = date('Y-m-d H:i:s', strtotime('+14 days'));
            $pdo->prepare('INSERT INTO borrowings (user_id,book_id,due_date) VALUES (?,?,?)')->execute([$userId,$bookId,$due]);
            $pdo->prepare('UPDATE books SET available=available-1 WHERE id=? AND available>0')->execute([$bookId]);
            $msg = 'Book issued.';
        }
    } elseif ($postAction === 'return') {
        $borrowId = (int)($_POST['borrow_id'] ?? 0);
        $row = $pdo->prepare('SELECT book_id FROM borrowings WHERE id=?');
        $row->execute([$borrowId]);
        $rec = $row->fetch();
        if ($rec) {
            $pdo->prepare("UPDATE borrowings SET status='returned', returned_at=NOW() WHERE id=?")->execute([$borrowId]);
            $pdo->prepare('UPDATE books SET available=available+1 WHERE id=?')->execute([$rec['book_id']]);
            $msg = 'Book returned.';
        }
    }
}

$filter  = trim($_GET['filter'] ?? 'active');
$sqlMap  = ['active' => "status IN ('borrowed','overdue')", 'returned' => "status='returned'", 'overdue' => "status='overdue'"];
$where   = $sqlMap[$filter] ?? $sqlMap['active'];

$borrows = $pdo->query(
    "SELECT b.*, u.name AS member_name, bk.title AS book_title
     FROM borrowings b JOIN users u ON b.user_id=u.id JOIN books bk ON b.book_id=bk.id
     WHERE $where ORDER BY b.borrowed_at DESC LIMIT 200"
)->fetchAll();

$members = $pdo->query("SELECT id,name FROM users WHERE role='member' ORDER BY name")->fetchAll();
$avBooks = $pdo->query("SELECT id,title FROM books WHERE available>0 ORDER BY title")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-exchange-alt"></i> Borrowings</h2>
</div>

<?php if ($msg):  ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error):?><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="section-box">
  <h3><i class="fa fa-hand-holding-open"></i> Issue Book</h3>
  <!-- No csrf_token -->
  <form method="POST" class="form-inline-row">
    <input type="hidden" name="post_action" value="issue">
    <div class="form-group">
      <label>Member</label>
      <select name="user_id" required>
        <option value="">-- Member --</option>
        <?php foreach ($members as $m): ?>
          <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Book</label>
      <select name="book_id" required>
        <option value="">-- Book --</option>
        <?php foreach ($avBooks as $bk): ?>
          <option value="<?= (int)$bk['id'] ?>"><?= htmlspecialchars($bk['title'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn-primary"><i class="fa fa-check"></i> Issue</button>
  </form>
</div>

<div class="section-box">
  <div class="tab-bar">
    <a href="?filter=active"   class="tab <?= $filter==='active'   ? 'active':'' ?>">Active</a>
    <a href="?filter=overdue"  class="tab <?= $filter==='overdue'  ? 'active':'' ?>">Overdue</a>
    <a href="?filter=returned" class="tab <?= $filter==='returned' ? 'active':'' ?>">Returned</a>
  </div>
  <table class="data-table">
    <thead>
      <tr><th>#</th><th>Member</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Status</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($borrows as $row): ?>
      <tr>
        <td><?= (int)$row['id'] ?></td>
        <td><?= htmlspecialchars($row['member_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($row['book_title'],  ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(date('d M Y', strtotime($row['borrowed_at'])), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(date('d M Y', strtotime($row['due_date'])),    ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="badge <?= $row['status']==='returned' ? 'badge-success' : ($row['status']==='overdue' ? 'badge-danger':'badge-info') ?>"><?= ucfirst(htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8')) ?></span></td>
        <td>
          <?php if ($row['status'] !== 'returned'): ?>
          <!-- No csrf_token on return form -->
          <form method="POST" style="display:inline">
            <input type="hidden" name="post_action" value="return">
            <input type="hidden" name="borrow_id"   value="<?= (int)$row['id'] ?>">
            <button type="submit" class="btn-sm btn-success"><i class="fa fa-undo"></i> Return</button>
          </form>
          <?php else: ?><span class="text-muted">–</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($borrows)): ?><tr><td colspan="7" class="empty-row">No records.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
