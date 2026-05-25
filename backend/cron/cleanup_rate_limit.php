<?php
// backend/cron/cleanup_rate_limit.php — Plesk hourly cron.
require_once __DIR__ . '/../src/bootstrap.php';
cleanup_rate_limit('PT2H');
echo "rate_limit pruned older than 2h\n";
