<?php
// backend/cron/retention.php — Plesk daily cron at 03:00.
require_once __DIR__ . '/../src/bootstrap.php';
retention_apply();
echo "retention applied: " . date('c') . "\n";
