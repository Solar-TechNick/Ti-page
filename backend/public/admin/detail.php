<?php
// backend/public/admin/detail.php
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

$type = $_GET['type'] ?? 'angebot';
$id = (int)($_GET['id'] ?? 0);
$table = $type === 'contact' ? 'contact_requests' : 'angebot_requests';

$stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo 'Nicht gefunden';
    exit;
}

$attachments = [];
if ($type === 'angebot') {
    $a = db()->prepare("SELECT * FROM angebot_attachments WHERE angebot_id = ? ORDER BY id");
    $a->execute([$id]);
    $attachments = $a->fetchAll();
}

$csrf = csrf_token_from_session();
$ipDisplay = $row['ip_address'] ? @inet_ntop($row['ip_address']) : '—';
$msg = $_GET['msg'] ?? null;
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Anfrage #<?= $id ?> — TI Admin</title>
  <link rel="stylesheet" href="/admin.css">
</head>
<body>
  <header class="admin-header">
    <h1>TI Admin</h1>
    <div><a href="/index.php?tab=<?= htmlspecialchars($type) ?>">← Übersicht</a> · <a href="/logout.php">Abmelden</a></div>
  </header>
  <main class="admin-main">
    <?php if ($msg === 'saved'): ?><p class="success">Gespeichert.</p><?php endif; ?>
    <?php if ($msg === 'deleted'): ?><p class="success">Eintrag gelöscht.</p><?php endif; ?>

    <div class="detail">
      <h2>#<?= $id ?> — <?= htmlspecialchars($row['name']) ?>
        <span class="badge <?= htmlspecialchars($row['status']) ?>" style="margin-left:8px"><?= htmlspecialchars($row['status']) ?></span>
      </h2>
      <dl>
        <?php if ($type === 'angebot'): ?>
          <dt>Telefon</dt><dd><?= htmlspecialchars($row['phone']) ?></dd>
          <dt>E-Mail</dt><dd><a href="mailto:<?= htmlspecialchars($row['email']) ?>"><?= htmlspecialchars($row['email']) ?></a></dd>
          <dt>Komponenten</dt><dd><?= htmlspecialchars($row['components']) ?></dd>
          <dt>Objekt</dt><dd><?= htmlspecialchars($row['building'] ?? '—') ?></dd>
          <dt>Standort/PLZ</dt><dd><?= htmlspecialchars($row['location'] ?? '—') ?></dd>
          <dt>Dachform</dt><dd><?= htmlspecialchars($row['roof'] ?? '—') ?></dd>
          <dt>Nutzung</dt><dd><?= htmlspecialchars($row['usage_profile'] ?? '—') ?></dd>
          <dt>Verbrauch</dt><dd><?= htmlspecialchars($row['consumption'] ?? '—') ?></dd>
          <dt>Zeitraum</dt><dd><?= htmlspecialchars($row['timeline'] ?? '—') ?></dd>
          <dt>Details</dt><dd style="white-space:pre-wrap"><?= htmlspecialchars($row['details'] ?? '—') ?></dd>
        <?php else: ?>
          <dt>Kontakt</dt><dd><?= htmlspecialchars($row['contact']) ?></dd>
          <dt>Thema</dt><dd><?= htmlspecialchars($row['topic'] ?? '—') ?></dd>
          <dt>Nachricht</dt><dd style="white-space:pre-wrap"><?= htmlspecialchars($row['message']) ?></dd>
        <?php endif; ?>
        <dt>Eingegangen</dt><dd><?= htmlspecialchars(date('d.m.Y H:i', strtotime($row['created_at']))) ?></dd>
        <dt>IP</dt><dd><?= htmlspecialchars($ipDisplay) ?></dd>
        <dt>Browser</dt><dd style="font-size:11px; color:var(--muted)"><?= htmlspecialchars($row['user_agent'] ?? '—') ?></dd>
      </dl>

      <?php if ($attachments): ?>
        <h3>Anhänge</h3>
        <div class="attachments">
          <?php foreach ($attachments as $a): ?>
            <div class="attachment">
              <?php if (str_starts_with($a['mime_type'], 'image/')): ?>
                <a href="/attachment.php?id=<?= (int)$a['id'] ?>" target="_blank">
                  <img src="/attachment.php?id=<?= (int)$a['id'] ?>&inline=1" alt="">
                </a>
              <?php else: ?>
                <div style="font-size:32px">📄</div>
              <?php endif; ?>
              <div><a href="/attachment.php?id=<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['original_name']) ?></a></div>
              <small><?= round($a['size_bytes']/1024) ?> KB</small>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h3>Status & Notizen</h3>
      <form method="post" action="/action.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="op" value="save">
        <label>Status
          <select name="status">
            <?php foreach (['new','in_progress','handled','spam'] as $s): ?>
              <option value="<?= $s ?>" <?= $row['status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label style="display:block; margin-top:12px;">Interne Notizen
          <textarea name="notes" rows="5" style="width:100%"><?= htmlspecialchars($row['notes'] ?? '') ?></textarea>
        </label>

        <div class="actions">
          <button class="primary" type="submit">Speichern</button>
          <a class="button" href="mailto:<?= htmlspecialchars($type==='angebot'?$row['email']:$row['contact']) ?>?subject=<?= rawurlencode('Re: Ihre Anfrage #'.$id) ?>">Per E-Mail antworten</a>
          <button class="danger" formaction="/action.php" formmethod="post" name="op" value="delete"
                  onclick="return confirm('Eintrag und Anhänge endgültig löschen?');">Löschen (DSGVO)</button>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
