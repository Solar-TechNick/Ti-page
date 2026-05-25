<?php
// backend/cron/setup-admin.php
// One-time script: creates an admin user. Run via `php cron/setup-admin.php`.
// Delete the file after first use.

require_once __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run from CLI only.');
}

echo "TI Admin — initial setup\n";
echo "------------------------\n";
echo "Username: ";
$username = trim((string)fgets(STDIN));
if ($username === '') { fwrite(STDERR, "Empty username.\n"); exit(1); }

echo "Password (input shown): ";
$password = trim((string)fgets(STDIN));
if (strlen($password) < 8) { fwrite(STDERR, "Password too short (min 8).\n"); exit(1); }

// Upsert
$stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
$existing = $stmt->fetchColumn();

if ($existing) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    db()->prepare("UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?")
        ->execute([$hash, $existing]);
    echo "Updated existing user '{$username}'.\n";
} else {
    create_user($username, $password);
    echo "Created user '{$username}'.\n";
}

echo "\nDONE — delete this file:\n  rm " . __FILE__ . "\n";
