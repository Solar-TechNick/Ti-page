<?php
// backend/public/admin/vouchers.php — list / create / delete voucher codes
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

$msg = $_GET['msg'] ?? null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_check_from_session($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('CSRF check failed.');
    }
    $op = $_POST['op'] ?? '';

    if ($op === 'create') {
        $code = trim((string)($_POST['code'] ?? ''));
        $expires = trim((string)($_POST['expires_at'] ?? ''));

        if ($code === '' || !preg_match('/^\S{1,50}$/u', $code)) {
            header('Location: /vouchers.php?msg=invalid_code'); exit;
        }
        $expiresAt = null;
        if ($expires !== '') {
            $ts = strtotime($expires . ' 23:59:59');
            if ($ts === false) {
                header('Location: /vouchers.php?msg=invalid_date'); exit;
            }
            $expiresAt = date('Y-m-d H:i:s', $ts);
        }
        try {
            db()->prepare("INSERT INTO vouchers (code, expires_at) VALUES (?, ?)")
                ->execute([$code, $expiresAt]);
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) { // duplicate key
                header('Location: /vouchers.php?msg=duplicate'); exit;
            }
            throw $e;
        }
        header('Location: /vouchers.php?msg=created'); exit;
    }

    if ($op === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("DELETE FROM vouchers WHERE id = ?")->execute([$id]);
        header('Location: /vouchers.php?msg=deleted'); exit;
    }

    http_response_code(400); exit('Unknown operation.');
}

$rows = db()->query("SELECT id, code, expires_at, active, created_at
                     FROM vouchers ORDER BY created_at DESC")->fetchAll();
$csrf = csrf_token_from_session();

$counts = [];
foreach (['contact_requests' => 'contact', 'angebot_requests' => 'angebot'] as $t => $key) {
    $counts[$key] = (int) db()->query("SELECT COUNT(*) FROM {$t} WHERE status='new'")->fetchColumn();
}
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Gutscheine — TI Admin</title>
  <link rel="stylesheet" href="/admin.css">
</head>
<body>
  <header class="admin-header">
    <h1>TI Admin</h1>
    <div>
      <span>Angemeldet als <?= htmlspecialchars($_SESSION['username']) ?></span>
      &nbsp;·&nbsp;
      <a href="/logout.php">Abmelden</a>
    </div>
  </header>
  <main class="admin-main">
    <nav class="tabs">
      <a href="/index.php?tab=angebot">Angebot (<?= $counts['angebot'] ?> neu)</a>
      <a href="/index.php?tab=contact">Kontakt (<?= $counts['contact'] ?> neu)</a>
      <a class="active" href="/vouchers.php">Gutscheine</a>
    </nav>

    <?php if ($msg === 'created'): ?><p class="success">Code angelegt.</p>
    <?php elseif ($msg === 'deleted'): ?><p class="success">Code gelöscht.</p>
    <?php elseif ($msg === 'duplicate'): ?><p class="error">Dieser Code existiert bereits.</p>
    <?php elseif ($msg === 'invalid_code'): ?><p class="error">Ungültiger Code (1–50 Zeichen, keine Leerzeichen).</p>
    <?php elseif ($msg === 'invalid_date'): ?><p class="error">Ungültiges Datum.</p>
    <?php endif; ?>

    <table class="list">
      <thead><tr><th>Code</th><th>Gültig bis</th><th>Erstellt</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['code']) ?></strong></td>
            <td><?= $r['expires_at'] ? htmlspecialchars(date('d.m.Y', strtotime($r['expires_at']))) : '<em>ohne Ablauf</em>' ?></td>
            <td><?= htmlspecialchars(date('d.m.Y', strtotime($r['created_at']))) ?></td>
            <td>
              <form method="post" action="/vouchers.php" style="display:inline"
                    onsubmit="return confirm('Code endgültig löschen?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="op" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="danger" type="submit">Löschen</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="4" style="text-align:center; color: var(--muted); padding: 24px;">Keine Codes angelegt.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <h3 style="margin-top:24px">Neuen Code anlegen</h3>
    <form method="post" action="/vouchers.php" class="filters">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="op" value="create">
      <label>Code
        <input type="text" name="code" maxlength="50" required placeholder="z. B. MESSE2026">
      </label>
      <label>Gültig bis (optional)
        <input type="date" name="expires_at">
      </label>
      <button class="primary" type="submit">Anlegen</button>
    </form>
  </main>
</body>
</html>
