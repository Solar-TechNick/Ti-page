<?php
// backend/src/rate_limit.php

/**
 * Records a hit for the given IP and reports whether the hit is rate-limited.
 * Returns true if the request should be REJECTED (limit exceeded).
 * The window is rolling: counts all hits in the last $window (ISO 8601 duration).
 */
function is_rate_limited(?string $packedIp, int $maxPerWindow, string $window): bool
{
    if ($packedIp === null) return false; // can't track, allow

    $cutoff = (new DateTimeImmutable())->sub(new DateInterval($window))->format('Y-m-d H:i:s');

    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(request_count), 0) FROM rate_limit WHERE ip_address = ? AND window_start >= ?"
    );
    $stmt->execute([$packedIp, $cutoff]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $maxPerWindow) {
        return true;
    }

    db()->prepare(
        "INSERT INTO rate_limit (ip_address, window_start, request_count) VALUES (?, NOW(), 1)
         ON DUPLICATE KEY UPDATE request_count = request_count + 1"
    )->execute([$packedIp]);

    return false;
}

function cleanup_rate_limit(string $maxAge): void
{
    $cutoff = (new DateTimeImmutable())->sub(new DateInterval($maxAge))->format('Y-m-d H:i:s');
    db()->prepare("DELETE FROM rate_limit WHERE window_start < ?")->execute([$cutoff]);
}
