<?php
// auth/register.php  –  Secure Member Registration
require_once __DIR__ . '/../includes/config.php';
startSecureSession();

// Compute project base path dynamically.
// Examples:
//   /auth/register.php                 -> ''
//   /library_system/auth/register.php  -> '/library_system'
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}

$registerPath = $basePath . '/auth/register.php';
$loginPath    = $basePath . '/auth/login.php';
$cssPath      = $basePath . '/public/css/style.css';
$jsPath       = $basePath . '/public/js/main.js';

if (!empty($_SESSION['user_id'])) {
    $role = ($_SESSION['role'] === 'admin') ? 'admin' : 'member';
    header('Location: ' . $basePath . '/' . $role . '/dashboard.php');
    exit;
}

$error     = '';
$success   = '';
$pageTitle = 'Register – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {

        $name  = sanitiseString($_POST['name'] ?? '', 100);
        $email = sanitiseString($_POST['email'] ?? '', 150);
        $pw    = $_POST['password'] ?? '';
        $pw2   = $_POST['password_confirm'] ?? '';

        // Validate all fields
        if (empty($name) || strlen($name) < 2) {
            $error = 'Full name must be at least 2 characters.';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (!validatePassword($pw)) {
            $error = 'Password must be at least 8 characters and include uppercase, lowercase, a digit, and a special character.';
        } elseif ($pw !== $pw2) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo = getDB();

                // Check for duplicate email
                $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $chk->execute([$email]);

                if ($chk->fetch()) {
                    $error = 'An account with that email already exists.';
                } else {
                    // Hash password with bcrypt (cost 12)
                    $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
                    $ins  = $pdo->prepare(
                        'INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)'
                    );
                    $ins->execute([$name, $email, $hash, 'member']);

                    $newId = (int)$pdo->lastInsertId();
                    auditLog('REGISTER', "New member: $email", $newId);

                    $success = 'Account created! You can now <a href="' . e($loginPath) . '">log in</a>.';
                }
            } catch (PDOException $e) {
                error_log('Register error: ' . $e->getMessage());
                $error = 'A server error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-Frame-Options" content="DENY">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= e($cssPath) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-body">

<div class="auth-container">
  <div class="auth-card">
    <div class="auth-logo">
      <i class="fa fa-user-plus fa-3x"></i>
      <h1>Create Account</h1>
      <p><?= e(APP_NAME) ?></p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="<?= e($registerPath) ?>" novalidate id="registerForm">
      <?= csrfField() ?>

      <div class="form-group">
        <label for="name"><i class="fa fa-user"></i> Full Name</label>
        <input type="text" id="name" name="name" maxlength="100" required
               value="<?= isset($_POST['name']) ? e(sanitiseString($_POST['name'])) : '' ?>"
               placeholder="Your full name">
      </div>

      <div class="form-group">
        <label for="email"><i class="fa fa-envelope"></i> Email Address</label>
        <input type="email" id="email" name="email" maxlength="150" required
               value="<?= isset($_POST['email']) ? e(sanitiseString($_POST['email'])) : '' ?>"
               placeholder="your@email.com">
      </div>

      <div class="form-group">
        <label for="password"><i class="fa fa-lock"></i> Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password" required placeholder="Min 8 chars">
          <button type="button" class="toggle-pw" onclick="togglePassword('password')">
            <i class="fa fa-eye"></i>
          </button>
        </div>
        <small class="hint">Min 8 chars &bull; Uppercase &bull; Lowercase &bull; Number &bull; Special char</small>
      </div>

      <div class="form-group">
        <label for="password_confirm"><i class="fa fa-lock"></i> Confirm Password</label>
        <div class="password-wrapper">
          <input type="password" id="password_confirm" name="password_confirm" required placeholder="Repeat password">
          <button type="button" class="toggle-pw" onclick="togglePassword('password_confirm')">
            <i class="fa fa-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-primary btn-block">
        <i class="fa fa-user-plus"></i> Create Account
      </button>
    </form>
    <?php endif; ?>

    <div class="auth-footer">
      <p>Already have an account? <a href="<?= e($loginPath) ?>">Log in</a></p>
    </div>
  </div>
</div>

<script src="<?= e($jsPath) ?>"></script>
</body>
</html>