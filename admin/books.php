<?php
// admin/books.php
// No CSRF tokens on Add / Edit / Delete forms
// No role check — any logged-in user can add/edit/delete books

require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pdo    = getDB();
$action = trim($_GET['action'] ?? 'list');
$msg    = '';
$error  = '';
$pageTitle = 'Books – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No CSRF token verification
    $postAction = trim($_POST['post_action'] ?? '');

    if ($postAction === 'add' || $postAction === 'edit') {
        $title    = trim($_POST['title']    ?? '');
        $author   = trim($_POST['author']   ?? '');
        $isbn     = trim($_POST['isbn']     ?? '');
        $genre    = trim($_POST['genre']    ?? '');
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if (empty($title) || empty($author) || empty($isbn) || empty($genre)) {
            $error  = 'All fields are required.';
            $action = $postAction;
        } else {
            if ($postAction === 'add') {
                $stmt = $pdo->prepare(
                    'INSERT INTO books (title,author,isbn,genre,quantity,available) VALUES (?,?,?,?,?,?)'
                );
                $stmt->execute([$title, $author, $isbn, $genre, $quantity, $quantity]);
                $msg = 'Book added.';
            } else {
                $id   = (int)($_POST['book_id'] ?? 0);
                $stmt = $pdo->prepare(
                    'UPDATE books SET title=?,author=?,isbn=?,genre=?,quantity=? WHERE id=?'
                );
                $stmt->execute([$title, $author, $isbn, $genre, $quantity, $id]);
                $msg = 'Book updated.';
            }
            $action = 'list';
        }
    } elseif ($postAction === 'delete') {
        // No CSRF check — this delete can be triggered from any page
        $id = (int)($_POST['book_id'] ?? 0);
        $pdo->prepare('DELETE FROM books WHERE id=?')->execute([$id]);
        $msg    = 'Book deleted.';
        $action = 'list';
    }
}

$books = [];
$book  = null;

if ($action === 'list' || $action === '') {
    $search = trim($_GET['q'] ?? '');
    if ($search !== '') {
        $stmt  = $pdo->prepare('SELECT * FROM books WHERE title LIKE ? OR author LIKE ? ORDER BY title');
        $like  = "%$search%";
        $stmt->execute([$like, $like]);
    } else {
        $stmt  = $pdo->query('SELECT * FROM books ORDER BY title');
    }
    $books = $stmt->fetchAll();
}

if ($action === 'edit') {
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM books WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $book = $stmt->fetch();
    if (!$book) $action = 'list';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-book"></i> Manage Books</h2>
  <?php if ($action === 'list'): ?>
    <a href="?action=add" class="btn-primary"><i class="fa fa-plus"></i> Add Book</a>
  <?php endif; ?>
</div>

<?php if ($msg):  ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error):?><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="section-box">
  <h3><?= $action === 'add' ? 'Add New Book' : 'Edit Book' ?></h3>
  <!-- No csrf_token hidden field -->
  <form method="POST" action="/vulnerable_app/admin/books.php">
    <input type="hidden" name="post_action" value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($action === 'edit'): ?>
      <input type="hidden" name="book_id" value="<?= (int)$book['id'] ?>">
    <?php endif; ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" value="<?= $book ? htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') : '' ?>" placeholder="Book title">
      </div>
      <div class="form-group">
        <label>Author</label>
        <input type="text" name="author" value="<?= $book ? htmlspecialchars($book['author'], ENT_QUOTES, 'UTF-8') : '' ?>" placeholder="Author name">
      </div>
      <div class="form-group">
        <label>ISBN</label>
        <input type="text" name="isbn" value="<?= $book ? htmlspecialchars($book['isbn'], ENT_QUOTES, 'UTF-8') : '' ?>" placeholder="ISBN-13">
      </div>
      <div class="form-group">
        <label>Genre</label>
        <select name="genre">
          <option value="">-- Select Genre --</option>
          <?php foreach (['Fiction','Non-Fiction','Technology','Science','History','Biography','Self-Help','Other'] as $g): ?>
            <option value="<?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?>"
              <?= ($book && $book['genre'] === $g) ? 'selected' : '' ?>>
              <?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Quantity</label>
        <input type="number" name="quantity" min="1" value="<?= $book ? (int)$book['quantity'] : 1 ?>">
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn-primary"><i class="fa fa-save"></i> Save</button>
      <a href="/vulnerable_app/admin/books.php" class="btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<div class="section-box">
  <form method="GET" class="search-bar">
    <input type="text" name="q" placeholder="Search by title or author..."
           value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn-primary"><i class="fa fa-search"></i> Search</button>
    <?php if (!empty($_GET['q'])): ?><a href="/vulnerable_app/admin/books.php" class="btn-secondary">Clear</a><?php endif; ?>
  </form>

  <table class="data-table">
    <thead>
      <tr><th>#</th><th>Title</th><th>Author</th><th>ISBN</th><th>Genre</th><th>Qty</th><th>Available</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($books as $b): ?>
      <tr>
        <td><?= (int)$b['id'] ?></td>
        <td><?= htmlspecialchars($b['title'],  ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($b['author'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($b['isbn'],   ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($b['genre'],  ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= (int)$b['quantity'] ?></td>
        <td><span class="badge <?= $b['available'] > 0 ? 'badge-success' : 'badge-danger' ?>"><?= (int)$b['available'] ?></span></td>
        <td class="actions">
          <a href="?action=edit&id=<?= (int)$b['id'] ?>" class="btn-sm btn-edit"><i class="fa fa-edit"></i></a>
          <!-- No csrf_token in delete form -->
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this book?')">
            <input type="hidden" name="post_action" value="delete">
            <input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>">
            <button type="submit" class="btn-sm btn-delete"><i class="fa fa-trash"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($books)): ?><tr><td colspan="8" class="empty-row">No books found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
