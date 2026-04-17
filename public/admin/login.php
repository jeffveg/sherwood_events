<?php
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/auth.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $password = (string)($_POST['password'] ?? '');
    if (auth_attempt($password)) {
        $dest = $_SESSION['after_login'] ?? '/admin/';
        unset($_SESSION['after_login']);
        header('Location: ' . $dest);
        exit;
    }
    $error = 'Incorrect password.';
}

if (auth_check()) {
    header('Location: /admin/');
    exit;
}

$pageTitle = 'Admin Login | Sherwood Events';
include __DIR__ . '/../_partials/head.php';
include __DIR__ . '/../_partials/nav.php';
?>

<main class="content-body admin-login" id="main-content">
  <h1>Admin Login</h1>
  <?php if ($error): ?>
    <p class="form-error"><?= e($error) ?></p>
  <?php endif; ?>
  <form method="POST" class="admin-form" style="max-width: 400px;">
    <?= csrf_field() ?>
    <label>Password
      <input type="password" name="password" required autofocus autocomplete="current-password">
    </label>
    <button type="submit" class="btn btn-gold">Log In</button>
  </form>
</main>

<?php include __DIR__ . '/../_partials/footer.php'; ?>
