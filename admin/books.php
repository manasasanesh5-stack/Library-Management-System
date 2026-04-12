<?php
// admin/books.php  –  Book CRUD (Admin Only)
require_once __DIR__ . '/../includes/config.php';
startSecureSession();
requireAdmin();

$pdo       = getDB();
$action    = sanitiseString($_GET['action'] ?? 'list', 10);
$msg       = '';
$error     = '';
$pageTitle = 'Manage Books – ' . APP_NAME;

$genres = ['Fiction', 'Non-Fiction', 'Technology', 'Science', 'History', 'Biography', 'Self-Help', 'Other'];

// Default form state
$formData = [
    'title'    => '',
    'author'   => '',
    'isbn'     => '',
    'genre'    => '',
    'quantity' => 1,
];

// ---- Handle POST actions ------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $postAction = sanitiseString($_POST['post_action'] ?? '', 20);

        if ($postAction === 'add' || $postAction === 'edit') {
            $title    = sanitiseString($_POST['title'] ?? '', 200);
            $author   = sanitiseString($_POST['author'] ?? '', 150);
            $isbn     = sanitiseString($_POST['isbn'] ?? '', 20);
            $genre    = sanitiseString($_POST['genre'] ?? '', 80);
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));

            $formData = [
                'title'    => $title,
                'author'   => $author,
                'isbn'     => $isbn,
                'genre'    => $genre,
                'quantity' => $quantity,
            ];

            if ($title === '' || $author === '' || $isbn === '' || $genre === '') {
                $error = 'All fields are required.';
                $action = $postAction;
            } elseif (!in_array($genre, $genres, true)) {
                $error = 'Please select a valid genre.';
                $action = $postAction;
            } elseif (!preg_match('/^[0-9Xx\-]{10,20}$/', $isbn)) {
                $error = 'Please enter a valid ISBN.';
                $action = $postAction;
            } else {
                try {
                    if ($postAction === 'add') {
                        $stmt = $pdo->prepare(
                            'INSERT INTO books (title, author, isbn, genre, quantity, available)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        $stmt->execute([$title, $author, $isbn, $genre, $quantity, $quantity]);

                        auditLog('BOOK_ADDED', "Title: $title ISBN: $isbn");
                        $msg = 'Book added successfully.';
                        $action = 'list';
                        $formData = [
                            'title'    => '',
                            'author'   => '',
                            'isbn'     => '',
                            'genre'    => '',
                            'quantity' => 1,
                        ];
                    } else {
                        $id = (int)($_POST['book_id'] ?? 0);

                        if ($id < 1) {
                            $error = 'Invalid book selected.';
                            $action = 'list';
                        } else {
                            $old = $pdo->prepare('SELECT id, quantity, available FROM books WHERE id = ? LIMIT 1');
                            $old->execute([$id]);
                            $oldRow = $old->fetch();

                            if (!$oldRow) {
                                $error = 'Book not found.';
                                $action = 'list';
                            } else {
                                $checkedOut = (int)$oldRow['quantity'] - (int)$oldRow['available'];

                                if ($quantity < $checkedOut) {
                                    $error = 'Quantity cannot be less than the number of copies currently borrowed or overdue.';
                                    $action = 'edit';
                                    $_GET['id'] = $id;
                                } else {
                                    $newAvail = $quantity - $checkedOut;

                                    $stmt = $pdo->prepare(
                                        'UPDATE books
                                         SET title = ?, author = ?, isbn = ?, genre = ?, quantity = ?, available = ?
                                         WHERE id = ?'
                                    );
                                    $stmt->execute([$title, $author, $isbn, $genre, $quantity, $newAvail, $id]);

                                    auditLog('BOOK_UPDATED', "ID: $id Title: $title");
                                    $msg = 'Book updated successfully.';
                                    $action = 'list';
                                }
                            }
                        }
                    }
                } catch (PDOException $e) {
                    if ((string)$e->getCode() === '23000') {
                        $error = 'A book with that ISBN already exists.';
                    } else {
                        error_log('Books module error: ' . $e->getMessage());
                        $error = 'Database error. Please try again.';
                    }

                    $action = $postAction;
                    if ($postAction === 'edit') {
                        $_GET['id'] = (int)($_POST['book_id'] ?? 0);
                    }
                }
            }
        } elseif ($postAction === 'delete') {
            $id = (int)($_POST['book_id'] ?? 0);

            if ($id < 1) {
                $error = 'Invalid book selected.';
            } else {
                $chk = $pdo->prepare(
                    "SELECT COUNT(*) FROM borrowings
                     WHERE book_id = ?
                     AND status IN ('borrowed', 'overdue')"
                );
                $chk->execute([$id]);

                if ((int)$chk->fetchColumn() > 0) {
                    $error = 'Cannot delete a book that is currently borrowed or overdue.';
                } else {
                    $del = $pdo->prepare('DELETE FROM books WHERE id = ?');
                    $del->execute([$id]);

                    if ($del->rowCount() > 0) {
                        auditLog('BOOK_DELETED', "ID: $id");
                        $msg = 'Book deleted.';
                    } else {
                        $error = 'Book not found or already deleted.';
                    }
                }
            }

            $action = 'list';
        }
    }
}

// ---- Fetch data for views -----------------------------------
$books = [];
$book  = null;

if ($action === 'list' || $action === '') {
    $search = sanitiseString($_GET['q'] ?? '', 100);

    if ($search !== '') {
        $stmt = $pdo->prepare(
            'SELECT * FROM books
             WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ?
             ORDER BY title'
        );
        $like = "%$search%";
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $pdo->query('SELECT * FROM books ORDER BY title');
    }

    $books = $stmt->fetchAll();
}

if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM books WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $book = $stmt->fetch();

        if ($book) {
            // Keep posted values if validation failed, otherwise load DB values
            if (
                $formData['title'] === '' &&
                $formData['author'] === '' &&
                $formData['isbn'] === '' &&
                $formData['genre'] === '' &&
                $formData['quantity'] === 1
            ) {
                $formData = [
                    'title'    => $book['title'],
                    'author'   => $book['author'],
                    'isbn'     => $book['isbn'],
                    'genre'    => $book['genre'],
                    'quantity' => (int)$book['quantity'],
                ];
            }
        } else {
            $error = 'Book not found.';
            $action = 'list';
        }
    } else {
        $error = 'Invalid book selected.';
        $action = 'list';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-books"></i> Manage Books</h2>
  <?php if ($action === 'list'): ?>
    <a href="?action=add" class="btn-primary"><i class="fa fa-plus"></i> Add Book</a>
  <?php endif; ?>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= e($msg) ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="section-box">
  <h3><?= $action === 'add' ? 'Add New Book' : 'Edit Book' ?></h3>

  <form method="POST" action="<?= e(url('/admin/books.php')) ?>" id="bookForm">
    <?= csrfField() ?>
    <input type="hidden" name="post_action" value="<?= e($action) ?>">

    <?php if ($action === 'edit' && $book): ?>
      <input type="hidden" name="book_id" value="<?= (int)$book['id'] ?>">
    <?php endif; ?>

    <div class="form-grid">
      <div class="form-group">
        <label>Title *</label>
        <input
          type="text"
          name="title"
          maxlength="200"
          required
          value="<?= e($formData['title']) ?>"
          placeholder="Book title"
        >
      </div>

      <div class="form-group">
        <label>Author *</label>
        <input
          type="text"
          name="author"
          maxlength="150"
          required
          value="<?= e($formData['author']) ?>"
          placeholder="Author name"
        >
      </div>

      <div class="form-group">
        <label>ISBN *</label>
        <input
          type="text"
          name="isbn"
          maxlength="20"
          required
          value="<?= e($formData['isbn']) ?>"
          placeholder="ISBN-13"
        >
      </div>

      <div class="form-group">
        <label>Genre *</label>
        <select name="genre" required>
          <option value="">-- Select Genre --</option>
          <?php foreach ($genres as $g): ?>
            <option value="<?= e($g) ?>" <?= $formData['genre'] === $g ? 'selected' : '' ?>>
              <?= e($g) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Quantity *</label>
        <input
          type="number"
          name="quantity"
          min="1"
          max="100"
          required
          value="<?= (int)$formData['quantity'] ?>"
        >
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-primary">
        <i class="fa fa-save"></i> <?= $action === 'add' ? 'Add Book' : 'Update Book' ?>
      </button>
      <a href="<?= e(url('/admin/books.php')) ?>" class="btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<div class="section-box">
  <form method="GET" action="<?= e(url('/admin/books.php')) ?>" class="search-bar">
    <input type="hidden" name="action" value="list">
    <input
      type="text"
      name="q"
      placeholder="Search by title, author or ISBN..."
      value="<?= isset($_GET['q']) ? e(sanitiseString($_GET['q'])) : '' ?>"
    >
    <button type="submit" class="btn-primary"><i class="fa fa-search"></i> Search</button>
    <?php if (!empty($_GET['q'])): ?>
      <a href="<?= e(url('/admin/books.php')) ?>" class="btn-secondary">Clear</a>
    <?php endif; ?>
  </form>

  <table class="data-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Title</th>
        <th>Author</th>
        <th>ISBN</th>
        <th>Genre</th>
        <th>Qty</th>
        <th>Available</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($books as $b): ?>
      <tr>
        <td><?= (int)$b['id'] ?></td>
        <td><?= e($b['title']) ?></td>
        <td><?= e($b['author']) ?></td>
        <td><?= e($b['isbn']) ?></td>
        <td><?= e($b['genre']) ?></td>
        <td><?= (int)$b['quantity'] ?></td>
        <td>
          <span class="badge <?= (int)$b['available'] > 0 ? 'badge-success' : 'badge-danger' ?>">
            <?= (int)$b['available'] ?>
          </span>
        </td>
        <td class="actions">
          <a href="?action=edit&id=<?= (int)$b['id'] ?>" class="btn-sm btn-edit">
            <i class="fa fa-edit"></i>
          </a>

          <form
            method="POST"
            action="<?= e(url('/admin/books.php')) ?>"
            style="display:inline"
            onsubmit="return confirm('Delete this book permanently?')"
          >
            <?= csrfField() ?>
            <input type="hidden" name="post_action" value="delete">
            <input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>">
            <button type="submit" class="btn-sm btn-delete">
              <i class="fa fa-trash"></i>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>

      <?php if (empty($books)): ?>
        <tr><td colspan="8" class="empty-row">No books found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>