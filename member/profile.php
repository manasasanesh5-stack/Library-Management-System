<?php
// member/profile.php  –  Member profile & password change
require_once __DIR__ . '/../includes/config.php';
startSecureSession();
requireLogin();

if (($_SESSION['role'] ?? '') !== 'member') {
    redirectTo('/admin/profile.php');
}

$pdo       = getDB();
$uid       = (int)$_SESSION['user_id'];
$msg       = '';
$error     = '';
$pageTitle = 'My Profile – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $postAction = sanitiseString($_POST['post_action'] ?? '', 20);

        if ($postAction === 'update_name') {
            $name = sanitiseString($_POST['name'] ?? '', 100);
            if (strlen($name) < 2) {
                $error = 'Name must be at least 2 characters.';
            } else {
                $pdo->prepare('UPDATE users SET name=? WHERE id=?')->execute([$name, $uid]);
                $_SESSION['name'] = $name;
                auditLog('PROFILE_UPDATED', 'Name updated');
                $msg = 'Name updated successfully.';
            }
        } elseif ($postAction === 'change_password') {
            $currentPw = $_POST['current_password'] ?? '';
            $newPw     = $_POST['new_password'] ?? '';
            $confirmPw = $_POST['confirm_password'] ?? '';

            $stmt = $pdo->prepare('SELECT password FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$uid]);
            $user = $stmt->fetch();

            if (!password_verify($currentPw, $user['password'])) {
                $error = 'Current password is incorrect.';
                auditLog('PASSWORD_CHANGE_FAIL', 'Wrong current password');
            } elseif (!validatePassword($newPw)) {
                $error = 'New password must be at least 8 characters with uppercase, lowercase, digit and special character.';
            } elseif ($newPw !== $confirmPw) {
                $error = 'New passwords do not match.';
            } elseif ($newPw === $currentPw) {
                $error = 'New password must differ from current password.';
            } else {
                $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash, $uid]);
                auditLog('PASSWORD_CHANGED', 'Member changed own password');
                $msg = 'Password changed successfully.';
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT name, email, created_at FROM users WHERE id=? LIMIT 1');
$stmt->execute([$uid]);
$profile = $stmt->fetch();

// Stats
$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM borrowings WHERE user_id=?');
$totalStmt->execute([$uid]);
$totalBorrowed = (int)$totalStmt->fetchColumn();

$activeStmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id=? AND status IN ('borrowed','overdue')");
$activeStmt->execute([$uid]);
$activeBorrowed = (int)$activeStmt->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-user-circle"></i> My Profile</h2>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= e($error) ?></div><?php endif; ?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px,1fr)); gap:1.5rem;">

  <!-- Account Info & Update Name -->
  <div class="section-box">
    <h3><i class="fa fa-id-card"></i> Account Details</h3>
    <table class="data-table" style="margin-bottom:1.5rem">
      <tr><th style="width:120px">Email</th><td><?= e($profile['email']) ?></td></tr>
      <tr><th>Role</th><td><span class="badge badge-info">Member</span></td></tr>
      <tr><th>Joined</th><td><?= e(date('d M Y', strtotime($profile['created_at']))) ?></td></tr>
      <tr><th>Total Borrowed</th><td><?= $totalBorrowed ?></td></tr>
      <tr><th>Currently Out</th><td><?= $activeBorrowed ?></td></tr>
    </table>

    <h3><i class="fa fa-edit"></i> Update Name</h3>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="post_action" value="update_name">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" maxlength="100" required
               value="<?= e($profile['name']) ?>">
      </div>
      <button type="submit" class="btn-primary"><i class="fa fa-save"></i> Save Name</button>
    </form>
  </div>

  <!-- Change Password -->
  <div class="section-box">
    <h3><i class="fa fa-lock"></i> Change Password</h3>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="post_action" value="change_password">

      <div class="form-group">
        <label>Current Password</label>
        <div class="password-wrapper">
          <input type="password" name="current_password" required
                 placeholder="Current password" autocomplete="current-password">
          <button type="button" class="toggle-pw" onclick="togglePassword(this.previousElementSibling.id || (this.previousElementSibling.id='cpw'))">
            <i class="fa fa-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label>New Password</label>
        <div class="password-wrapper">
          <input type="password" id="new_password" name="new_password"
                 required placeholder="New password" autocomplete="new-password">
          <button type="button" class="toggle-pw" onclick="togglePassword('new_password')">
            <i class="fa fa-eye"></i>
          </button>
        </div>
        <small class="hint">Min 8 chars &bull; Uppercase &bull; Lowercase &bull; Number &bull; Special</small>
      </div>

      <div class="form-group">
        <label>Confirm New Password</label>
        <div class="password-wrapper">
          <input type="password" id="confirm_password" name="confirm_password"
                 required placeholder="Repeat new password" autocomplete="new-password">
          <button type="button" class="toggle-pw" onclick="togglePassword('confirm_password')">
            <i class="fa fa-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-primary"><i class="fa fa-key"></i> Change Password</button>
    </form>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>