<?php
// admin/members.php  –  Member Management (Admin Only)
require_once __DIR__ . '/../includes/config.php';
startSecureSession();
requireAdmin();

$pdo       = getDB();
$msg       = '';
$error     = '';
$pageTitle = 'Manage Members – ' . APP_NAME;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $postAction = sanitiseString($_POST['post_action'] ?? '', 20);
        $uid        = (int)($_POST['user_id'] ?? 0);

        if ($uid < 1) {
            $error = 'Invalid member selected.';
        } elseif ($postAction === 'toggle_active') {
            // Prevent admin from deactivating themselves
            if ($uid === (int)$_SESSION['user_id']) {
                $error = 'You cannot deactivate your own account.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET is_active = 1 - is_active
                     WHERE id = ? AND role = "member"'
                );
                $stmt->execute([$uid]);

                if ($stmt->rowCount() > 0) {
                    auditLog('MEMBER_STATUS_TOGGLE', "UserID: $uid");
                    $msg = 'Member status updated.';
                } else {
                    $error = 'Member not found.';
                }
            }
        } elseif ($postAction === 'delete') {
            // Check no active or overdue borrowings
            $chk = $pdo->prepare(
                "SELECT COUNT(*) FROM borrowings
                 WHERE user_id = ?
                 AND status IN ('borrowed', 'overdue')"
            );
            $chk->execute([$uid]);

            if ((int)$chk->fetchColumn() > 0) {
                $error = 'Cannot delete member with active or overdue borrowings.';
            } else {
                $del = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "member"');
                $del->execute([$uid]);

                if ($del->rowCount() > 0) {
                    auditLog('MEMBER_DELETED', "UserID: $uid");
                    $msg = 'Member deleted.';
                } else {
                    $error = 'Member not found or already deleted.';
                }
            }
        } else {
            $error = 'Invalid action.';
        }
    }
}

$search = sanitiseString($_GET['q'] ?? '', 100);

if ($search !== '') {
    $stmt = $pdo->prepare(
        "SELECT u.*, COUNT(b.id) AS total_borrows
         FROM users u
         LEFT JOIN borrowings b
           ON b.user_id = u.id
          AND b.status IN ('borrowed', 'overdue')
         WHERE u.role = 'member'
           AND (u.name LIKE ? OR u.email LIKE ?)
         GROUP BY u.id
         ORDER BY u.name"
    );
    $like = "%$search%";
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query(
        "SELECT u.*, COUNT(b.id) AS total_borrows
         FROM users u
         LEFT JOIN borrowings b
           ON b.user_id = u.id
          AND b.status IN ('borrowed', 'overdue')
         WHERE u.role = 'member'
         GROUP BY u.id
         ORDER BY u.name"
    );
}

$members = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-users"></i> Manage Members</h2>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= e($msg) ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="section-box">
  <form method="GET" action="<?= e(url('/admin/members.php')) ?>" class="search-bar">
    <input
      type="text"
      name="q"
      placeholder="Search by name or email..."
      value="<?= isset($_GET['q']) ? e(sanitiseString($_GET['q'])) : '' ?>"
    >
    <button type="submit" class="btn-primary"><i class="fa fa-search"></i> Search</button>
    <?php if (!empty($_GET['q'])): ?>
      <a href="<?= e(url('/admin/members.php')) ?>" class="btn-secondary">Clear</a>
    <?php endif; ?>
  </form>

  <table class="data-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Name</th>
        <th>Email</th>
        <th>Active Borrows</th>
        <th>Status</th>
        <th>Joined</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($members as $m): ?>
      <tr>
        <td><?= (int)$m['id'] ?></td>
        <td><?= e($m['name']) ?></td>
        <td><?= e($m['email']) ?></td>
        <td><?= (int)$m['total_borrows'] ?></td>
        <td>
          <span class="badge <?= (int)$m['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
            <?= (int)$m['is_active'] === 1 ? 'Active' : 'Inactive' ?>
          </span>
        </td>
        <td><?= e(date('d M Y', strtotime($m['created_at']))) ?></td>
        <td class="actions">
          <!-- Toggle Status -->
          <form method="POST" action="<?= e(url('/admin/members.php')) ?>" style="display:inline">
            <?= csrfField() ?>
            <input type="hidden" name="post_action" value="toggle_active">
            <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <button
              type="submit"
              class="btn-sm <?= (int)$m['is_active'] === 1 ? 'btn-warning' : 'btn-success' ?>"
              title="<?= (int)$m['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>"
            >
              <i class="fa <?= (int)$m['is_active'] === 1 ? 'fa-ban' : 'fa-check' ?>"></i>
            </button>
          </form>

          <!-- Delete -->
          <form
            method="POST"
            action="<?= e(url('/admin/members.php')) ?>"
            style="display:inline"
            onsubmit="return confirm('Delete member <?= e(addslashes($m['name'])) ?>?')"
          >
            <?= csrfField() ?>
            <input type="hidden" name="post_action" value="delete">
            <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="btn-sm btn-delete" title="Delete">
              <i class="fa fa-trash"></i>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>

      <?php if (empty($members)): ?>
        <tr><td colspan="7" class="empty-row">No members found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>