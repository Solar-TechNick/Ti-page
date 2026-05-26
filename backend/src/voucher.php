<?php
// backend/src/voucher.php

function find_active_voucher(string $code): ?array
{
    $code = trim($code);
    if ($code === '') return null;

    $stmt = db()->prepare(
        "SELECT id, code, expires_at, active FROM vouchers
         WHERE code = :code
           AND active = 1
           AND (expires_at IS NULL OR expires_at > NOW())
         LIMIT 1"
    );
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch();
    return $row ?: null;
}
