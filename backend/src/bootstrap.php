<?php
// backend/src/bootstrap.php — loaded by every entry point and by tests.

if (!defined('TI_CONFIG')) {
    $configPath = __DIR__ . '/../config/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('Config file not found: ' . $configPath);
    }
    define('TI_CONFIG', require $configPath);
}

function config(?string $key = null): mixed
{
    if ($key === null) return TI_CONFIG;
    $parts = explode('.', $key);
    $node = TI_CONFIG;
    foreach ($parts as $p) {
        if (!is_array($node) || !array_key_exists($p, $node)) return null;
        $node = $node[$p];
    }
    return $node;
}
