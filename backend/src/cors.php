<?php
// backend/src/cors.php

function cors_headers_for(?string $origin, array $allowed): array
{
    if ($origin === null || !in_array($origin, $allowed, true)) {
        return ['Vary' => 'Origin'];
    }
    return [
        'Access-Control-Allow-Origin'  => $origin,
        'Access-Control-Allow-Methods' => 'POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type',
        'Access-Control-Max-Age'       => '600',
        'Vary'                         => 'Origin',
    ];
}

function emit_cors_headers(array $headers): void
{
    foreach ($headers as $k => $v) {
        header("{$k}: {$v}");
    }
}

function handle_preflight_if_options(?string $origin, array $allowed): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') return;
    emit_cors_headers(cors_headers_for($origin, $allowed));
    http_response_code(204);
    exit;
}
