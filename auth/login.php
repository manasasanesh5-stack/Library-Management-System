<?php
// auth/login.php
// VULNERABILITY 1: No brute-force / rate-limiting protection
//     An attacker can submit unlimited login attempts with no lockout.
// VULNERABILITY 2: No CSRF token on the login form.
// VULNERABILITY 3: No session_regenerate_id() after login (session fixation).

require_once __DIR__ . '/../includes/config.php';
startSession();

if (!empty($_SESSION['user_id'])) {
    header('Location: /vulnerable_app/' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']    ?? '');
    $pw    = $_POST['password']      ?? '';

    // No CSRF token check
    // No attempt counter or lockout check
    // No input length validation

    if (empty($email) || empty($pw)) {
        $error = 'Please enter your email and password.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id, name, password, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pw, $user['password'])) {
            // No session_regenerate_id() — vulnerable to session fixation
            // No login attempt recorded anywhere
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            header('Location: /vulnerable_app/' . $user['role'] . '/dashboard.php');
            exit;
        } else {
            // No failed attempt is logged or counted
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/vulnerable_app/public/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-body">

<div class="auth-container">
  <div class="auth-card">
    <div class="auth-logo">
      <i class="fa fa-book-open fa-3x"></i>
      <h1><?= APP_NAME ?></h1>
      <p>Library Management System</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="fa fa-exclamation-triangle"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- No csrf_token hidden field anywhere in this form -->
    <form method="POST" action="">
      <div class="form-group">
        <label for="email"><i class="fa fa-envelope"></i> Email Address</label>
        <input type="email" id="email" name="email"
               placeholder="Enter your email"
               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-group">
        <label for="password"><i class="fa fa-lock"></i> Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password"
                 placeholder="Enter your password">
          <button type="button" class="toggle-pw" onclick="togglePassword('password')">
            <i class="fa fa-eye"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-primary btn-block">
        <i class="fa fa-sign-in-alt"></i> Log In
      </button>
    </form>

    <div class="auth-footer">
      <p>Don't have an account? <a href="/vulnerable_app/auth/register.php">Register here</a></p>
    </div>
  </div>
</div>

<script src="/vulnerable_app/public/js/main.js"></script>
</body>
</html>
