<?php
// backend/public/admin/login.php
require_once __DIR__ . '/../../src/bootstrap.php';

start_admin_session();
if (!empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = (string)($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    if (verify_login($u, $p)) {
        $stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$u]);
        $uid = (int)$stmt->fetchColumn();
        login_user($uid, $u);
        header('Location: /index.php');
        exit;
    }
    $error = 'Falsche Anmeldedaten oder Konto gesperrt.';
}
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Anmelden — TI Admin</title>
  <link rel="stylesheet" href="/admin.css">
</head>
<body class="login-page">
  <main class="login-card">
    <h1>TI Admin</h1>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post" autocomplete="off">
      <label>Benutzername
        <input type="text" name="username" required autofocus>
      </label>
      <label>Passwort
        <input type="password" name="password" required>
      </label>
      <button type="submit">Anmelden</button>
    </form>
  </main>
</body>
</html>
