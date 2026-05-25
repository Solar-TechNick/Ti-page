<?php
// backend/src/http.php

function json_response_body(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function emit_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_response_body($payload);
}

function ok_response(int $id): array            { return ['ok' => true, 'id' => $id]; }
function validation_error(array $fields): array { return ['ok' => false, 'error' => 'validation', 'fields' => $fields]; }
function rate_limit_error(): array              { return ['ok' => false, 'error' => 'rate_limit']; }
function too_large_error(): array               { return ['ok' => false, 'error' => 'too_large']; }
function server_error(): array                  { return ['ok' => false, 'error' => 'server']; }
