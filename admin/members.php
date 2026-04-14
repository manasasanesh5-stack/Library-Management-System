<?php
// admin/members.php
// No CSRF tokens on status-toggle and delete forms
// No role check

require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pdo       = getDB();
$msg       = '';
$error     = '';
$pageTitle = 'Members – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No CSRF token check
    $postAction = trim($_POST['post_action'] ?? '');
    $uid        = (int)($_POST['user_id'] ?? 0);

    if ($postAction === 'toggle_active' && $uid > 0) {
        $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=?')->execute([$uid]);
        $msg = 'Member status updated.';
    } elseif ($postAction === 'delete' && $uid > 0) {
        $pdo->prepare('DELETE FROM users WHERE id=? AND role="member"')->execute([$uid]);
        $msg = 'Member deleted.';
    }
}

$stmt    = $pdo->query("SELECT * FROM users WHERE role='member' ORDER BY name");
$members = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-users"></i> Manage Members</h2>
</div>

<?php if ($msg):  ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error):?><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="section-box">
  <table class="data-table">
    <thead>
      <tr><th>#</th><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($members as $m): ?>
      <tr>
        <td><?= (int)$m['id'] ?></td>
        <td><?= htmlspecialchars($m['name'],  ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($m['email'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="badge <?= $m['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $m['is_active'] ? 'Active' : 'Inactive' ?></span></td>
        <td><?= htmlspecialchars(date('d M Y', strtotime($m['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
        <td class="actions">
          <!-- No csrf_token -->
          <form method="POST" style="display:inline">
            <input type="hidden" name="post_action" value="toggle_active">
            <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="btn-sm <?= $m['is_active'] ? 'btn-warning' : 'btn-success' ?>">
              <i class="fa <?= $m['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
            </button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete member?')">
            <input type="hidden" name="post_action" value="delete">
            <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="btn-sm btn-delete"><i class="fa fa-trash"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($members)): ?><tr><td colspan="6" class="empty-row">No members found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
