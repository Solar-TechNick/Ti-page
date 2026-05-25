<?php
// backend/src/csrf.php

function csrf_issue(array &$store): string
{
    $token = bin2hex(random_bytes(16));
    $store['csrf'] = $token;
    return $token;
}

function csrf_verify(string $token, array $store): bool
{
    return !empty($store['csrf']) && hash_equals($store['csrf'], $token);
}

function csrf_token_from_session(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check_from_session(?string $token): bool
{
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}
