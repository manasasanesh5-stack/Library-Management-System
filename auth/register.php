<?php
// auth/register.php
// No CSRF token on registration form
// Minimal input validation (no password complexity rules)

require_once __DIR__ . '/../includes/config.php';
startSession();

if (!empty($_SESSION['user_id'])) {
    header('Location: /vulnerable_app/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No CSRF token check

    $name  = trim($_POST['name']             ?? '');
    $email = trim($_POST['email']            ?? '');
    $pw    = $_POST['password']              ?? '';
    $pw2   = $_POST['password_confirm']      ?? '';

    // Very weak validation — no password complexity enforced
    if (empty($name) || empty($email) || empty($pw)) {
        $error = 'All fields are required.';
    } elseif ($pw !== $pw2) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = getDB();
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare(
                'INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)'
            );
            $ins->execute([$name, $email, $hash, 'member']);
            $success = 'Account created! You can now <a href="login.php">log in</a>.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register – <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/vulnerable_app/public/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-body">

<div class="auth-container">
  <div class="auth-card">
    <div class="auth-logo">
      <i class="fa fa-user-plus fa-3x"></i>
      <h1>Create Account</h1>
      <p><?= APP_NAME ?></p>
    </div>

    <?php if ($error):   ?><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <!-- No csrf_token hidden field -->
    <form method="POST" action="">
      <div class="form-group">
        <label><i class="fa fa-user"></i> Full Name</label>
        <input type="text" name="name" placeholder="Your full name"
               value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-group">
        <label><i class="fa fa-envelope"></i> Email Address</label>
        <input type="email" name="email" placeholder="your@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-group">
        <label><i class="fa fa-lock"></i> Password</label>
        <!-- No complexity hint or enforcement -->
        <input type="password" name="password" placeholder="Choose a password">
      </div>
      <div class="form-group">
        <label><i class="fa fa-lock"></i> Confirm Password</label>
        <input type="password" name="password_confirm" placeholder="Repeat password">
      </div>
      <button type="submit" class="btn-primary btn-block">
        <i class="fa fa-user-plus"></i> Create Account
      </button>
    </form>
    <?php endif; ?>

    <div class="auth-footer">
      <p>Already have an account? <a href="login.php">Log in</a></p>
    </div>
  </div>
</div>

<script src="/vulnerable_app/public/js/main.js"></script>
</body>
</html>
