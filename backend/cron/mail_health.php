<?php
// backend/cron/mail_health.php — Plesk daily cron 08:00.
// If any mail_errors.log entries from the past 24h exist, email a summary.
require_once __DIR__ . '/../src/bootstrap.php';

$log = config('logs_dir') . '/mail_errors.log';
if (!is_file($log)) { echo "no log file\n"; exit; }

$cutoff = time() - 24*3600;
$lines = [];
foreach (new SplFileObject($log) as $line) {
    if (preg_match('/^\[(\S+)\]/', (string)$line, $m)) {
        if (strtotime($m[1]) >= $cutoff) {
            $lines[] = rtrim($line, "\r\n");
        }
    }
}

if (!$lines) { echo "no recent failures\n"; exit; }

$body = "Mail-Zustellfehler in den letzten 24h:\n\n" . implode("\n", $lines)
      . "\n\n(Quelle: backend/storage/logs/mail_errors.log)\n";
send_mail([
    'to'      => config('mail.to_address'),
    'subject' => 'TI Backend — Mail-Zustellfehler (' . count($lines) . ')',
    'body'    => $body,
]);
echo "summary sent: " . count($lines) . " failures\n";
