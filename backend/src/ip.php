<?php
// backend/src/ip.php

function pack_ip(?string $ip): ?string
{
    if ($ip === null || $ip === '') return null;
    $packed = @inet_pton($ip);
    return $packed === false ? null : $packed;
}

function anonymize_ip(?string $packed): ?string
{
    if ($packed === null) return null;
    $len = strlen($packed);
    if ($len === 4) {
        // IPv4 /24 → zero last byte
        return substr($packed, 0, 3) . "\0";
    }
    if ($len === 16) {
        // IPv6 /48 → zero last 10 bytes
        return substr($packed, 0, 6) . str_repeat("\0", 10);
    }
    return $packed;
}

function client_ip(): ?string
{
    // Plesk typically passes through REMOTE_ADDR directly (no front proxy);
    // adjust here later if a CDN is added.
    return $_SERVER['REMOTE_ADDR'] ?? null;
}
