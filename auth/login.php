<?php
// auth/login.php  –  Secure Login with rate-limiting & CSRF
require_once __DIR__ . '/../includes/config.php';
startSecureSession();

// Compute project base path dynamically.
// Examples:
//   /auth/login.php                 -> ''
//   /library_system/auth/login.php  -> '/library_system'
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}

$loginPath    = $basePath . '/auth/login.php';
$registerPath = $basePath . '/auth/register.php';
$cssPath      = $basePath . '/public/css/style.css';
$jsPath       = $basePath . '/public/js/main.js';

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    $role = ($_SESSION['role'] === 'admin') ? 'admin' : 'member';
    header('Location: ' . $basePath . '/' . $role . '/dashboard.php');
    exit;
}

$error     = '';
$pageTitle = 'Login – ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF Verification
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
        auditLog('CSRF_FAILURE', 'Login CSRF mismatch');
    } else {

        $email = sanitiseString($_POST['email'] ?? '', 150);
        $pw    = $_POST['password'] ?? '';
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // 2. Input Validation
        if (!validateEmail($email) || empty($pw)) {
            $error = 'Please enter a valid email and password.';
        }
        // 3. Rate-limiting / Brute-force protection
        elseif (isLockedOut($email, $ip)) {
            $error = 'Too many failed attempts. Please wait 15 minutes before trying again.';
            auditLog('ACCOUNT_LOCKED', "Email: $email", null);
        } else {
            try {
                $pdo = getDB();

                // 4. Parameterised query – prevents SQL injection
                $stmt = $pdo->prepare(
                    'SELECT id, name, password, role, is_active FROM users WHERE email = ? LIMIT 1'
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // 5. Constant-time password verification (bcrypt)
                if ($user && password_verify($pw, $user['password'])) {
                    if (!$user['is_active']) {
                        $error = 'Your account has been deactivated. Contact admin.';
                    } else {
                        // Success: record attempt, regenerate session, set vars
                        recordLoginAttempt($email, $ip, true);
                        session_regenerate_id(true); // Session fixation prevention
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['name']    = $user['name'];
                        $_SESSION['role']    = $user['role'];
                        $_SESSION['email']   = $email;

                        auditLog('LOGIN_SUCCESS', null, (int)$user['id']);
                        header('Location: ' . $basePath . '/' . $user['role'] . '/dashboard.php');
                        exit;
                    }
                } else {
                    // Generic error – do not reveal whether email exists
                    recordLoginAttempt($email, $ip, false);
                    auditLog('LOGIN_FAILURE', "Email: $email");
                    $error = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                error_log('Login error: ' . $e->getMessage());
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
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="X-Frame-Options" content="DENY">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= e($cssPath) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-body">

<div class="auth-container">
  <div class="auth-card">
    <div class="auth-logo">
      <i class="fa fa-book-open fa-3x"></i>
      <h1><?= e(APP_NAME) ?></h1>
      <p>Secure Library Management System</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="fa fa-exclamation-triangle"></i> <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($loginPath) ?>" novalidate id="loginForm">
      <?= csrfField() ?>

      <div class="form-group">
        <label for="email"><i class="fa fa-envelope"></i> Email Address</label>
        <input type="email" id="email" name="email"
               value="<?= isset($_POST['email']) ? e(sanitiseString($_POST['email'])) : '' ?>"
               placeholder="Enter your email" required maxlength="150" autocomplete="email">
      </div>

      <div class="form-group">
        <label for="password"><i class="fa fa-lock"></i> Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password"
                 placeholder="Enter your password" required autocomplete="current-password">
          <button type="button" class="toggle-pw" onclick="togglePassword('password')">
            <i class="fa fa-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-primary btn-block">
        <i class="fa fa-sign-in-alt"></i> Log In
      </button>
    </form>

    <div class="auth-footer">
      <p>Don't have an account? <a href="<?= e($registerPath) ?>">Register here</a></p>
    </div>
  </div>
</div>

<script src="<?= e($jsPath) ?>"></script>
</body>
</html>