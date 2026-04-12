<?php
// admin/borrowings.php  –  Borrowings Management (Admin Only)
require_once __DIR__ . '/../includes/config.php';
startSecureSession();
requireAdmin();

$pdo       = getDB();
$msg       = '';
$error     = '';
$pageTitle = 'Borrowings – ' . APP_NAME;

// Mark overdue records
$pdo->query(
    "UPDATE borrowings SET status='overdue' WHERE status='borrowed' AND due_date < NOW()"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $postAction = sanitiseString($_POST['post_action'] ?? '', 20);

        if ($postAction === 'issue') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $bookId = (int)($_POST['book_id'] ?? 0);

            if ($userId < 1 || $bookId < 1) {
                $error = 'Please select a member and a book.';
            } else {
                try {
                    // Start transaction BEFORE locking/availability checks
                    $pdo->beginTransaction();

                    // Lock the selected book row
                    $avail = $pdo->prepare(
                        'SELECT id, title, available FROM books WHERE id = ? LIMIT 1 FOR UPDATE'
                    );
                    $avail->execute([$bookId]);
                    $book = $avail->fetch();

                    if (!$book) {
                        $pdo->rollBack();
                        $error = 'Selected book does not exist.';
                    } elseif ((int)$book['available'] < 1) {
                        $pdo->rollBack();
                        $error = 'Book is not currently available.';
                    } else {
                        // Check member does not already have this book
                        $dup = $pdo->prepare(
                            "SELECT id
                             FROM borrowings
                             WHERE user_id = ?
                               AND book_id = ?
                               AND status IN ('borrowed', 'overdue')
                             LIMIT 1"
                        );
                        $dup->execute([$userId, $bookId]);

                        if ($dup->fetch()) {
                            $pdo->rollBack();
                            $error = 'This member already has this book checked out.';
                        } else {
                            $due = date('Y-m-d H:i:s', strtotime('+' . BORROW_DAYS . ' days'));

                            $insert = $pdo->prepare(
                                'INSERT INTO borrowings (user_id, book_id, due_date) VALUES (?, ?, ?)'
                            );
                            $insert->execute([$userId, $bookId, $due]);

                            $update = $pdo->prepare(
                                'UPDATE books SET available = available - 1 WHERE id = ? AND available > 0'
                            );
                            $update->execute([$bookId]);

                            // Extra safety: make sure stock was actually decremented
                            if ($update->rowCount() !== 1) {
                                throw new Exception('Book availability update failed.');
                            }

                            $pdo->commit();
                            auditLog('BOOK_ISSUED', "UserID:$userId BookID:$bookId Due:$due");
                            $msg = 'Book issued successfully. Due: ' . date('d M Y', strtotime($due));
                        }
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Issue book error: ' . $e->getMessage());
                    $error = 'Failed to issue book. Please try again.';
                }
            }
        } elseif ($postAction === 'return') {
            $borrowId = (int)($_POST['borrow_id'] ?? 0);
            $borrow   = $pdo->prepare('SELECT * FROM borrowings WHERE id=? LIMIT 1');
            $borrow->execute([$borrowId]);
            $rec = $borrow->fetch();

            if (!$rec || $rec['status'] === 'returned') {
                $error = 'Invalid borrowing record.';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare(
                        "UPDATE borrowings SET status='returned', returned_at=NOW() WHERE id=?"
                    )->execute([$borrowId]);

                    $pdo->prepare(
                        'UPDATE books SET available = available + 1 WHERE id=?'
                    )->execute([$rec['book_id']]);

                    $pdo->commit();
                    auditLog('BOOK_RETURNED', "BorrowID:$borrowId");
                    $msg = 'Book marked as returned.';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Return book error: ' . $e->getMessage());
                    $error = 'Failed to process return.';
                }
            }
        }
    }
}

// Fetch active / overdue borrowings
$filter = sanitiseString($_GET['filter'] ?? 'active', 10);
$sqlMap = [
    'active'   => "status IN ('borrowed','overdue')",
    'returned' => "status='returned'",
    'overdue'  => "status='overdue'",
];
$where = $sqlMap[$filter] ?? $sqlMap['active'];

$borrows = $pdo->query(
    "SELECT b.*, u.name AS member_name, u.email, bk.title AS book_title, bk.isbn
     FROM borrowings b
     JOIN users u ON b.user_id = u.id
     JOIN books bk ON b.book_id = bk.id
     WHERE $where
     ORDER BY b.borrowed_at DESC
     LIMIT 200"
)->fetchAll();

// For issue form
$members = $pdo->query(
    "SELECT id, name, email
     FROM users
     WHERE role='member' AND is_active=1
     ORDER BY name"
)->fetchAll();

$avBooks = $pdo->query(
    "SELECT id, title, author
     FROM books
     WHERE available > 0
     ORDER BY title"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-exchange-alt"></i> Borrowings</h2>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success">
    <i class="fa fa-check-circle"></i> <?= e($msg) ?>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger">
    <i class="fa fa-exclamation-triangle"></i> <?= e($error) ?>
  </div>
<?php endif; ?>

<!-- Issue Book Form -->
<div class="section-box">
  <h3><i class="fa fa-hand-holding-open"></i> Issue Book to Member</h3>
  <form method="POST" class="form-inline-row">
    <?= csrfField() ?>
    <input type="hidden" name="post_action" value="issue">

    <div class="form-group">
      <label>Member</label>
      <select name="user_id" required>
        <option value="">-- Select Member --</option>
        <?php foreach ($members as $m): ?>
          <option value="<?= (int)$m['id'] ?>">
            <?= e($m['name']) ?> (<?= e($m['email']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Book</label>
      <select name="book_id" required>
        <option value="">-- Select Available Book --</option>
        <?php foreach ($avBooks as $bk): ?>
          <option value="<?= (int)$bk['id'] ?>">
            <?= e($bk['title']) ?> – <?= e($bk['author']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button type="submit" class="btn-primary">
      <i class="fa fa-check"></i> Issue Book
    </button>
  </form>
</div>

<!-- Filter Tabs -->
<div class="section-box">
  <div class="tab-bar">
    <a href="?filter=active" class="tab <?= $filter === 'active' ? 'active' : '' ?>">Active</a>
    <a href="?filter=overdue" class="tab <?= $filter === 'overdue' ? 'active' : '' ?>">Overdue</a>
    <a href="?filter=returned" class="tab <?= $filter === 'returned' ? 'active' : '' ?>">Returned</a>
  </div>

  <table class="data-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Member</th>
        <th>Book</th>
        <th>Borrowed</th>
        <th>Due</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($borrows as $row): ?>
        <tr>
          <td><?= (int)$row['id'] ?></td>
          <td><?= e($row['member_name']) ?></td>
          <td><?= e($row['book_title']) ?></td>
          <td><?= e(date('d M Y', strtotime($row['borrowed_at']))) ?></td>
          <td><?= e(date('d M Y', strtotime($row['due_date']))) ?></td>
          <td>
            <span class="badge <?= $row['status'] === 'returned' ? 'badge-success' : ($row['status'] === 'overdue' ? 'badge-danger' : 'badge-info') ?>">
              <?= ucfirst(e($row['status'])) ?>
            </span>
          </td>
          <td>
            <?php if ($row['status'] !== 'returned'): ?>
              <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="post_action" value="return">
                <input type="hidden" name="borrow_id" value="<?= (int)$row['id'] ?>">
                <button type="submit" class="btn-sm btn-success">
                  <i class="fa fa-undo"></i> Return
                </button>
              </form>
            <?php else: ?>
              <span class="text-muted">–</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (empty($borrows)): ?>
        <tr>
          <td colspan="7" class="empty-row">No records found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>