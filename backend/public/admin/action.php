<?php
// backend/public/admin/action.php — handles save / delete from the detail view.
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

$csrf = $_POST['csrf'] ?? '';
if (!csrf_check_from_session($csrf)) {
    http_response_code(403);
    exit('CSRF check failed.');
}

$type = $_POST['type'] ?? 'angebot';
$id = (int)($_POST['id'] ?? 0);
$op = $_POST['op'] ?? '';
$table = $type === 'contact' ? 'contact_requests' : 'angebot_requests';

if ($op === 'save') {
    $status = $_POST['status'] ?? 'new';
    if (!in_array($status, ['new','in_progress','handled','spam'], true)) {
        http_response_code(400); exit('Bad status.');
    }
    $notes = (string)($_POST['notes'] ?? '');
    $handledAt = in_array($status, ['handled','spam'], true) ? date('Y-m-d H:i:s') : null;

    $stmt = db()->prepare(
        "UPDATE {$table} SET status = ?, notes = ?, handled_at = COALESCE(?, handled_at) WHERE id = ?"
    );
    $stmt->execute([$status, $notes, $handledAt, $id]);

    header("Location: /detail.php?type={$type}&id={$id}&msg=saved");
    exit;
}

if ($op === 'delete') {
    if ($type === 'angebot') {
        $att = db()->prepare("SELECT angebot_id, stored_name FROM angebot_attachments WHERE angebot_id = ?");
        $att->execute([$id]);
        foreach ($att->fetchAll() as $a) {
            @unlink(config('uploads.dir') . '/' . $a['angebot_id'] . '/' . $a['stored_name']);
        }
        @rmdir(config('uploads.dir') . '/' . $id);
    }
    db()->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
    header("Location: /index.php?tab={$type}&msg=deleted");
    exit;
}

http_response_code(400);
exit('Unknown operation.');
