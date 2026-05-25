<?php
// backend/src/retention.php

function retention_apply(): void
{
    _retention_purge_old_handled();
    _retention_anonymize_ips();
}

function _retention_purge_old_handled(): void
{
    $stmt = db()->query(
        "SELECT id FROM angebot_requests
         WHERE status IN ('handled','spam') AND handled_at < DATE_SUB(NOW(), INTERVAL 12 MONTH)"
    );
    $uploadDir = $GLOBALS['__ti_retention_upload_dir'] ?? config('uploads.dir');
    foreach ($stmt->fetchAll() as $r) {
        $dir = $uploadDir . '/' . (int)$r['id'];
        if (is_dir($dir)) {
            foreach (glob("{$dir}/*") as $f) @unlink($f);
            @rmdir($dir);
        }
    }
    db()->exec(
        "DELETE FROM angebot_requests
         WHERE status IN ('handled','spam') AND handled_at < DATE_SUB(NOW(), INTERVAL 12 MONTH)"
    );
    db()->exec(
        "DELETE FROM contact_requests
         WHERE status IN ('handled','spam') AND handled_at < DATE_SUB(NOW(), INTERVAL 12 MONTH)"
    );
}

function _retention_anonymize_ips(): void
{
    foreach (['contact_requests', 'angebot_requests'] as $t) {
        $rows = db()->query(
            "SELECT id, ip_address FROM {$t}
             WHERE ip_address IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetchAll();
        $upd = db()->prepare("UPDATE {$t} SET ip_address = ? WHERE id = ?");
        foreach ($rows as $r) {
            $anon = anonymize_ip($r['ip_address']);
            if ($anon !== $r['ip_address']) {
                $upd->execute([$anon, $r['id']]);
            }
        }
    }
}
