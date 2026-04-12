<?php
// member/browse.php  –  Browse & Search Books
require_once __DIR__ . '/../includes/config.php';
startSecureSession();
requireLogin();

if (($_SESSION['role'] ?? '') !== 'member') {
    redirectTo('/admin/dashboard.php');
}

$pdo       = getDB();
$pageTitle = 'Browse Books – ' . APP_NAME;
$uid       = (int)$_SESSION['user_id'];
$msg       = '';
$error     = '';

// Handle borrow request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $bookId = (int)($_POST['book_id'] ?? 0);

        if ($bookId < 1) {
            $error = 'Invalid book selection.';
        } else {
            try {
                // Start transaction BEFORE locking/checking availability
                $pdo->beginTransaction();

                // Lock the selected book row
                $bk = $pdo->prepare(
                    'SELECT id, title, available FROM books WHERE id = ? LIMIT 1 FOR UPDATE'
                );
                $bk->execute([$bookId]);
                $book = $bk->fetch();

                if (!$book) {
                    $pdo->rollBack();
                    $error = 'Selected book does not exist.';
                } elseif ((int)$book['available'] < 1) {
                    $pdo->rollBack();
                    $error = 'Sorry, this book is not currently available.';
                } else {
                    // Check member does not already have this book
                    $dup = $pdo->prepare(
                        "SELECT id
                         FROM borrowings
                         WHERE user_id = ?
                           AND book_id = ?
                           AND status IN ('borrowed','overdue')
                         LIMIT 1"
                    );
                    $dup->execute([$uid, $bookId]);

                    if ($dup->fetch()) {
                        $pdo->rollBack();
                        $error = 'You already have this book checked out.';
                    } else {
                        $due = date('Y-m-d H:i:s', strtotime('+' . BORROW_DAYS . ' days'));

                        $insert = $pdo->prepare(
                            'INSERT INTO borrowings (user_id, book_id, due_date) VALUES (?, ?, ?)'
                        );
                        $insert->execute([$uid, $bookId, $due]);

                        $update = $pdo->prepare(
                            'UPDATE books SET available = available - 1 WHERE id = ? AND available > 0'
                        );
                        $update->execute([$bookId]);

                        // Extra safety: confirm decrement actually happened
                        if ($update->rowCount() !== 1) {
                            throw new Exception('Book availability update failed.');
                        }

                        $pdo->commit();
                        auditLog('BOOK_BORROWED_MEMBER', "BookID:$bookId Due:$due");
                        $msg = 'You have successfully borrowed "' . e($book['title']) . '". Due: ' . date('d M Y', strtotime($due));
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Member borrow error: ' . $e->getMessage());
                $error = 'Could not process request. Please try again.';
            }
        }
    }
}

// Search & filter
$search = sanitiseString($_GET['q'] ?? '', 100);
$genre  = sanitiseString($_GET['genre'] ?? '', 80);
$avOnly = (int)($_GET['available'] ?? 0);

$sql    = 'SELECT * FROM books WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)';
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}

if ($genre !== '') {
    $sql .= ' AND genre = ?';
    $params[] = $genre;
}

if ($avOnly) {
    $sql .= ' AND available > 0';
}

$sql .= ' ORDER BY title';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$genres = $pdo->query('SELECT DISTINCT genre FROM books ORDER BY genre')->fetchAll(PDO::FETCH_COLUMN);

// IDs member already has borrowed
$borrowed = $pdo->prepare(
    "SELECT book_id FROM borrowings WHERE user_id = ? AND status IN ('borrowed','overdue')"
);
$borrowed->execute([$uid]);
$borrowedIds = $borrowed->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-search"></i> Browse Books</h2>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $msg ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="section-box">
  <form method="GET" class="search-bar flex-wrap">
    <input type="text" name="q" placeholder="Search title, author, ISBN..."
           value="<?= isset($_GET['q']) ? e(sanitiseString($_GET['q'])) : '' ?>">

    <select name="genre">
      <option value="">All Genres</option>
      <?php foreach ($genres as $g): ?>
        <option value="<?= e($g) ?>" <?= $genre === $g ? 'selected' : '' ?>><?= e($g) ?></option>
      <?php endforeach; ?>
    </select>

    <label class="checkbox-label">
      <input type="checkbox" name="available" value="1" <?= $avOnly ? 'checked' : '' ?>>
      Available only
    </label>

    <button type="submit" class="btn-primary"><i class="fa fa-search"></i> Search</button>
    <a href="<?= e(url('/member/browse.php')) ?>" class="btn-secondary">Reset</a>
  </form>

  <div class="book-grid">
    <?php foreach ($books as $b): ?>
      <div class="book-card">
        <div class="book-icon"><i class="fa fa-book-open fa-3x"></i></div>

        <div class="book-info">
          <h4><?= e($b['title']) ?></h4>
          <p class="book-author"><i class="fa fa-user"></i> <?= e($b['author']) ?></p>
          <p class="book-meta">
            <span class="badge badge-genre"><?= e($b['genre']) ?></span>
            <span class="badge <?= $b['available'] > 0 ? 'badge-success' : 'badge-danger' ?>">
              <?= (int)$b['available'] ?> / <?= (int)$b['quantity'] ?> available
            </span>
          </p>
          <p class="book-isbn"><small>ISBN: <?= e($b['isbn']) ?></small></p>
        </div>

        <div class="book-action">
          <?php if (in_array($b['id'], $borrowedIds)): ?>
            <span class="btn-sm badge-info">Already Borrowed</span>
          <?php elseif ($b['available'] < 1): ?>
            <span class="btn-sm badge-danger">Unavailable</span>
          <?php else: ?>
            <form method="POST">
              <?= csrfField() ?>
              <input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>">
              <button type="submit" class="btn-sm btn-success"
                      onclick="return confirm('Borrow \'<?= e(addslashes($b['title'])) ?>\'?')">
                <i class="fa fa-hand-holding-open"></i> Borrow
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($books)): ?>
      <p class="empty-row">No books match your search.</p>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>