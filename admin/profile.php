<?php
// admin/profile.php  –  Change own password (Admin)
require_once __DIR__ . '/../includes/config.php';
startSecureSession();
requireAdmin();

$pdo       = getDB();
$uid       = (int)$_SESSION['user_id'];
$msg       = '';
$error     = '';
$pageTitle = 'My Profile – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $currentPw  = $_POST['current_password']  ?? '';
        $newPw      = $_POST['new_password']       ?? '';
        $confirmPw  = $_POST['confirm_password']   ?? '';

        // Fetch current hash
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!password_verify($currentPw, $user['password'])) {
            $error = 'Current password is incorrect.';
            auditLog('PASSWORD_CHANGE_FAIL', 'Wrong current password');
        } elseif (!validatePassword($newPw)) {
            $error = 'New password must be at least 8 characters and include uppercase, lowercase, a digit, and a special character.';
        } elseif ($newPw !== $confirmPw) {
            $error = 'New passwords do not match.';
        } elseif ($newPw === $currentPw) {
            $error = 'New password must be different from the current password.';
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash, $uid]);
            auditLog('PASSWORD_CHANGED', 'Admin changed own password');
            $msg = 'Password updated successfully.';
        }
    }
}

// Fetch admin info
$stmt = $pdo->prepare('SELECT name, email, created_at FROM users WHERE id=? LIMIT 1');
$stmt->execute([$uid]);
$profile = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-user-cog"></i> My Profile</h2>
</div>

<?php if ($msg):  ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= e($msg) ?></div><?php endif; ?>
<?php if ($error):?><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= e($error) ?></div><?php endif; ?>

<div class="section-box" style="max-width:520px">
  <h3><i class="fa fa-id-card"></i> Account Information</h3>
  <table class="data-table" style="margin-bottom:1.5rem">
    <tr><th style="width:130px">Name</th><td><?= e($profile['name']) ?></td></tr>
    <tr><th>Email</th><td><?= e($profile['email']) ?></td></tr>
    <tr><th>Role</th><td><span class="badge badge-warning">Administrator</span></td></tr>
    <tr><th>Member Since</th><td><?= e(date('d M Y', strtotime($profile['created_at']))) ?></td></tr>
  </table>

  <h3><i class="fa fa-lock"></i> Change Password</h3>
  <form method="POST" action="<?= e(url('/admin/profile.php')) ?>" id="profileForm">
    <?= csrfField() ?>

    <div class="form-group">
      <label for="current_password">Current Password</label>
      <div class="password-wrapper">
        <input type="password" id="current_password" name="current_password"
               required placeholder="Enter current password" autocomplete="current-password">
        <button type="button" class="toggle-pw" onclick="togglePassword('current_password')">
          <i class="fa fa-eye"></i>
        </button>
      </div>
    </div>

    <div class="form-group">
      <label for="new_password">New Password</label>
      <div class="password-wrapper">
        <input type="password" id="new_password" name="new_password"
               required placeholder="New password" autocomplete="new-password">
        <button type="button" class="toggle-pw" onclick="togglePassword('new_password')">
          <i class="fa fa-eye"></i>
        </button>
      </div>
      <small class="hint">Min 8 chars &bull; Uppercase &bull; Lowercase &bull; Number &bull; Special char</small>
    </div>

    <div class="form-group">
      <label for="confirm_password">Confirm New Password</label>
      <div class="password-wrapper">
        <input type="password" id="confirm_password" name="confirm_password"
               required placeholder="Repeat new password" autocomplete="new-password">
        <button type="button" class="toggle-pw" onclick="togglePassword('confirm_password')">
          <i class="fa fa-eye"></i>
        </button>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-primary">
        <i class="fa fa-save"></i> Update Password
      </button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>