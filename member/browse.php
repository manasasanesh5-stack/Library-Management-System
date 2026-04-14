<?php
// member/browse.php
// No CSRF token on borrow form

require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pdo   = getDB();
$uid   = (int)$_SESSION['user_id'];
$msg   = '';
$error = '';
$pageTitle = 'Browse Books – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No CSRF token check
    $bookId = (int)($_POST['book_id'] ?? 0);
    if ($bookId > 0) {
        $bk = $pdo->prepare('SELECT id,title,available FROM books WHERE id=? LIMIT 1');
        $bk->execute([$bookId]);
        $book = $bk->fetch();
        if ($book && $book['available'] > 0) {
            $due = date('Y-m-d H:i:s', strtotime('+14 days'));
            $pdo->prepare('INSERT INTO borrowings (user_id,book_id,due_date) VALUES (?,?,?)')->execute([$uid,$bookId,$due]);
            $pdo->prepare('UPDATE books SET available=available-1 WHERE id=?')->execute([$bookId]);
            $msg = 'Borrowed "' . htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') . '"!';
        } else {
            $error = 'Book not available.';
        }
    }
}

$search = trim($_GET['q']     ?? '');
$genre  = trim($_GET['genre'] ?? '');

$sql    = 'SELECT * FROM books WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (title LIKE ? OR author LIKE ?)';
    $like  = "%$search%";
    $params = [$like, $like];
}
if ($genre !== '') {
    $sql .= ' AND genre=?';
    $params[] = $genre;
}
$sql .= ' ORDER BY title';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$genres = $pdo->query('SELECT DISTINCT genre FROM books ORDER BY genre')->fetchAll(PDO::FETCH_COLUMN);

$borrowed = $pdo->prepare("SELECT book_id FROM borrowings WHERE user_id=? AND status IN ('borrowed','overdue')");
$borrowed->execute([$uid]);
$borrowedIds = $borrowed->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-search"></i> Browse Books</h2>
</div>

<?php if ($msg):  ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($error):?><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="section-box">
  <form method="GET" class="search-bar">
    <input type="text" name="q" placeholder="Search title or author..."
           value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <select name="genre">
      <option value="">All Genres</option>
      <?php foreach ($genres as $g): ?>
        <option value="<?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?>" <?= $genre===$g ? 'selected':'' ?>><?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-primary"><i class="fa fa-search"></i> Search</button>
    <a href="/vulnerable_app/member/browse.php" class="btn-secondary">Reset</a>
  </form>

  <div class="book-grid">
    <?php foreach ($books as $b): ?>
    <div class="book-card">
      <div class="book-icon"><i class="fa fa-book-open fa-3x"></i></div>
      <div class="book-info">
        <h4><?= htmlspecialchars($b['title'],  ENT_QUOTES, 'UTF-8') ?></h4>
        <p class="book-author"><i class="fa fa-user"></i> <?= htmlspecialchars($b['author'], ENT_QUOTES, 'UTF-8') ?></p>
        <p class="book-meta">
          <span class="badge badge-genre"><?= htmlspecialchars($b['genre'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="badge <?= $b['available']>0 ? 'badge-success':'badge-danger' ?>"><?= (int)$b['available'] ?>/<?= (int)$b['quantity'] ?> available</span>
        </p>
      </div>
      <div class="book-action">
        <?php if (in_array($b['id'], $borrowedIds)): ?>
          <span class="btn-sm badge-info">Already Borrowed</span>
        <?php elseif ($b['available'] < 1): ?>
          <span class="btn-sm badge-danger">Unavailable</span>
        <?php else: ?>
          <!-- No csrf_token on borrow form -->
          <form method="POST">
            <input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>">
            <button type="submit" class="btn-sm btn-success"
                    onclick="return confirm('Borrow this book?')">
              <i class="fa fa-hand-holding-open"></i> Borrow
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($books)): ?><p class="empty-row">No books found.</p><?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
