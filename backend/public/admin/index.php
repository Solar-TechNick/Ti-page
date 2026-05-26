<?php
// backend/public/admin/index.php
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

$tab = $_GET['tab'] ?? 'angebot';
$status = $_GET['status'] ?? 'new';
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($status !== 'all') {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}

if ($tab === 'contact') {
    $table = 'contact_requests';
    $cols = 'id, created_at, name, contact AS contact_info, NULL AS phone, status';
    if ($q !== '') {
        $where[] = '(name LIKE :q OR contact LIKE :q OR message LIKE :q)';
        $params[':q'] = "%{$q}%";
    }
} else {
    $table = 'angebot_requests';
    $cols = 'id, created_at, name, email AS contact_info, phone, status';
    if ($q !== '') {
        $where[] = '(name LIKE :q OR email LIKE :q OR phone LIKE :q OR details LIKE :q)';
        $params[':q'] = "%{$q}%";
    }
}

$sql = "SELECT {$cols} FROM {$table}";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = [];
foreach (['contact_requests' => 'contact', 'angebot_requests' => 'angebot'] as $t => $key) {
    $counts[$key] = (int) db()->query("SELECT COUNT(*) FROM {$t} WHERE status='new'")->fetchColumn();
}
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Anfragen — TI Admin</title>
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
      <a class="<?= $tab==='angebot'?'active':'' ?>" href="?tab=angebot">Angebot (<?= $counts['angebot'] ?> neu)</a>
      <a class="<?= $tab==='contact'?'active':'' ?>" href="?tab=contact">Kontakt (<?= $counts['contact'] ?> neu)</a>
      <a href="/vouchers.php">Gutscheine</a>
    </nav>

    <form class="filters" method="get">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <label>Status
        <select name="status">
          <?php foreach (['new','in_progress','handled','spam','all'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Suche
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Name, Mail, Telefon...">
      </label>
      <button type="submit">Filtern</button>
    </form>

    <table class="list">
      <thead><tr><th>ID</th><th>Datum</th><th>Name</th><th>Kontakt</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><a href="/detail.php?type=<?= $tab ?>&id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td>
              <?= htmlspecialchars($r['contact_info']) ?>
              <?php if (!empty($r['phone'])): ?><br><small><?= htmlspecialchars($r['phone']) ?></small><?php endif; ?>
            </td>
            <td><span class="badge <?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="5" style="text-align:center; color: var(--muted); padding: 24px;">Keine Einträge.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
</body>
</html>
