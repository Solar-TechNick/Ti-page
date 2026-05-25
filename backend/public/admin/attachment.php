<?php
// backend/public/admin/attachment.php
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$inline = !empty($_GET['inline']);

$stmt = db()->prepare("SELECT * FROM angebot_attachments WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); exit('Nicht gefunden'); }

$path = config('uploads.dir') . '/' . $row['angebot_id'] . '/' . $row['stored_name'];
if (!is_file($path)) { http_response_code(404); exit('Datei fehlt'); }

header('Content-Type: ' . $row['mime_type']);
header('Content-Length: ' . filesize($path));
$disposition = $inline ? 'inline' : 'attachment';
$fname = preg_replace('/[\r\n"]/', '_', $row['original_name']);
header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $fname));
header('X-Content-Type-Options: nosniff');
readfile($path);
