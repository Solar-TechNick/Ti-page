<?php
// backend/src/auth.php

function create_user(string $username, string $password): int
{
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = db()->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    $stmt->execute([$username, $hash]);
    return (int) db()->lastInsertId();
}

function verify_login(string $username, string $password): bool
{
    if (is_account_locked($username)) return false;

    $stmt = db()->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) return false;

    if (!password_verify($password, $user['password_hash'])) {
        _record_failure($user['id']);
        return false;
    }
    db()->prepare("UPDATE users SET failed_logins=0, locked_until=NULL, last_login=NOW() WHERE id=?")
        ->execute([$user['id']]);
    return true;
}

function _record_failure(int $userId): void
{
    $sess = config('session');
    db()->prepare("UPDATE users SET failed_logins = failed_logins + 1 WHERE id = ?")->execute([$userId]);
    db()->prepare(
        "UPDATE users
         SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
         WHERE id = ? AND failed_logins >= ?"
    )->execute([$sess['lockout_minutes'], $userId, $sess['lockout_threshold']]);
}

function is_account_locked(string $username): bool
{
    $stmt = db()->prepare("SELECT locked_until FROM users WHERE username = ? AND locked_until > NOW()");
    $stmt->execute([$username]);
    return $stmt->fetch() !== false;
}

function start_admin_session(): void
{
    if (PHP_SESSION_ACTIVE !== session_status()) {
        $sess = config('session');
        session_name($sess['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // Idle timeout
    $sess = config('session');
    if (isset($_SESSION['last_seen']) && (time() - $_SESSION['last_seen']) > $sess['idle_seconds']) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_seen'] = time();
}

function require_login(): void
{
    start_admin_session();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function login_user(int $userId, string $username): void
{
    start_admin_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['last_seen'] = time();
}

function logout_user(): void
{
    start_admin_session();
    session_unset();
    session_destroy();
}
