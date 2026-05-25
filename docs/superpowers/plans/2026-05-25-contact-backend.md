# Contact & Angebot Backend — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the existing `mailto:`-based form flow on the Ti-page website with a real PHP+MySQL backend that stores submissions, sends notifications + visitor autoreplies, supports file uploads on the Angebot form, and provides a small password-protected admin UI on a separate subdomain.

**Architecture:** Plain PHP 8.2 + MySQL (no framework, no runtime Composer deps). Two new subdomains (`api.` and `admin.`) served by Plesk, both rooted into `backend/public/`. Endpoint files expose a pure `handle(array $input, array $files): array` function so tests can call them without an HTTP server. PHPUnit is a dev-only dependency.

**Tech Stack:** PHP 8.2 (CLI + PHP-FPM via Plesk), MariaDB/MySQL 10+/8+ (Plesk-managed), PHPUnit 10 (dev-only via Composer), vanilla JS + CSS for the frontend. Spec: `docs/superpowers/specs/2026-05-25-contact-backend-design.md`.

**Dev environment assumption:** All commands below are run via SSH on the Plesk VPS. The user has access to `php` (Plesk's PHP 8.2 binary in `PATH`, or aliased — verify with `php -v`), `composer` (install via `curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer` if missing), and a MariaDB/MySQL client (`mysql -u root -p` works in Plesk).

**Two test databases:**
- `ti_backend` — production (created during deploy)
- `ti_backend_test` — wiped between tests; only used by PHPUnit

**Git workflow:** Every task ends with a commit. Branch off `main`. Final integration is a single merge or fast-forward at the end.

---

## Phase 1 — Foundation

Set up project skeleton, Composer for dev tooling, database schema, and core helpers (db connection, HTTP responses, CORS, IP packing). No business logic yet.

### Task 1: Create branch and backend skeleton directories

**Files:**
- Create: `backend/public/api/.gitkeep`
- Create: `backend/public/admin/.gitkeep`
- Create: `backend/src/.gitkeep`
- Create: `backend/config/.gitkeep`
- Create: `backend/cron/.gitkeep`
- Create: `backend/sql/.gitkeep`
- Create: `backend/tests/.gitkeep`

- [ ] **Step 1: Create feature branch**

```bash
cd /home/nick/projects/Ti-page
git checkout -b feat/contact-backend
```

- [ ] **Step 2: Create directory skeleton**

```bash
mkdir -p backend/public/api backend/public/admin backend/src backend/config backend/cron backend/sql backend/tests
touch backend/public/api/.gitkeep backend/public/admin/.gitkeep backend/src/.gitkeep \
      backend/config/.gitkeep backend/cron/.gitkeep backend/sql/.gitkeep backend/tests/.gitkeep
```

- [ ] **Step 3: Commit**

```bash
git add backend/
git commit -m "feat(backend): create directory skeleton"
```

### Task 2: Add Composer config and install PHPUnit

**Files:**
- Create: `backend/composer.json`
- Create: `backend/phpunit.xml`

- [ ] **Step 1: Write composer.json**

```json
{
  "name": "ti-prignitz/backend",
  "description": "Contact + Angebot backend for Ti-page",
  "type": "project",
  "require": {
    "php": ">=8.2"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.5"
  },
  "autoload": {
    "files": [
      "src/db.php",
      "src/http.php",
      "src/cors.php",
      "src/csrf.php",
      "src/auth.php",
      "src/rate_limit.php",
      "src/validate.php",
      "src/upload.php",
      "src/ip.php",
      "src/mailer.php"
    ]
  },
  "autoload-dev": {
    "psr-4": { "Ti\\Tests\\": "tests/" }
  },
  "config": { "sort-packages": true }
}
```

- [ ] **Step 2: Write phpunit.xml**

```xml
<?xml version="1.0"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
    bootstrap="tests/bootstrap.php"
    colors="true"
    failOnRisky="true"
    failOnWarning="true"
    cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="backend">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="test"/>
    </php>
</phpunit>
```

- [ ] **Step 3: Install dependencies**

Run: `cd backend && composer install --no-interaction`
Expected: creates `vendor/` and `composer.lock`. (`vendor/` and `composer.lock` are already in the root `.gitignore`.)

- [ ] **Step 4: Verify PHPUnit runs**

Run: `cd backend && vendor/bin/phpunit --version`
Expected: prints "PHPUnit 10.x.x by Sebastian Bergmann and contributors."

- [ ] **Step 5: Commit**

```bash
git add backend/composer.json backend/phpunit.xml
git commit -m "feat(backend): add Composer + PHPUnit dev tooling"
```

### Task 3: Write SQL schema

**Files:**
- Create: `backend/sql/schema.sql`

- [ ] **Step 1: Write schema**

```sql
-- backend/sql/schema.sql
-- Idempotent — safe to re-run.

CREATE TABLE IF NOT EXISTS contact_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    name            VARCHAR(200) NOT NULL,
    contact         VARCHAR(200) NOT NULL,
    topic           VARCHAR(200) NULL,
    message         TEXT NOT NULL,
    ip_address      VARBINARY(16) NULL,
    user_agent      VARCHAR(500) NULL,
    status          ENUM('new','in_progress','handled','spam') NOT NULL DEFAULT 'new',
    handled_at      DATETIME NULL,
    notes           TEXT NULL,
    INDEX (created_at),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS angebot_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    name            VARCHAR(200) NOT NULL,
    phone           VARCHAR(100) NOT NULL,
    email           VARCHAR(200) NOT NULL,
    components      VARCHAR(500) NOT NULL,
    building        VARCHAR(100) NULL,
    location        VARCHAR(200) NULL,
    roof            VARCHAR(100) NULL,
    usage_profile   VARCHAR(100) NULL,
    consumption     VARCHAR(100) NULL,
    timeline        VARCHAR(100) NULL,
    details         TEXT NULL,
    photos_followup TINYINT(1) NOT NULL DEFAULT 0,
    ip_address      VARBINARY(16) NULL,
    user_agent      VARCHAR(500) NULL,
    status          ENUM('new','in_progress','handled','spam') NOT NULL DEFAULT 'new',
    handled_at      DATETIME NULL,
    notes           TEXT NULL,
    INDEX (created_at),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS angebot_attachments (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    angebot_id     INT UNSIGNED NOT NULL,
    stored_name    VARCHAR(120) NOT NULL,
    original_name  VARCHAR(255) NOT NULL,
    mime_type      VARCHAR(100) NOT NULL,
    size_bytes     INT UNSIGNED NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (angebot_id) REFERENCES angebot_requests(id) ON DELETE CASCADE,
    INDEX (angebot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limit (
    ip_address      VARBINARY(16) NOT NULL,
    window_start    DATETIME NOT NULL,
    request_count   INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (ip_address, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50) NOT NULL UNIQUE,
    password_hash   CHAR(60) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login      DATETIME NULL,
    failed_logins   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Create test database and load schema**

Run:
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS ti_backend_test CHARACTER SET utf8mb4;"
mysql -u root -p ti_backend_test < backend/sql/schema.sql
mysql -u root -p ti_backend_test -e "SHOW TABLES;"
```
Expected: six tables listed (`contact_requests`, `angebot_requests`, `angebot_attachments`, `rate_limit`, `users`).

- [ ] **Step 3: Commit**

```bash
git add backend/sql/schema.sql
git commit -m "feat(backend): add MySQL schema"
```

### Task 4: Add config example and a local config

**Files:**
- Create: `backend/config/config.example.php`
- Create: `backend/config/config.php` (gitignored — local only)

- [ ] **Step 1: Write config.example.php**

```php
<?php
// backend/config/config.example.php
// Copy to config.php and fill in. config.php is gitignored.

return [
    'env' => 'production',                             // 'production' | 'test'
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'ti_backend',
        'username' => 'ti_backend_app',
        'password' => 'CHANGE_ME',
    ],
    'mail' => [
        'from_address' => 'anfrage@technik-prignitz.de',
        'from_name'    => 'Technik- & Instandsetzungs GmbH',
        'to_address'   => 'info@technik-prignitz.de',
    ],
    'urls' => [
        'admin_base' => 'https://admin.technik-prignitz.de',
        'public_site' => 'https://technik-prignitz.de',
    ],
    'cors_origins' => [
        'https://technik-prignitz.de',
        'https://www.technik-prignitz.de',
    ],
    'rate_limit' => [
        'max_per_hour' => 5,
    ],
    'uploads' => [
        'dir'              => __DIR__ . '/../storage/uploads',
        'max_file_bytes'   => 10 * 1024 * 1024,
        'max_total_bytes'  => 50 * 1024 * 1024,
        'max_file_count'   => 10,
        'allowed_mimes'    => ['image/jpeg','image/png','image/heic','image/webp','application/pdf'],
    ],
    'logs_dir' => __DIR__ . '/../storage/logs',
    'session' => [
        'name'        => 'tiadmin',
        'idle_seconds' => 8 * 3600,
        'lockout_threshold' => 5,
        'lockout_minutes'   => 15,
    ],
];
```

- [ ] **Step 2: Create local config.php**

Copy and edit for the test database:

```bash
cp backend/config/config.example.php backend/config/config.php
```

Edit `backend/config/config.php` so the `db` section uses the test DB by default (the bootstrap will override per-env):

```php
'db' => [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'ti_backend_test',
    'username' => 'root',
    'password' => 'YOUR_LOCAL_MYSQL_ROOT_PASSWORD',
],
```

(For production we'll update this file separately during deploy — never committed.)

- [ ] **Step 3: Create storage dirs**

```bash
mkdir -p backend/storage/uploads backend/storage/logs
```

- [ ] **Step 4: Commit**

```bash
git add backend/config/config.example.php
git commit -m "feat(backend): add config example"
```

### Task 5: Bootstrap + db helpers (TDD)

**Files:**
- Create: `backend/src/bootstrap.php`
- Create: `backend/src/db.php`
- Create: `backend/tests/bootstrap.php`
- Create: `backend/tests/TestCase.php`
- Create: `backend/tests/DbTest.php`

- [ ] **Step 1: Write tests/bootstrap.php**

```php
<?php
// backend/tests/bootstrap.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/TestCase.php';
```

- [ ] **Step 2: Write tests/TestCase.php**

```php
<?php
namespace Ti\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use PDO;

abstract class TestCase extends PHPUnitTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->truncateAll();
    }

    private function truncateAll(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['angebot_attachments','angebot_requests','contact_requests','rate_limit','users'] as $t) {
            $this->pdo->exec("TRUNCATE TABLE {$t}");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
```

- [ ] **Step 3: Write tests/DbTest.php (the failing test)**

```php
<?php
namespace Ti\Tests;

class DbTest extends TestCase
{
    public function testReturnsSamePdoInstance(): void
    {
        $a = db();
        $b = db();
        $this->assertSame($a, $b);
    }

    public function testCanQueryUsersTable(): void
    {
        $count = db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(0, (int)$count);
    }
}
```

- [ ] **Step 4: Run test to confirm failure**

Run: `cd backend && vendor/bin/phpunit tests/DbTest.php`
Expected: ERROR — `Call to undefined function db()`.

- [ ] **Step 5: Implement src/bootstrap.php**

```php
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
```

- [ ] **Step 6: Implement src/db.php**

```php
<?php
// backend/src/db.php

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $cfg = config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'], $cfg['port'], $cfg['database']
    );
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
```

- [ ] **Step 7: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/DbTest.php`
Expected: 2 tests, 2 assertions, OK.

- [ ] **Step 8: Commit**

```bash
git add backend/src/bootstrap.php backend/src/db.php backend/tests/bootstrap.php backend/tests/TestCase.php backend/tests/DbTest.php
git commit -m "feat(backend): bootstrap + PDO db helper with tests"
```

### Task 6: HTTP response + CORS helpers (TDD)

**Files:**
- Create: `backend/src/http.php`
- Create: `backend/src/cors.php`
- Create: `backend/tests/HttpTest.php`

- [ ] **Step 1: Write tests/HttpTest.php**

```php
<?php
namespace Ti\Tests;

class HttpTest extends \PHPUnit\Framework\TestCase
{
    public function testJsonResponseSerialisesArray(): void
    {
        $body = json_response_body(['ok' => true, 'id' => 42]);
        $this->assertSame('{"ok":true,"id":42}', $body);
    }

    public function testValidationErrorShape(): void
    {
        $body = json_response_body(validation_error(['email' => 'Ungültig']));
        $this->assertSame('{"ok":false,"error":"validation","fields":{"email":"Ungültig"}}', $body);
    }

    public function testRateLimitError(): void
    {
        $body = json_response_body(rate_limit_error());
        $this->assertSame('{"ok":false,"error":"rate_limit"}', $body);
    }

    public function testCorsAllowsListedOrigin(): void
    {
        $headers = cors_headers_for('https://technik-prignitz.de', ['https://technik-prignitz.de']);
        $this->assertSame('https://technik-prignitz.de', $headers['Access-Control-Allow-Origin'] ?? null);
    }

    public function testCorsRejectsUnlistedOrigin(): void
    {
        $headers = cors_headers_for('https://evil.example', ['https://technik-prignitz.de']);
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
    }
}
```

- [ ] **Step 2: Run test — expect undefined functions**

Run: `cd backend && vendor/bin/phpunit tests/HttpTest.php`
Expected: ERROR — undefined function.

- [ ] **Step 3: Implement src/http.php**

```php
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

function ok_response(int $id): array          { return ['ok' => true, 'id' => $id]; }
function validation_error(array $fields): array { return ['ok' => false, 'error' => 'validation', 'fields' => $fields]; }
function rate_limit_error(): array            { return ['ok' => false, 'error' => 'rate_limit']; }
function too_large_error(): array             { return ['ok' => false, 'error' => 'too_large']; }
function server_error(): array                { return ['ok' => false, 'error' => 'server']; }
```

- [ ] **Step 4: Implement src/cors.php**

```php
<?php
// backend/src/cors.php

function cors_headers_for(?string $origin, array $allowed): array
{
    if ($origin === null || !in_array($origin, $allowed, true)) {
        return [
            'Vary' => 'Origin',
        ];
    }
    return [
        'Access-Control-Allow-Origin' => $origin,
        'Access-Control-Allow-Methods' => 'POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type',
        'Access-Control-Max-Age' => '600',
        'Vary' => 'Origin',
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
```

- [ ] **Step 5: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/HttpTest.php`
Expected: 5 tests, OK.

- [ ] **Step 6: Commit**

```bash
git add backend/src/http.php backend/src/cors.php backend/tests/HttpTest.php
git commit -m "feat(backend): http response + cors helpers with tests"
```

### Task 7: IP packing + anonymization (TDD)

**Files:**
- Create: `backend/src/ip.php`
- Create: `backend/tests/IpTest.php`

- [ ] **Step 1: Write tests/IpTest.php**

```php
<?php
namespace Ti\Tests;

class IpTest extends \PHPUnit\Framework\TestCase
{
    public function testPacksIpv4(): void
    {
        $this->assertSame(inet_pton('192.0.2.1'), pack_ip('192.0.2.1'));
    }

    public function testPacksIpv6(): void
    {
        $this->assertSame(inet_pton('2001:db8::1'), pack_ip('2001:db8::1'));
    }

    public function testInvalidIpReturnsNull(): void
    {
        $this->assertNull(pack_ip('not-an-ip'));
    }

    public function testAnonymisesIpv4ToSlash24(): void
    {
        $packed = pack_ip('192.0.2.123');
        $anon = anonymize_ip($packed);
        $this->assertSame(inet_pton('192.0.2.0'), $anon);
    }

    public function testAnonymisesIpv6ToSlash48(): void
    {
        $packed = pack_ip('2001:db8:abcd:1234::1');
        $anon = anonymize_ip($packed);
        $this->assertSame(inet_pton('2001:db8:abcd::'), $anon);
    }
}
```

- [ ] **Step 2: Run test to confirm failure**

Run: `cd backend && vendor/bin/phpunit tests/IpTest.php`
Expected: ERROR — undefined.

- [ ] **Step 3: Implement src/ip.php**

```php
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
```

- [ ] **Step 4: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/IpTest.php`
Expected: 5 tests, OK.

- [ ] **Step 5: Commit**

```bash
git add backend/src/ip.php backend/tests/IpTest.php
git commit -m "feat(backend): IP packing + anonymization helpers"
```

### Task 8: Validation helper (TDD)

**Files:**
- Create: `backend/src/validate.php`
- Create: `backend/tests/ValidateTest.php`

- [ ] **Step 1: Write tests/ValidateTest.php**

```php
<?php
namespace Ti\Tests;

class ValidateTest extends \PHPUnit\Framework\TestCase
{
    public function testRequiredMissing(): void
    {
        $errors = validate_contact([]);
        $this->assertSame('Bitte geben Sie Ihren Namen an.', $errors['name'] ?? null);
        $this->assertSame('Bitte geben Sie einen Kontakt an.', $errors['contact'] ?? null);
        $this->assertSame('Bitte schreiben Sie uns eine Nachricht.', $errors['message'] ?? null);
    }

    public function testValidContact(): void
    {
        $errors = validate_contact([
            'name'    => 'Max',
            'contact' => 'max@example.de',
            'message' => 'Hallo',
        ]);
        $this->assertSame([], $errors);
    }

    public function testFieldLengthLimit(): void
    {
        $errors = validate_contact([
            'name'    => str_repeat('a', 201),
            'contact' => 'a@b.de',
            'message' => 'x',
        ]);
        $this->assertNotEmpty($errors['name'] ?? null);
    }

    public function testAngebotRequiresComponents(): void
    {
        $errors = validate_angebot(['name'=>'M','phone'=>'1','email'=>'a@b.de','privacy'=>'1']);
        $this->assertNotEmpty($errors['components'] ?? null);
    }

    public function testAngebotRequiresPrivacy(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],
        ]);
        $this->assertNotEmpty($errors['privacy'] ?? null);
    }

    public function testAngebotEmailFormat(): void
    {
        $errors = validate_angebot([
            'name'=>'M','phone'=>'1','email'=>'not-email',
            'components'=>['Photovoltaik'],'privacy'=>'1',
        ]);
        $this->assertNotEmpty($errors['email'] ?? null);
    }

    public function testHoneypotDetection(): void
    {
        $this->assertTrue(is_honeypot_triggered(['website' => 'something']));
        $this->assertFalse(is_honeypot_triggered(['website' => '']));
        $this->assertFalse(is_honeypot_triggered([]));
    }
}
```

- [ ] **Step 2: Run test (expect failures)**

Run: `cd backend && vendor/bin/phpunit tests/ValidateTest.php`
Expected: errors / failures — functions undefined.

- [ ] **Step 3: Implement src/validate.php**

```php
<?php
// backend/src/validate.php

function is_honeypot_triggered(array $input): bool
{
    return !empty($input['website'] ?? '');
}

function _str(array $in, string $k, int $max): ?string
{
    $v = $in[$k] ?? null;
    if ($v === null) return null;
    $v = trim((string)$v);
    if ($v === '') return null;
    if (mb_strlen($v) > $max) return false; // sentinel for "too long"
    return $v;
}

function validate_contact(array $in): array
{
    $errors = [];

    $name = _str($in, 'name', 200);
    if ($name === null) $errors['name'] = 'Bitte geben Sie Ihren Namen an.';
    elseif ($name === false) $errors['name'] = 'Name darf höchstens 200 Zeichen lang sein.';

    $contact = _str($in, 'contact', 200);
    if ($contact === null) $errors['contact'] = 'Bitte geben Sie einen Kontakt an.';
    elseif ($contact === false) $errors['contact'] = 'Kontakt darf höchstens 200 Zeichen lang sein.';

    $message = _str($in, 'message', 5000);
    if ($message === null) $errors['message'] = 'Bitte schreiben Sie uns eine Nachricht.';
    elseif ($message === false) $errors['message'] = 'Nachricht darf höchstens 5000 Zeichen lang sein.';

    if (isset($in['topic']) && mb_strlen((string)$in['topic']) > 200) {
        $errors['topic'] = 'Thema darf höchstens 200 Zeichen lang sein.';
    }

    return $errors;
}

function validate_angebot(array $in): array
{
    $errors = [];

    $name = _str($in, 'name', 200);
    if (!$name) $errors['name'] = 'Bitte geben Sie Ihren Namen an.';

    $phone = _str($in, 'phone', 100);
    if (!$phone) $errors['phone'] = 'Bitte geben Sie eine Telefonnummer an.';

    $email = _str($in, 'email', 200);
    if (!$email) {
        $errors['email'] = 'Bitte geben Sie eine E-Mail-Adresse an.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
    }

    $components = $in['components'] ?? null;
    if (!is_array($components) || count($components) === 0) {
        $errors['components'] = 'Bitte wählen Sie mindestens eine Komponente aus.';
    }

    if (empty($in['privacy']) || $in['privacy'] !== '1' && $in['privacy'] !== true && $in['privacy'] !== 1) {
        $errors['privacy'] = 'Bitte bestätigen Sie die Datenschutzerklärung.';
    }

    foreach (['building'=>100,'location'=>200,'roof'=>100,'usage'=>100,'consumption'=>100,'timeline'=>100] as $k => $max) {
        if (isset($in[$k]) && mb_strlen((string)$in[$k]) > $max) {
            $errors[$k] = ucfirst($k) . " darf höchstens {$max} Zeichen lang sein.";
        }
    }
    if (isset($in['details']) && mb_strlen((string)$in['details']) > 5000) {
        $errors['details'] = 'Details dürfen höchstens 5000 Zeichen lang sein.';
    }

    return $errors;
}
```

- [ ] **Step 4: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/ValidateTest.php`
Expected: 7 tests, OK.

- [ ] **Step 5: Commit**

```bash
git add backend/src/validate.php backend/tests/ValidateTest.php
git commit -m "feat(backend): validation + honeypot helpers"
```

### Task 9: Rate limiter (TDD)

**Files:**
- Create: `backend/src/rate_limit.php`
- Create: `backend/tests/RateLimitTest.php`

- [ ] **Step 1: Write tests/RateLimitTest.php**

```php
<?php
namespace Ti\Tests;

class RateLimitTest extends TestCase
{
    public function testFirstHitIsAllowed(): void
    {
        $packed = pack_ip('192.0.2.10');
        $this->assertFalse(is_rate_limited($packed, 5, 'PT1H'));
    }

    public function testHitsAccumulateUntilLimit(): void
    {
        $packed = pack_ip('192.0.2.11');
        for ($i = 1; $i <= 5; $i++) {
            $this->assertFalse(is_rate_limited($packed, 5, 'PT1H'), "hit {$i} should pass");
        }
        $this->assertTrue(is_rate_limited($packed, 5, 'PT1H'), 'hit 6 should be blocked');
    }

    public function testDifferentIpsAreIndependent(): void
    {
        $a = pack_ip('192.0.2.20');
        $b = pack_ip('192.0.2.21');
        for ($i = 0; $i < 5; $i++) is_rate_limited($a, 5, 'PT1H');
        $this->assertFalse(is_rate_limited($b, 5, 'PT1H'));
    }

    public function testCleanupRemovesOldWindows(): void
    {
        $packed = pack_ip('192.0.2.30');
        db()->prepare("INSERT INTO rate_limit (ip_address, window_start, request_count) VALUES (?, ?, 1)")
            ->execute([$packed, '2020-01-01 00:00:00']);
        cleanup_rate_limit('PT2H');
        $remaining = db()->query("SELECT COUNT(*) FROM rate_limit")->fetchColumn();
        $this->assertSame(0, (int)$remaining);
    }
}
```

- [ ] **Step 2: Run test (expect undefined)**

Run: `cd backend && vendor/bin/phpunit tests/RateLimitTest.php`
Expected: errors.

- [ ] **Step 3: Implement src/rate_limit.php**

```php
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
```

- [ ] **Step 4: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/RateLimitTest.php`
Expected: 4 tests, OK.

- [ ] **Step 5: Commit**

```bash
git add backend/src/rate_limit.php backend/tests/RateLimitTest.php
git commit -m "feat(backend): rate limiter with sliding window"
```

---

## Phase 2 — Public API (Contact + Angebot)

Implement the two submission endpoints end-to-end using TDD. Mailer is stubbed in tests via a switchable function reference so we don't actually send mail during tests.

### Task 10: Mailer with test-injectable transport

**Files:**
- Create: `backend/src/mailer.php`
- Create: `backend/tests/MailerTest.php`

- [ ] **Step 1: Write tests/MailerTest.php**

```php
<?php
namespace Ti\Tests;

class MailerTest extends \PHPUnit\Framework\TestCase
{
    public function testTransportCapturesMessage(): void
    {
        $captured = [];
        set_mail_transport(function(array $msg) use (&$captured) {
            $captured[] = $msg;
            return true;
        });

        $ok = send_mail([
            'to'      => 'a@b.de',
            'subject' => 'Hallo',
            'body'    => 'Test',
        ]);

        $this->assertTrue($ok);
        $this->assertCount(1, $captured);
        $this->assertSame('a@b.de', $captured[0]['to']);
        $this->assertSame('Hallo', $captured[0]['subject']);
    }

    public function testSubjectStripsCrlfHeaderInjection(): void
    {
        $captured = [];
        set_mail_transport(function(array $msg) use (&$captured) {
            $captured[] = $msg; return true;
        });
        send_mail([
            'to'      => 'a@b.de',
            'subject' => "Hi\r\nBcc: attacker@evil",
            'body'    => '.',
        ]);
        $this->assertSame('Hi Bcc: attacker@evil', $captured[0]['subject']);
    }

    public function testReplyToHeaderRespected(): void
    {
        $captured = [];
        set_mail_transport(function(array $msg) use (&$captured) {
            $captured[] = $msg; return true;
        });
        send_mail(['to'=>'a@b.de','subject'=>'s','body'=>'b','reply_to'=>'visitor@example.de']);
        $this->assertStringContainsString('Reply-To: visitor@example.de', $captured[0]['headers']);
    }
}
```

- [ ] **Step 2: Run test (expect undefined)**

Run: `cd backend && vendor/bin/phpunit tests/MailerTest.php`
Expected: errors.

- [ ] **Step 3: Implement src/mailer.php**

```php
<?php
// backend/src/mailer.php

function set_mail_transport(callable $transport): void
{
    $GLOBALS['__ti_mail_transport'] = $transport;
}

function send_mail(array $msg): bool
{
    $cfg = config('mail');
    $from = sprintf('%s <%s>', $cfg['from_name'], $cfg['from_address']);
    $subject = _sanitize_header(($msg['subject'] ?? '(ohne Betreff)'), 200);

    $headers = [
        "From: {$from}",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=utf-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if (!empty($msg['reply_to'])) {
        $headers[] = 'Reply-To: ' . _sanitize_header($msg['reply_to'], 200);
    }
    $headersStr = implode("\r\n", $headers);

    $transport = $GLOBALS['__ti_mail_transport'] ?? function(array $m): bool {
        return @mail($m['to'], $m['subject'], $m['body'], $m['headers']);
    };

    $ok = $transport([
        'to'      => $msg['to'],
        'subject' => $subject,
        'body'    => $msg['body'],
        'headers' => $headersStr,
    ]);

    if (!$ok) {
        _mail_log_failure($msg, $subject);
    }
    return (bool)$ok;
}

function _sanitize_header(string $v, int $maxLen): string
{
    $v = preg_replace('/[\r\n]+/', ' ', $v);
    $v = mb_substr($v, 0, $maxLen);
    return $v;
}

function _mail_log_failure(array $msg, string $subject): void
{
    $dir = config('logs_dir');
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $line = sprintf("[%s] mail failure to=%s subject=%s\n",
        date('c'), $msg['to'] ?? '?', $subject);
    @file_put_contents($dir . '/mail_errors.log', $line, FILE_APPEND);
}
```

- [ ] **Step 4: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/MailerTest.php`
Expected: 3 tests, OK.

- [ ] **Step 5: Commit**

```bash
git add backend/src/mailer.php backend/tests/MailerTest.php
git commit -m "feat(backend): mailer with test-injectable transport"
```

### Task 11: Contact endpoint — handle() function (TDD)

**Files:**
- Create: `backend/public/api/contact.php`
- Create: `backend/tests/ContactEndpointTest.php`

- [ ] **Step 1: Write tests/ContactEndpointTest.php**

```php
<?php
namespace Ti\Tests;

require_once __DIR__ . '/../public/api/contact.php';

class ContactEndpointTest extends TestCase
{
    private array $mails;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mails = [];
        $caps = &$this->mails;
        set_mail_transport(function(array $m) use (&$caps) { $caps[] = $m; return true; });
    }

    public function testHappyPathStoresAndMails(): void
    {
        $result = contact_handle([
            'name'    => 'Max Mustermann',
            'contact' => 'max@example.de',
            'topic'   => 'PV',
            'message' => 'Bitte melden.',
            'website' => '',
        ], packed_ip: pack_ip('192.0.2.1'), userAgent: 'TestUA/1.0');

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok']);
        $this->assertIsInt($result['body']['id']);

        $row = db()->query('SELECT * FROM contact_requests')->fetch();
        $this->assertSame('Max Mustermann', $row['name']);
        $this->assertSame('PV', $row['topic']);

        $this->assertCount(2, $this->mails); // operator + visitor autoreply
    }

    public function testMissingFieldsReturn400(): void
    {
        $result = contact_handle([], packed_ip: pack_ip('192.0.2.1'), userAgent: '');
        $this->assertSame(400, $result['status']);
        $this->assertSame('validation', $result['body']['error']);
        $this->assertArrayHasKey('name', $result['body']['fields']);
    }

    public function testHoneypotReturnsFakeSuccessAndDoesNotStore(): void
    {
        $result = contact_handle([
            'name'=>'x','contact'=>'x@y.de','message'=>'.',
            'website' => 'spam',
        ], packed_ip: pack_ip('192.0.2.2'), userAgent: '');
        $this->assertSame(200, $result['status']);
        $count = (int)db()->query('SELECT COUNT(*) FROM contact_requests')->fetchColumn();
        $this->assertSame(0, $count);
        $this->assertCount(0, $this->mails);
    }

    public function testRateLimitReturns429(): void
    {
        $packed = pack_ip('192.0.2.99');
        for ($i = 0; $i < 5; $i++) {
            contact_handle(['name'=>"u{$i}",'contact'=>'x@y.de','message'=>'.'], $packed, '');
        }
        $result = contact_handle(['name'=>'u6','contact'=>'x@y.de','message'=>'.'], $packed, '');
        $this->assertSame(429, $result['status']);
        $this->assertSame('rate_limit', $result['body']['error']);
    }
}
```

- [ ] **Step 2: Run test (expect undefined)**

Run: `cd backend && vendor/bin/phpunit tests/ContactEndpointTest.php`
Expected: errors.

- [ ] **Step 3: Implement public/api/contact.php**

```php
<?php
// backend/public/api/contact.php

require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * Pure handler. Returns ['status'=>int,'body'=>array].
 * Side effects: DB writes, mail sends.
 */
function contact_handle(array $input, ?string $packed_ip, string $userAgent): array
{
    if (is_honeypot_triggered($input)) {
        return ['status' => 200, 'body' => ['ok' => true, 'id' => 0]];
    }

    if (is_rate_limited($packed_ip, (int)config('rate_limit.max_per_hour'), 'PT1H')) {
        return ['status' => 429, 'body' => rate_limit_error()];
    }

    $errors = validate_contact($input);
    if ($errors) {
        return ['status' => 400, 'body' => validation_error($errors)];
    }

    $stmt = db()->prepare(
        "INSERT INTO contact_requests (name, contact, topic, message, ip_address, user_agent)
         VALUES (:name, :contact, :topic, :message, :ip, :ua)"
    );
    $stmt->execute([
        ':name'    => trim($input['name']),
        ':contact' => trim($input['contact']),
        ':topic'   => isset($input['topic']) ? trim($input['topic']) : null,
        ':message' => trim($input['message']),
        ':ip'      => $packed_ip,
        ':ua'      => mb_substr($userAgent, 0, 500),
    ]);
    $id = (int) db()->lastInsertId();

    _contact_notify_operator($id, $input);
    _contact_autoreply_visitor($input);

    return ['status' => 200, 'body' => ok_response($id)];
}

function _contact_notify_operator(int $id, array $in): void
{
    $cfg = config('mail');
    $adminUrl = config('urls.admin_base') . "/detail.php?type=contact&id={$id}";
    $body = implode("\n", [
        'Neue Kontaktanfrage:',
        '',
        'Name:    ' . $in['name'],
        'Kontakt: ' . $in['contact'],
        'Thema:   ' . ($in['topic'] ?? '-'),
        '',
        'Nachricht:',
        $in['message'],
        '',
        '────────────────────────────',
        'Eingegangen: ' . date('d.m.Y H:i'),
        'Im Admin:    ' . $adminUrl,
        'Antwort an:  ' . $in['contact'],
    ]);
    send_mail([
        'to'       => $cfg['to_address'],
        'subject'  => 'Neue Kontaktanfrage: ' . ($in['topic'] ?? 'Anfrage') . " (#{$id})",
        'body'     => $body,
        'reply_to' => $in['contact'],
    ]);
}

function _contact_autoreply_visitor(array $in): void
{
    if (!filter_var($in['contact'] ?? '', FILTER_VALIDATE_EMAIL)) return;
    $body = implode("\n", [
        'Vielen Dank für Ihre Nachricht.',
        '',
        'Wir haben Ihre Anfrage erhalten und melden uns innerhalb von 2 Werktagen.',
        '',
        'Ihre Anfrage:',
        '-------------',
        ($in['topic'] ?? '') !== '' ? 'Thema: ' . $in['topic'] : '',
        $in['message'],
        '',
        '────────────────────────────',
        'Technik- & Instandsetzungs GmbH',
        'Quitzower Damm 15, 19348 Sükow',
        'Tel.: +49 3876 612474',
    ]);
    send_mail([
        'to'      => $in['contact'],
        'subject' => 'Ihre Anfrage bei Technik- & Instandsetzungs GmbH',
        'body'    => $body,
    ]);
}

// Script body — only runs when called via HTTP, not in tests.
if (PHP_SAPI !== 'cli' && !defined('TI_TEST')) {
    handle_preflight_if_options($_SERVER['HTTP_ORIGIN'] ?? null, config('cors_origins'));
    emit_cors_headers(cors_headers_for($_SERVER['HTTP_ORIGIN'] ?? null, config('cors_origins')));

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        emit_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    $result = contact_handle(
        $input,
        pack_ip(client_ip()),
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
    );
    emit_json($result['status'], $result['body']);
}
```

- [ ] **Step 4: Add TI_TEST guard to tests/bootstrap.php**

Edit `backend/tests/bootstrap.php` to declare the constant before any endpoint files are included:

```php
<?php
define('TI_TEST', true);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/TestCase.php';
```

- [ ] **Step 5: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/ContactEndpointTest.php`
Expected: 4 tests, OK.

- [ ] **Step 6: Commit**

```bash
git add backend/public/api/contact.php backend/tests/ContactEndpointTest.php backend/tests/bootstrap.php
git commit -m "feat(backend): contact endpoint handler with TDD coverage"
```

### Task 12: Upload validator + storage (TDD)

**Files:**
- Create: `backend/src/upload.php`
- Create: `backend/tests/UploadTest.php`

- [ ] **Step 1: Write tests/UploadTest.php**

```php
<?php
namespace Ti\Tests;

class UploadTest extends \PHPUnit\Framework\TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ti-upload-test-' . uniqid();
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        // recursive cleanup
        $this->rrmdir($this->tmpDir);
    }

    private function rrmdir(string $d): void
    {
        if (!is_dir($d)) return;
        foreach (scandir($d) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = "{$d}/{$f}";
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($d);
    }

    private function fakeUpload(string $name, string $content, string $type = 'image/jpeg'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $content);
        return [
            'name'     => $name,
            'type'     => $type,
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($content),
        ];
    }

    public function testNormaliseFilesEntryEmpty(): void
    {
        $result = normalise_files_input([]);
        $this->assertSame([], $result);
    }

    public function testNormaliseFilesEntrySingle(): void
    {
        $files = ['files' => [
            'name'     => ['a.jpg'],
            'type'     => ['image/jpeg'],
            'tmp_name' => ['/tmp/x'],
            'error'    => [0],
            'size'     => [123],
        ]];
        $result = normalise_files_input($files['files']);
        $this->assertCount(1, $result);
        $this->assertSame('a.jpg', $result[0]['name']);
    }

    public function testValidateRejectsTooManyFiles(): void
    {
        $files = array_fill(0, 11, $this->fakeUpload('a.jpg', 'x'));
        $err = validate_uploads($files, [
            'max_file_count' => 10,
            'max_file_bytes' => 1024,
            'max_total_bytes' => 10000,
            'allowed_mimes' => ['image/jpeg'],
        ]);
        $this->assertSame('validation', $err['kind']);
    }

    public function testValidateRejectsTooLargeFile(): void
    {
        $big = str_repeat('x', 2000);
        $files = [$this->fakeUpload('a.jpg', $big)];
        $err = validate_uploads($files, [
            'max_file_count' => 10,
            'max_file_bytes' => 1024,
            'max_total_bytes' => 100000,
            'allowed_mimes' => ['image/jpeg','application/pdf'],
        ]);
        $this->assertSame('too_large', $err['kind']);
    }

    public function testValidateRejectsTotalSize(): void
    {
        $half = str_repeat('x', 600);
        $files = [$this->fakeUpload('a.jpg', $half), $this->fakeUpload('b.jpg', $half)];
        $err = validate_uploads($files, [
            'max_file_count' => 10,
            'max_file_bytes' => 10000,
            'max_total_bytes' => 1000,
            'allowed_mimes' => ['image/jpeg'],
        ]);
        $this->assertSame('too_large', $err['kind']);
    }

    public function testValidateAcceptsValidFile(): void
    {
        // 1x1 JPEG signature
        $jpeg = "\xFF\xD8\xFF\xE0" . str_repeat("\0", 100);
        $files = [$this->fakeUpload('photo.jpg', $jpeg, 'image/jpeg')];
        $err = validate_uploads($files, [
            'max_file_count' => 10,
            'max_file_bytes' => 10000,
            'max_total_bytes' => 100000,
            'allowed_mimes' => ['image/jpeg'],
        ]);
        $this->assertNull($err);
    }

    public function testStoreUploadsCreatesDirAndReturnsMetadata(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0" . str_repeat("\0", 100);
        $files = [$this->fakeUpload('mein bild.jpg', $jpeg, 'image/jpeg')];
        $meta = store_uploads($files, $this->tmpDir, 42, [
            'image/jpeg' => 'jpg',
        ]);
        $this->assertCount(1, $meta);
        $this->assertSame('mein bild.jpg', $meta[0]['original_name']);
        $this->assertSame('image/jpeg', $meta[0]['mime_type']);
        $this->assertFileExists($this->tmpDir . '/42/' . $meta[0]['stored_name']);
        $this->assertStringEndsWith('.jpg', $meta[0]['stored_name']);
    }
}
```

- [ ] **Step 2: Run test (expect undefined)**

Run: `cd backend && vendor/bin/phpunit tests/UploadTest.php`
Expected: errors.

- [ ] **Step 3: Implement src/upload.php**

```php
<?php
// backend/src/upload.php

const _MIME_EXT = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/heic'      => 'heic',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf',
];

/**
 * Convert PHP's bizarre $_FILES['fieldname'] layout into a flat list of files.
 * Accepts the inner array (e.g. $_FILES['files']) — not the whole $_FILES.
 */
function normalise_files_input(array $entry): array
{
    if (!isset($entry['name'])) return [];
    if (!is_array($entry['name'])) {
        // single file
        return [[
            'name'     => $entry['name'],
            'type'     => $entry['type'],
            'tmp_name' => $entry['tmp_name'],
            'error'    => $entry['error'],
            'size'     => $entry['size'],
        ]];
    }
    $out = [];
    $n = count($entry['name']);
    for ($i = 0; $i < $n; $i++) {
        if (($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $out[] = [
            'name'     => $entry['name'][$i],
            'type'     => $entry['type'][$i],
            'tmp_name' => $entry['tmp_name'][$i],
            'error'    => $entry['error'][$i],
            'size'     => $entry['size'][$i],
        ];
    }
    return $out;
}

/**
 * Validate a list of normalised uploads.
 * Returns null when OK, or ['kind' => 'too_large'|'validation', 'fields' => [...]].
 */
function validate_uploads(array $files, array $cfg): ?array
{
    if (count($files) > $cfg['max_file_count']) {
        return ['kind' => 'validation', 'fields' => ['files' => 'Zu viele Dateien (max. ' . $cfg['max_file_count'] . ').']];
    }

    $total = 0;
    foreach ($files as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            return ['kind' => 'validation', 'fields' => ['files' => 'Fehler beim Upload: ' . $f['name']]];
        }
        if ($f['size'] > $cfg['max_file_bytes']) {
            return ['kind' => 'too_large', 'fields' => ['files' => $f['name'] . ' überschreitet ' . ($cfg['max_file_bytes']/1024/1024) . ' MB.']];
        }
        $total += $f['size'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        if (!in_array($detectedMime, $cfg['allowed_mimes'], true)) {
            return ['kind' => 'validation', 'fields' => ['files' => 'Dateityp nicht erlaubt: ' . $f['name']]];
        }
    }
    if ($total > $cfg['max_total_bytes']) {
        return ['kind' => 'too_large', 'fields' => ['files' => 'Gesamtgröße überschritten.']];
    }
    return null;
}

/**
 * Move uploaded files into $baseDir/<id>/<random>.<ext>. Returns metadata rows for DB insert.
 */
function store_uploads(array $files, string $baseDir, int $requestId, array $mimeExtMap = null): array
{
    $mimeExtMap = $mimeExtMap ?? _MIME_EXT;
    $dir = $baseDir . '/' . $requestId;
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        throw new RuntimeException("Cannot create upload dir: {$dir}");
    }

    $out = [];
    foreach ($files as $f) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);

        $ext = $mimeExtMap[$mime] ?? 'bin';
        $stored = bin2hex(random_bytes(6)) . '.' . $ext;
        $target = "{$dir}/{$stored}";

        // Use rename when not a real PHP upload (tests), move_uploaded_file in prod
        if (is_uploaded_file($f['tmp_name'])) {
            if (!move_uploaded_file($f['tmp_name'], $target)) {
                throw new RuntimeException('move_uploaded_file failed');
            }
        } else {
            if (!rename($f['tmp_name'], $target)) {
                throw new RuntimeException('rename failed');
            }
        }
        @chmod($target, 0640);

        $out[] = [
            'stored_name'   => $stored,
            'original_name' => mb_substr($f['name'], 0, 255),
            'mime_type'     => $mime,
            'size_bytes'    => filesize($target),
        ];
    }
    return $out;
}
```

- [ ] **Step 4: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/UploadTest.php`
Expected: 7 tests, OK.

- [ ] **Step 5: Commit**

```bash
git add backend/src/upload.php backend/tests/UploadTest.php
git commit -m "feat(backend): upload normalisation, validation, storage"
```

### Task 13: Angebot endpoint — handle() function (TDD)

**Files:**
- Create: `backend/public/api/angebot.php`
- Create: `backend/tests/AngebotEndpointTest.php`

- [ ] **Step 1: Write tests/AngebotEndpointTest.php**

```php
<?php
namespace Ti\Tests;

require_once __DIR__ . '/../public/api/angebot.php';

class AngebotEndpointTest extends TestCase
{
    private array $mails;
    private string $uploadDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mails = [];
        $caps = &$this->mails;
        set_mail_transport(function(array $m) use (&$caps) { $caps[] = $m; return true; });

        $this->uploadDir = sys_get_temp_dir() . '/ti-up-' . uniqid();
        mkdir($this->uploadDir, 0700, true);
        // Override upload dir for the test by replacing in config
        $GLOBALS['__ti_override_upload_dir'] = $this->uploadDir;
    }

    public function testHappyPathStoresAndCsvComponents(): void
    {
        $result = angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik','Stromspeicher'],
            'building'=>'Einfamilienhaus','location'=>'19348',
            'roof'=>'Satteldach','usage'=>'3-4 Personen',
            'consumption'=>'4500','timeline'=>'In 1-3 Monaten',
            'details'=>'PV bitte','photos_followup'=>'1','privacy'=>'1',
        ], [], pack_ip('192.0.2.50'), 'UA');

        $this->assertSame(200, $result['status']);
        $row = db()->query('SELECT * FROM angebot_requests')->fetch();
        $this->assertSame('Photovoltaik, Stromspeicher', $row['components']);
        $this->assertSame('Anna', $row['name']);
        $this->assertCount(2, $this->mails);
    }

    public function testValidationErrors(): void
    {
        $result = angebot_handle([], [], pack_ip('192.0.2.51'), 'UA');
        $this->assertSame(400, $result['status']);
        $this->assertSame('validation', $result['body']['error']);
    }

    public function testHoneypotSilentSuccess(): void
    {
        $result = angebot_handle([
            'name'=>'x','phone'=>'1','email'=>'a@b.de',
            'components'=>['x'],'privacy'=>'1','website'=>'spam'
        ], [], pack_ip('192.0.2.52'), 'UA');
        $this->assertSame(200, $result['status']);
        $this->assertSame(0, (int)db()->query('SELECT COUNT(*) FROM angebot_requests')->fetchColumn());
    }

    public function testFileUploadStored(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0" . str_repeat("\0", 200);
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $jpeg);

        $files = [
            'files' => [
                'name'     => ['photo.jpg'],
                'type'     => ['image/jpeg'],
                'tmp_name' => [$tmp],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [filesize($tmp)],
            ],
        ];
        $result = angebot_handle([
            'name'=>'Anna','phone'=>'1','email'=>'a@b.de',
            'components'=>['Photovoltaik'],'privacy'=>'1',
        ], $files, pack_ip('192.0.2.53'), 'UA');

        $this->assertSame(200, $result['status']);
        $attachments = db()->query('SELECT * FROM angebot_attachments')->fetchAll();
        $this->assertCount(1, $attachments);
        $this->assertSame('photo.jpg', $attachments[0]['original_name']);
        $this->assertFileExists($this->uploadDir . '/' . $attachments[0]['angebot_id'] . '/' . $attachments[0]['stored_name']);
    }

    public function testRejectTooLargeFile(): void
    {
        $big = str_repeat('x', 11 * 1024 * 1024);
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $big);
        $files = ['files' => [
            'name'=>['big.bin'],'type'=>['application/octet-stream'],
            'tmp_name'=>[$tmp],'error'=>[UPLOAD_ERR_OK],'size'=>[filesize($tmp)],
        ]];

        $result = angebot_handle([
            'name'=>'A','phone'=>'1','email'=>'a@b.de',
            'components'=>['x'],'privacy'=>'1',
        ], $files, pack_ip('192.0.2.54'), 'UA');
        $this->assertSame(413, $result['status']);
    }
}
```

- [ ] **Step 2: Run test (expect undefined)**

Run: `cd backend && vendor/bin/phpunit tests/AngebotEndpointTest.php`
Expected: errors.

- [ ] **Step 3: Implement public/api/angebot.php**

```php
<?php
// backend/public/api/angebot.php

require_once __DIR__ . '/../../src/bootstrap.php';

function angebot_handle(array $input, array $filesSuperglobal, ?string $packed_ip, string $userAgent): array
{
    if (is_honeypot_triggered($input)) {
        return ['status' => 200, 'body' => ['ok' => true, 'id' => 0]];
    }

    if (is_rate_limited($packed_ip, (int)config('rate_limit.max_per_hour'), 'PT1H')) {
        return ['status' => 429, 'body' => rate_limit_error()];
    }

    $errors = validate_angebot($input);
    if ($errors) {
        return ['status' => 400, 'body' => validation_error($errors)];
    }

    $files = normalise_files_input($filesSuperglobal['files'] ?? []);
    $uploadCfg = config('uploads');
    if ($files) {
        $err = validate_uploads($files, $uploadCfg);
        if ($err !== null) {
            $status = $err['kind'] === 'too_large' ? 413 : 400;
            $body = $err['kind'] === 'too_large' ? too_large_error() : validation_error($err['fields']);
            return ['status' => $status, 'body' => $body];
        }
    }

    $components = implode(', ', array_map('trim', $input['components']));

    $stmt = db()->prepare(
        "INSERT INTO angebot_requests
        (name, phone, email, components, building, location, roof, usage_profile,
         consumption, timeline, details, photos_followup, ip_address, user_agent)
        VALUES (:name,:phone,:email,:components,:building,:location,:roof,:usage,
                :consumption,:timeline,:details,:photos,:ip,:ua)"
    );
    $stmt->execute([
        ':name'        => trim($input['name']),
        ':phone'       => trim($input['phone']),
        ':email'       => trim($input['email']),
        ':components'  => mb_substr($components, 0, 500),
        ':building'    => $input['building']    ?? null,
        ':location'    => $input['location']    ?? null,
        ':roof'        => $input['roof']        ?? null,
        ':usage'       => $input['usage']       ?? null,
        ':consumption' => $input['consumption'] ?? null,
        ':timeline'    => $input['timeline']    ?? null,
        ':details'     => $input['details']     ?? null,
        ':photos'      => !empty($input['photos_followup']) ? 1 : 0,
        ':ip'          => $packed_ip,
        ':ua'          => mb_substr($userAgent, 0, 500),
    ]);
    $id = (int)db()->lastInsertId();

    $uploadBase = $GLOBALS['__ti_override_upload_dir'] ?? $uploadCfg['dir'];
    $attachments = [];
    if ($files) {
        $attachments = store_uploads($files, $uploadBase, $id);
        $ins = db()->prepare(
            "INSERT INTO angebot_attachments (angebot_id, stored_name, original_name, mime_type, size_bytes)
             VALUES (:aid, :sn, :on, :mt, :sz)"
        );
        foreach ($attachments as $a) {
            $ins->execute([':aid'=>$id,':sn'=>$a['stored_name'],':on'=>$a['original_name'],
                           ':mt'=>$a['mime_type'],':sz'=>$a['size_bytes']]);
        }
    }

    _angebot_notify_operator($id, $input, $components, $attachments);
    _angebot_autoreply_visitor($input, $components);

    return ['status' => 200, 'body' => ok_response($id)];
}

function _angebot_notify_operator(int $id, array $in, string $components, array $attachments): void
{
    $cfg = config('mail');
    $adminUrl = config('urls.admin_base') . "/detail.php?type=angebot&id={$id}";
    $totalSize = array_sum(array_column($attachments, 'size_bytes'));
    $attLine = $attachments
        ? sprintf('Anhänge: %d Dateien (%.1f MB) — im Admin ansehen:', count($attachments), $totalSize/1024/1024)
        : 'Anhänge: keine';

    $body = implode("\n", [
        'Neue Angebotsanfrage:',
        '',
        'Kontakt',
        '  Name:    ' . $in['name'],
        '  Telefon: ' . $in['phone'],
        '  E-Mail:  ' . $in['email'],
        '',
        'Projekt',
        '  Komponenten:  ' . $components,
        '  Objekt:       ' . ($in['building']    ?? '-'),
        '  Standort/PLZ: ' . ($in['location']    ?? '-'),
        '  Dachform:     ' . ($in['roof']        ?? '-'),
        '  Nutzung:      ' . ($in['usage']       ?? '-'),
        '  Verbrauch:    ' . ($in['consumption'] ?? '-'),
        '  Zeitraum:     ' . ($in['timeline']    ?? '-'),
        '',
        'Details:',
        $in['details'] ?? '-',
        '',
        $attLine,
        $attachments ? $adminUrl : '',
        '',
        '────────────────────────────',
        'Eingegangen: ' . date('d.m.Y H:i'),
        'Im Admin:    ' . $adminUrl,
        'Antwort an:  ' . $in['email'],
    ]);
    send_mail([
        'to'       => $cfg['to_address'],
        'subject'  => "Neue Angebotsanfrage: {$components} (#{$id})",
        'body'     => $body,
        'reply_to' => $in['email'],
    ]);
}

function _angebot_autoreply_visitor(array $in, string $components): void
{
    $body = implode("\n", [
        'Vielen Dank für Ihre Angebotsanfrage.',
        '',
        'Wir haben Ihre Angaben erhalten und melden uns innerhalb von 2 Werktagen.',
        '',
        'Ihre Angaben (Auszug):',
        '  Komponenten: ' . $components,
        '  Objekt:      ' . ($in['building'] ?? '-'),
        '  Standort:    ' . ($in['location'] ?? '-'),
        '',
        '────────────────────────────',
        'Technik- & Instandsetzungs GmbH',
        'Quitzower Damm 15, 19348 Sükow',
        'Tel.: +49 3876 612474',
    ]);
    send_mail([
        'to'      => $in['email'],
        'subject' => 'Ihre Angebotsanfrage bei Technik- & Instandsetzungs GmbH',
        'body'    => $body,
    ]);
}

// HTTP entry
if (PHP_SAPI !== 'cli' && !defined('TI_TEST')) {
    handle_preflight_if_options($_SERVER['HTTP_ORIGIN'] ?? null, config('cors_origins'));
    emit_cors_headers(cors_headers_for($_SERVER['HTTP_ORIGIN'] ?? null, config('cors_origins')));

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        emit_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
        exit;
    }

    // Convert components[] form repetition: PHP gives an array natively because the field name has [].
    $input = $_POST;
    if (isset($input['components']) && !is_array($input['components'])) {
        $input['components'] = [$input['components']];
    }

    $result = angebot_handle($input, $_FILES, pack_ip(client_ip()),
        (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    emit_json($result['status'], $result['body']);
}
```

- [ ] **Step 4: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/AngebotEndpointTest.php`
Expected: 5 tests, OK.

- [ ] **Step 5: Run the full test suite to check nothing regressed**

Run: `cd backend && vendor/bin/phpunit`
Expected: all tests across all files pass.

- [ ] **Step 6: Commit**

```bash
git add backend/public/api/angebot.php backend/tests/AngebotEndpointTest.php
git commit -m "feat(backend): angebot endpoint with file uploads"
```

---

## Phase 3 — Admin (auth + views)

### Task 14: Auth helpers + session (TDD)

**Files:**
- Create: `backend/src/auth.php`
- Create: `backend/src/csrf.php`
- Create: `backend/tests/AuthTest.php`
- Create: `backend/tests/CsrfTest.php`

- [ ] **Step 1: Write tests/AuthTest.php**

```php
<?php
namespace Ti\Tests;

class AuthTest extends TestCase
{
    public function testCreateAndVerifyUser(): void
    {
        create_user('admin', 'correct horse battery staple');
        $this->assertTrue(verify_login('admin', 'correct horse battery staple'));
        $this->assertFalse(verify_login('admin', 'wrong'));
    }

    public function testLockoutAfterFailures(): void
    {
        create_user('admin', 'right');
        for ($i = 0; $i < 5; $i++) verify_login('admin', 'wrong');
        $this->assertTrue(is_account_locked('admin'));
        // Still locked even with right password
        $this->assertFalse(verify_login('admin', 'right'));
    }

    public function testLockoutExpires(): void
    {
        create_user('admin', 'right');
        for ($i = 0; $i < 5; $i++) verify_login('admin', 'wrong');
        // Manually expire the lock
        db()->exec("UPDATE users SET locked_until = '2000-01-01 00:00:00' WHERE username='admin'");
        $this->assertTrue(verify_login('admin', 'right'));
    }
}
```

- [ ] **Step 2: Write tests/CsrfTest.php**

```php
<?php
namespace Ti\Tests;

class CsrfTest extends \PHPUnit\Framework\TestCase
{
    public function testGenerateAndCheck(): void
    {
        $store = [];
        $token = csrf_issue($store);
        $this->assertTrue(csrf_verify($token, $store));
    }

    public function testWrongTokenFails(): void
    {
        $store = [];
        csrf_issue($store);
        $this->assertFalse(csrf_verify('wrong', $store));
    }

    public function testEmptyStoreFails(): void
    {
        $this->assertFalse(csrf_verify('whatever', []));
    }
}
```

- [ ] **Step 3: Run tests (expect undefined)**

Run: `cd backend && vendor/bin/phpunit tests/AuthTest.php tests/CsrfTest.php`
Expected: errors.

- [ ] **Step 4: Implement src/auth.php**

```php
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
```

- [ ] **Step 5: Implement src/csrf.php**

```php
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
```

- [ ] **Step 6: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/AuthTest.php tests/CsrfTest.php`
Expected: 6 tests, OK.

- [ ] **Step 7: Commit**

```bash
git add backend/src/auth.php backend/src/csrf.php backend/tests/AuthTest.php backend/tests/CsrfTest.php
git commit -m "feat(backend): auth + CSRF helpers"
```

### Task 15: Admin login + logout pages

**Files:**
- Create: `backend/public/admin/login.php`
- Create: `backend/public/admin/logout.php`
- Create: `backend/public/admin/admin.css`

Manual testing only (PHP session cookie behaviour is awkward to unit-test); we'll smoke-test in the browser during Phase 7.

- [ ] **Step 1: Write admin/login.php**

```php
<?php
// backend/public/admin/login.php
require_once __DIR__ . '/../../src/bootstrap.php';

start_admin_session();
if (!empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = (string)($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    if (verify_login($u, $p)) {
        $stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$u]);
        $uid = (int)$stmt->fetchColumn();
        login_user($uid, $u);
        header('Location: /index.php');
        exit;
    }
    $error = 'Falsche Anmeldedaten oder Konto gesperrt.';
}
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Anmelden — TI Admin</title>
  <link rel="stylesheet" href="/admin.css">
</head>
<body class="login-page">
  <main class="login-card">
    <h1>TI Admin</h1>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post" autocomplete="off">
      <label>Benutzername
        <input type="text" name="username" required autofocus>
      </label>
      <label>Passwort
        <input type="password" name="password" required>
      </label>
      <button type="submit">Anmelden</button>
    </form>
  </main>
</body>
</html>
```

- [ ] **Step 2: Write admin/logout.php**

```php
<?php
require_once __DIR__ . '/../../src/bootstrap.php';
logout_user();
header('Location: /login.php');
exit;
```

- [ ] **Step 3: Write admin/admin.css**

```css
/* backend/public/admin/admin.css — utilitarian, brand-aligned */
:root {
  --bg: #f6f7f9;
  --surface: #ffffff;
  --border: #d8dde3;
  --text: #1a2230;
  --muted: #5a6473;
  --accent: #0f5b3a;      /* TI green */
  --accent-fg: #ffffff;
  --warn: #d2691e;
  --danger: #c0392b;
  --success: #1e7e34;
}
* { box-sizing: border-box; }
body { margin: 0; font: 14px/1.5 system-ui, sans-serif; color: var(--text); background: var(--bg); }
a { color: var(--accent); text-decoration: none; }
a:hover { text-decoration: underline; }
header.admin-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 24px; background: var(--surface); border-bottom: 1px solid var(--border);
}
header.admin-header h1 { margin: 0; font-size: 18px; }
main.admin-main { padding: 24px; max-width: 1200px; margin: 0 auto; }
.tabs { display: flex; gap: 8px; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
.tabs a { padding: 10px 16px; border: 1px solid transparent; border-bottom: none;
          border-radius: 4px 4px 0 0; color: var(--muted); }
.tabs a.active { background: var(--surface); border-color: var(--border); color: var(--text); }
.filters { display: flex; gap: 12px; margin-bottom: 12px; align-items: end; }
.filters label { display: flex; flex-direction: column; font-size: 12px; color: var(--muted); }
.filters select, .filters input { padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; }
table.list { width: 100%; border-collapse: collapse; background: var(--surface);
             border: 1px solid var(--border); border-radius: 4px; overflow: hidden; }
table.list th, table.list td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); }
table.list th { background: #eef1f5; font-weight: 600; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
.badge.new        { background: #cfe7d8; color: #0f5b3a; }
.badge.in_progress{ background: #fde9b3; color: #6a4b00; }
.badge.handled    { background: #e2e6eb; color: #444; }
.badge.spam       { background: #fbd1cd; color: #7a1f15; }
.detail { background: var(--surface); padding: 24px; border: 1px solid var(--border);
          border-radius: 4px; max-width: 900px; }
.detail dl { display: grid; grid-template-columns: 200px 1fr; gap: 8px 16px; margin: 0; }
.detail dt { color: var(--muted); }
.detail dd { margin: 0; }
.actions { display: flex; gap: 12px; margin-top: 24px; }
.actions button, .actions a.button {
  padding: 8px 16px; border: 1px solid var(--border); background: var(--surface);
  border-radius: 4px; cursor: pointer; font: inherit;
}
.actions .primary { background: var(--accent); color: var(--accent-fg); border-color: var(--accent); }
.actions .danger { color: var(--danger); border-color: var(--danger); }
.attachments { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
.attachment { width: 140px; padding: 8px; border: 1px solid var(--border); border-radius: 4px;
              background: var(--surface); text-align: center; }
.attachment img { max-width: 100%; height: 100px; object-fit: cover; }
.error { color: var(--danger); }
.success { color: var(--success); }
.login-page { display: grid; place-items: center; min-height: 100vh; }
.login-card { background: var(--surface); padding: 32px; border: 1px solid var(--border);
              border-radius: 8px; min-width: 320px; }
.login-card h1 { margin-top: 0; }
.login-card label { display: block; margin-bottom: 12px; font-size: 12px; color: var(--muted); }
.login-card input { display: block; width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px; }
.login-card button { width: 100%; padding: 10px; background: var(--accent); color: var(--accent-fg);
                     border: none; border-radius: 4px; cursor: pointer; font: inherit; }
```

- [ ] **Step 4: Smoke-test (manual)**

Spin up PHP's built-in server (one-off, for local testing):
```bash
cd backend/public/admin && php -S 127.0.0.1:8080 -t .
```
In another terminal:
```bash
mysql -u root -p ti_backend_test -e "INSERT INTO users (username, password_hash) VALUES ('admin', '$(php -r "echo password_hash('test123', PASSWORD_BCRYPT);")');"
```
Open `http://127.0.0.1:8080/login.php`, log in with `admin / test123`. Confirm redirect to `/index.php` (will 404 until next task, but the session cookie should be set).

- [ ] **Step 5: Commit**

```bash
git add backend/public/admin/login.php backend/public/admin/logout.php backend/public/admin/admin.css
git commit -m "feat(admin): login/logout pages + base CSS"
```

### Task 16: Admin list view

**Files:**
- Create: `backend/public/admin/index.php`

- [ ] **Step 1: Write admin/index.php**

```php
<?php
// backend/public/admin/index.php
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

$tab = $_GET['tab'] ?? 'angebot';
$status = $_GET['status'] ?? 'new';
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($status !== 'all') {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}
if ($q !== '') {
    $where[] = '(name LIKE :q OR email LIKE :q OR phone LIKE :q OR message LIKE :q)';
    $params[':q'] = "%{$q}%";
}
// 'contact_requests' has 'contact' column instead of 'email'/'phone'; substitute for that tab
if ($tab === 'contact') {
    if ($q !== '') {
        // rewrite
        $where = array_filter($where, fn($w) => !str_contains($w, 'email LIKE'));
        $where[] = '(name LIKE :q OR contact LIKE :q OR message LIKE :q)';
    }
    $table = 'contact_requests';
    $cols = 'id, created_at, name, contact AS contact_info, NULL AS phone, status';
} else {
    $table = 'angebot_requests';
    $cols = 'id, created_at, name, email AS contact_info, phone, status';
}

$sql = "SELECT {$cols} FROM {$table}";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = [];
foreach (['contact_requests' => 'contact', 'angebot_requests' => 'angebot'] as $t => $key) {
    $counts[$key] = (int) db()->query("SELECT COUNT(*) FROM {$t} WHERE status='new'")->fetchColumn();
}
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Anfragen — TI Admin</title>
  <link rel="stylesheet" href="/admin.css">
</head>
<body>
  <header class="admin-header">
    <h1>TI Admin</h1>
    <div>
      <span>Angemeldet als <?= htmlspecialchars($_SESSION['username']) ?></span>
      &nbsp;·&nbsp;
      <a href="/logout.php">Abmelden</a>
    </div>
  </header>
  <main class="admin-main">
    <nav class="tabs">
      <a class="<?= $tab==='angebot'?'active':'' ?>" href="?tab=angebot">Angebot (<?= $counts['angebot'] ?> neu)</a>
      <a class="<?= $tab==='contact'?'active':'' ?>" href="?tab=contact">Kontakt (<?= $counts['contact'] ?> neu)</a>
    </nav>

    <form class="filters" method="get">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <label>Status
        <select name="status">
          <?php foreach (['new','in_progress','handled','spam','all'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Suche
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Name, Mail, Telefon...">
      </label>
      <button type="submit">Filtern</button>
    </form>

    <table class="list">
      <thead><tr><th>ID</th><th>Datum</th><th>Name</th><th>Kontakt</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><a href="/detail.php?type=<?= $tab ?>&id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td>
              <?= htmlspecialchars($r['contact_info']) ?>
              <?php if (!empty($r['phone'])): ?><br><small><?= htmlspecialchars($r['phone']) ?></small><?php endif; ?>
            </td>
            <td><span class="badge <?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="5" style="text-align:center; color: var(--muted); padding: 24px;">Keine Einträge.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
</body>
</html>
```

- [ ] **Step 2: Smoke-test (manual)**

With the PHP built-in server still running from Task 15, log in and open `http://127.0.0.1:8080/index.php`. Insert a sample row via `mysql` and verify it appears:
```bash
mysql -u root -p ti_backend_test -e "INSERT INTO angebot_requests (name, phone, email, components) VALUES ('Test', '123', 't@e.de', 'Photovoltaik');"
```
Refresh — confirm the row is listed; tab counts update; filter dropdown works.

- [ ] **Step 3: Commit**

```bash
git add backend/public/admin/index.php
git commit -m "feat(admin): list view with tabs, filters, status badges"
```

### Task 17: Admin detail view + action endpoint

**Files:**
- Create: `backend/public/admin/detail.php`
- Create: `backend/public/admin/action.php`

- [ ] **Step 1: Write admin/detail.php**

```php
<?php
// backend/public/admin/detail.php
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

$type = $_GET['type'] ?? 'angebot';
$id = (int)($_GET['id'] ?? 0);
$table = $type === 'contact' ? 'contact_requests' : 'angebot_requests';

$stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo 'Nicht gefunden';
    exit;
}

$attachments = [];
if ($type === 'angebot') {
    $a = db()->prepare("SELECT * FROM angebot_attachments WHERE angebot_id = ? ORDER BY id");
    $a->execute([$id]);
    $attachments = $a->fetchAll();
}

$csrf = csrf_token_from_session();
$ipDisplay = $row['ip_address'] ? @inet_ntop($row['ip_address']) : '—';
$msg = $_GET['msg'] ?? null;
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Anfrage #<?= $id ?> — TI Admin</title>
  <link rel="stylesheet" href="/admin.css">
</head>
<body>
  <header class="admin-header">
    <h1>TI Admin</h1>
    <div><a href="/index.php?tab=<?= htmlspecialchars($type) ?>">← Übersicht</a> · <a href="/logout.php">Abmelden</a></div>
  </header>
  <main class="admin-main">
    <?php if ($msg === 'saved'): ?><p class="success">Gespeichert.</p><?php endif; ?>
    <?php if ($msg === 'deleted'): ?><p class="success">Eintrag gelöscht.</p><?php endif; ?>

    <div class="detail">
      <h2>#<?= $id ?> — <?= htmlspecialchars($row['name']) ?>
        <span class="badge <?= htmlspecialchars($row['status']) ?>" style="margin-left:8px"><?= htmlspecialchars($row['status']) ?></span>
      </h2>
      <dl>
        <?php if ($type === 'angebot'): ?>
          <dt>Telefon</dt><dd><?= htmlspecialchars($row['phone']) ?></dd>
          <dt>E-Mail</dt><dd><a href="mailto:<?= htmlspecialchars($row['email']) ?>"><?= htmlspecialchars($row['email']) ?></a></dd>
          <dt>Komponenten</dt><dd><?= htmlspecialchars($row['components']) ?></dd>
          <dt>Objekt</dt><dd><?= htmlspecialchars($row['building'] ?? '—') ?></dd>
          <dt>Standort/PLZ</dt><dd><?= htmlspecialchars($row['location'] ?? '—') ?></dd>
          <dt>Dachform</dt><dd><?= htmlspecialchars($row['roof'] ?? '—') ?></dd>
          <dt>Nutzung</dt><dd><?= htmlspecialchars($row['usage_profile'] ?? '—') ?></dd>
          <dt>Verbrauch</dt><dd><?= htmlspecialchars($row['consumption'] ?? '—') ?></dd>
          <dt>Zeitraum</dt><dd><?= htmlspecialchars($row['timeline'] ?? '—') ?></dd>
          <dt>Details</dt><dd style="white-space:pre-wrap"><?= htmlspecialchars($row['details'] ?? '—') ?></dd>
        <?php else: ?>
          <dt>Kontakt</dt><dd><?= htmlspecialchars($row['contact']) ?></dd>
          <dt>Thema</dt><dd><?= htmlspecialchars($row['topic'] ?? '—') ?></dd>
          <dt>Nachricht</dt><dd style="white-space:pre-wrap"><?= htmlspecialchars($row['message']) ?></dd>
        <?php endif; ?>
        <dt>Eingegangen</dt><dd><?= htmlspecialchars(date('d.m.Y H:i', strtotime($row['created_at']))) ?></dd>
        <dt>IP</dt><dd><?= htmlspecialchars($ipDisplay) ?></dd>
        <dt>Browser</dt><dd style="font-size:11px; color:var(--muted)"><?= htmlspecialchars($row['user_agent'] ?? '—') ?></dd>
      </dl>

      <?php if ($attachments): ?>
        <h3>Anhänge</h3>
        <div class="attachments">
          <?php foreach ($attachments as $a): ?>
            <div class="attachment">
              <?php if (str_starts_with($a['mime_type'], 'image/')): ?>
                <a href="/attachment.php?id=<?= (int)$a['id'] ?>" target="_blank">
                  <img src="/attachment.php?id=<?= (int)$a['id'] ?>&inline=1" alt="">
                </a>
              <?php else: ?>
                <div style="font-size:32px">📄</div>
              <?php endif; ?>
              <div><a href="/attachment.php?id=<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['original_name']) ?></a></div>
              <small><?= round($a['size_bytes']/1024) ?> KB</small>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h3>Status & Notizen</h3>
      <form method="post" action="/action.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="op" value="save">
        <label>Status
          <select name="status">
            <?php foreach (['new','in_progress','handled','spam'] as $s): ?>
              <option value="<?= $s ?>" <?= $row['status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label style="display:block; margin-top:12px;">Interne Notizen
          <textarea name="notes" rows="5" style="width:100%"><?= htmlspecialchars($row['notes'] ?? '') ?></textarea>
        </label>

        <div class="actions">
          <button class="primary" type="submit">Speichern</button>
          <a class="button" href="mailto:<?= htmlspecialchars($type==='angebot'?$row['email']:$row['contact']) ?>?subject=<?= rawurlencode('Re: Ihre Anfrage #'.$id) ?>">Per E-Mail antworten</a>
          <button class="danger" formaction="/action.php" formmethod="post" name="op" value="delete"
                  onclick="return confirm('Eintrag und Anhänge endgültig löschen?');">Löschen (DSGVO)</button>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
```

- [ ] **Step 2: Write admin/action.php**

```php
<?php
// backend/public/admin/action.php — handles save / delete from the detail view.
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

$csrf = $_POST['csrf'] ?? '';
if (!csrf_check_from_session($csrf)) {
    http_response_code(403);
    exit('CSRF check failed.');
}

$type = $_POST['type'] ?? 'angebot';
$id = (int)($_POST['id'] ?? 0);
$op = $_POST['op'] ?? '';
$table = $type === 'contact' ? 'contact_requests' : 'angebot_requests';

if ($op === 'save') {
    $status = $_POST['status'] ?? 'new';
    if (!in_array($status, ['new','in_progress','handled','spam'], true)) {
        http_response_code(400); exit('Bad status.');
    }
    $notes = (string)($_POST['notes'] ?? '');
    $handledAt = in_array($status, ['handled','spam'], true) ? date('Y-m-d H:i:s') : null;

    $stmt = db()->prepare(
        "UPDATE {$table} SET status = ?, notes = ?, handled_at = COALESCE(?, handled_at) WHERE id = ?"
    );
    $stmt->execute([$status, $notes, $handledAt, $id]);

    header("Location: /detail.php?type={$type}&id={$id}&msg=saved");
    exit;
}

if ($op === 'delete') {
    if ($type === 'angebot') {
        $att = db()->prepare("SELECT angebot_id, stored_name FROM angebot_attachments WHERE angebot_id = ?");
        $att->execute([$id]);
        foreach ($att->fetchAll() as $a) {
            @unlink(config('uploads.dir') . '/' . $a['angebot_id'] . '/' . $a['stored_name']);
        }
        @rmdir(config('uploads.dir') . '/' . $id);
    }
    db()->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
    header("Location: /index.php?tab={$type}&msg=deleted");
    exit;
}

http_response_code(400);
exit('Unknown operation.');
```

- [ ] **Step 3: Smoke-test (manual)**

In the browser, open a detail page (e.g. `/detail.php?type=angebot&id=1`), change status, save, confirm the green "Gespeichert" banner and that the badge updates. Test the delete confirmation prompt with a throw-away row.

- [ ] **Step 4: Commit**

```bash
git add backend/public/admin/detail.php backend/public/admin/action.php
git commit -m "feat(admin): detail view + save/delete actions"
```

### Task 18: Admin attachment download

**Files:**
- Create: `backend/public/admin/attachment.php`

- [ ] **Step 1: Write admin/attachment.php**

```php
<?php
// backend/public/admin/attachment.php
require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$inline = !empty($_GET['inline']);

$stmt = db()->prepare("SELECT * FROM angebot_attachments WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); exit('Nicht gefunden'); }

$path = config('uploads.dir') . '/' . $row['angebot_id'] . '/' . $row['stored_name'];
if (!is_file($path)) { http_response_code(404); exit('Datei fehlt'); }

header('Content-Type: ' . $row['mime_type']);
header('Content-Length: ' . filesize($path));
$disposition = $inline ? 'inline' : 'attachment';
$fname = preg_replace('/[\r\n"]/', '_', $row['original_name']);
header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $fname));
header('X-Content-Type-Options: nosniff');
readfile($path);
```

- [ ] **Step 2: Smoke-test (manual)**

From the detail page, click an image thumbnail (loads inline) and the filename link (downloads). Hit `/attachment.php?id=X` while logged out — should redirect to login.

- [ ] **Step 3: Commit**

```bash
git add backend/public/admin/attachment.php
git commit -m "feat(admin): session-protected attachment download"
```

---

## Phase 4 — Cron + setup scripts

### Task 19: setup-admin.php (interactive one-shot)

**Files:**
- Create: `backend/cron/setup-admin.php`

- [ ] **Step 1: Write cron/setup-admin.php**

```php
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
```

- [ ] **Step 2: Smoke-test (manual)**

```bash
cd backend && php cron/setup-admin.php
# Enter a username and password, verify message; check users table
mysql -u root -p ti_backend_test -e "SELECT username, locked_until FROM users;"
```

- [ ] **Step 3: Commit**

```bash
git add backend/cron/setup-admin.php
git commit -m "feat(cron): one-time setup-admin script"
```

### Task 20: cleanup_rate_limit cron

**Files:**
- Create: `backend/cron/cleanup_rate_limit.php`

- [ ] **Step 1: Write cron/cleanup_rate_limit.php**

```php
<?php
// backend/cron/cleanup_rate_limit.php — Plesk hourly cron.
require_once __DIR__ . '/../src/bootstrap.php';
cleanup_rate_limit('PT2H');
echo "rate_limit pruned older than 2h\n";
```

- [ ] **Step 2: Verify it runs without error**

```bash
cd backend && php cron/cleanup_rate_limit.php
```

- [ ] **Step 3: Commit**

```bash
git add backend/cron/cleanup_rate_limit.php
git commit -m "feat(cron): hourly rate_limit cleanup"
```

### Task 21: Retention cron (TDD)

**Files:**
- Create: `backend/src/retention.php`
- Create: `backend/cron/retention.php`
- Create: `backend/tests/RetentionTest.php`

- [ ] **Step 1: Write tests/RetentionTest.php**

```php
<?php
namespace Ti\Tests;

class RetentionTest extends TestCase
{
    public function testHandledOlderThan12MonthsDeleted(): void
    {
        db()->exec("INSERT INTO contact_requests (name, contact, message, status, handled_at, created_at)
                    VALUES ('old', 'a@b.de', '.', 'handled', '2024-01-01', '2024-01-01')");
        db()->exec("INSERT INTO contact_requests (name, contact, message, status, handled_at)
                    VALUES ('recent', 'a@b.de', '.', 'handled', NOW())");

        retention_apply();

        $rows = db()->query("SELECT name FROM contact_requests")->fetchAll();
        $names = array_column($rows, 'name');
        $this->assertContains('recent', $names);
        $this->assertNotContains('old', $names);
    }

    public function testIpAnonymizedAfter30Days(): void
    {
        $packed = pack_ip('192.0.2.123');
        $stmt = db()->prepare("INSERT INTO contact_requests (name, contact, message, ip_address, created_at)
                               VALUES ('u', 'a@b.de', '.', ?, DATE_SUB(NOW(), INTERVAL 31 DAY))");
        $stmt->execute([$packed]);

        retention_apply();

        $ip = db()->query("SELECT ip_address FROM contact_requests WHERE name='u'")->fetchColumn();
        $this->assertSame(inet_pton('192.0.2.0'), $ip);
    }

    public function testAttachmentsDeletedWithAngebot(): void
    {
        $dir = sys_get_temp_dir() . '/ti-ret-' . uniqid();
        mkdir("{$dir}/42", 0700, true);
        $p = "{$dir}/42/abc.jpg";
        file_put_contents($p, 'x');
        $GLOBALS['__ti_retention_upload_dir'] = $dir;

        db()->exec("INSERT INTO angebot_requests (id, name, phone, email, components, status, handled_at, created_at)
                    VALUES (42, 'A', '1', 'a@b.de', 'PV', 'handled', '2024-01-01', '2024-01-01')");
        db()->exec("INSERT INTO angebot_attachments (angebot_id, stored_name, original_name, mime_type, size_bytes)
                    VALUES (42, 'abc.jpg', 'photo.jpg', 'image/jpeg', 1)");

        retention_apply();

        $this->assertFileDoesNotExist($p);
        $this->assertSame(0, (int)db()->query("SELECT COUNT(*) FROM angebot_requests WHERE id=42")->fetchColumn());
    }
}
```

- [ ] **Step 2: Run test (expect undefined)**

Run: `cd backend && vendor/bin/phpunit tests/RetentionTest.php`
Expected: errors.

- [ ] **Step 3: Implement src/retention.php**

```php
<?php
// backend/src/retention.php

function retention_apply(): void
{
    _retention_purge_old_handled();
    _retention_anonymize_ips();
}

function _retention_purge_old_handled(): void
{
    // Find Angebot rows about to be deleted; remove their files first
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
            // Only update if it changed (avoid pointless writes)
            if ($anon !== $r['ip_address']) {
                $upd->execute([$anon, $r['id']]);
            }
        }
    }
}
```

- [ ] **Step 4: Implement cron/retention.php**

```php
<?php
// backend/cron/retention.php — Plesk daily cron at 03:00.
require_once __DIR__ . '/../src/bootstrap.php';
retention_apply();
echo "retention applied: " . date('c') . "\n";
```

- [ ] **Step 5: Add `src/retention.php` to composer autoload**

Edit `backend/composer.json`'s `autoload.files` array to include `"src/retention.php"`, then run:
```bash
cd backend && composer dump-autoload
```

- [ ] **Step 6: Run tests until green**

Run: `cd backend && vendor/bin/phpunit tests/RetentionTest.php`
Expected: 3 tests, OK.

- [ ] **Step 7: Run the full suite**

Run: `cd backend && vendor/bin/phpunit`
Expected: all tests pass.

- [ ] **Step 8: Commit**

```bash
git add backend/src/retention.php backend/cron/retention.php backend/tests/RetentionTest.php backend/composer.json backend/composer.lock
git commit -m "feat(cron): GDPR retention + IP anonymization"
```

### Task 22: mail_health cron

**Files:**
- Create: `backend/cron/mail_health.php`

- [ ] **Step 1: Write cron/mail_health.php**

```php
<?php
// backend/cron/mail_health.php — Plesk daily cron 08:00.
// If any mail_errors.log entries from the past 24h exist, email a summary.
require_once __DIR__ . '/../src/bootstrap.php';

$log = config('logs_dir') . '/mail_errors.log';
if (!is_file($log)) { echo "no log file\n"; exit; }

$cutoff = time() - 24*3600;
$lines = [];
foreach (new SplFileObject($log) as $line) {
    if (preg_match('/^\[(\S+)\]/', (string)$line, $m)) {
        if (strtotime($m[1]) >= $cutoff) {
            $lines[] = rtrim($line, "\r\n");
        }
    }
}

if (!$lines) { echo "no recent failures\n"; exit; }

$body = "Mail-Zustellfehler in den letzten 24h:\n\n" . implode("\n", $lines)
      . "\n\n(Quelle: backend/storage/logs/mail_errors.log)\n";
send_mail([
    'to'      => config('mail.to_address'),
    'subject' => 'TI Backend — Mail-Zustellfehler (' . count($lines) . ')',
    'body'    => $body,
]);
echo "summary sent: " . count($lines) . " failures\n";
```

- [ ] **Step 2: Smoke-test (manual)**

Write a fake line to the log and run the cron:
```bash
mkdir -p backend/storage/logs
echo "[$(date -Iseconds)] mail failure to=test@example.de subject=Manueller Test" >> backend/storage/logs/mail_errors.log
cd backend && php cron/mail_health.php
```
Expected: prints "summary sent: 1 failures" (and would actually send if `mail()` works locally; on a dev machine it may print nothing if `mail()` isn't configured — that's OK, this is checked on the live server).

- [ ] **Step 3: Commit**

```bash
git add backend/cron/mail_health.php
git commit -m "feat(cron): daily mail-failure summary"
```

---

## Phase 5 — Frontend integration

### Task 23: Add visually-hidden + form-status helper styles

**Files:**
- Modify: `styles.css` (append to the end)

- [ ] **Step 1: Append CSS additions to styles.css**

```css
/* ===== Form backend additions ===== */

.visually-hidden {
  position: absolute !important;
  width: 1px; height: 1px;
  padding: 0; margin: -1px;
  overflow: hidden;
  clip: rect(0,0,0,0);
  white-space: nowrap;
  border: 0;
}

.form-status.is-success { color: #1e7e34; font-weight: 600; }
.form-status.is-error   { color: #c0392b; font-weight: 600; }

.has-error input,
.has-error textarea,
.has-error select {
  border-color: #c0392b !important;
  box-shadow: 0 0 0 2px rgba(192, 57, 43, 0.15);
}

.form-noscript {
  background: #fff8e1;
  border: 1px solid #f1c84b;
  border-left: 4px solid #c89500;
  padding: 12px 16px;
  border-radius: 4px;
  margin: 12px 0;
  color: #4a3a00;
}

.offer-file-list {
  list-style: none; padding: 0; margin: 8px 0 0;
  display: flex; flex-direction: column; gap: 6px;
}
.offer-file-list li {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; padding: 8px 12px; border: 1px solid var(--border, #e2e6eb);
  border-radius: 6px; background: #f9fafb; font-size: 14px;
}
.offer-file-list li small { color: #6b7280; }
.offer-file-list button.remove {
  background: transparent; border: none; cursor: pointer;
  color: #c0392b; font-size: 18px; line-height: 1; padding: 0 4px;
}
```

- [ ] **Step 2: Commit**

```bash
git add styles.css
git commit -m "feat(frontend): styles for form status, errors, noscript, file list"
```

### Task 24: Wire honeypot + noscript into contact form

**Files:**
- Modify: `index.html` (around the `data-contact-form` block; existing form is roughly lines 295–330)

- [ ] **Step 1: Add honeypot + noscript inside the contact form**

Find the contact form `<form class="contact-form" data-contact-form>` block. Just before the closing `</form>`, insert:

```html
<input
  type="text"
  name="website"
  tabindex="-1"
  autocomplete="off"
  class="visually-hidden"
  aria-hidden="true">
<noscript>
  <p class="form-noscript">
    <strong>Hinweis:</strong> Für das Senden dieses Formulars benötigen wir JavaScript.
    Sie erreichen uns auch direkt unter
    <a href="tel:+493876612474">+49 3876 612474</a>
    oder per E-Mail an
    <a href="mailto:info@technik-prignitz.de">info@technik-prignitz.de</a>.
  </p>
</noscript>
```

- [ ] **Step 2: Manual sanity check**

Open `index.html` in a browser, view the contact section, then disable JS via DevTools and reload — confirm the warning box appears.

- [ ] **Step 3: Commit**

```bash
git add index.html
git commit -m "feat(frontend): honeypot + noscript fallback in contact form"
```

### Task 25: Wire honeypot + noscript + file input into Angebot form

**Files:**
- Modify: `angebot/index.html`

- [ ] **Step 1: Replace the existing "photos" checkbox with a file input + list**

Locate the Angebot form's "Details" step (around line 200–217 in the current file). Replace the `<label class="consent">` block containing `<input type="checkbox" name="photos" ...>` with:

```html
<label class="offer-field">
  Fotos, Stromrechnung oder vorhandene Angebote (optional)
  <input
    type="file"
    name="files"
    multiple
    accept="image/jpeg,image/png,image/heic,image/webp,application/pdf"
    data-offer-files>
  <small>JPG, PNG, HEIC, WEBP oder PDF. Max. 10 Dateien, je max. 10 MB.</small>
</label>
<ul class="offer-file-list" data-offer-file-list></ul>
```

- [ ] **Step 2: Add honeypot + noscript before the closing `</form>`**

```html
<input
  type="text"
  name="website"
  tabindex="-1"
  autocomplete="off"
  class="visually-hidden"
  aria-hidden="true">
<noscript>
  <p class="form-noscript">
    <strong>Hinweis:</strong> Für das Senden dieses Formulars benötigen wir JavaScript.
    Sie erreichen uns auch direkt unter
    <a href="tel:+493876612474">+49 3876 612474</a>
    oder per E-Mail an
    <a href="mailto:info@technik-prignitz.de">info@technik-prignitz.de</a>.
  </p>
</noscript>
```

- [ ] **Step 3: Manual sanity check**

Open `angebot/index.html`, navigate to the Details step. Confirm the file input shows up and the noscript box appears with JS disabled.

- [ ] **Step 4: Commit**

```bash
git add angebot/index.html
git commit -m "feat(frontend): file input, honeypot, noscript on Angebot form"
```

### Task 26: Rewrite script.js to use fetch()

**Files:**
- Modify: `script.js`

- [ ] **Step 1: Replace the mailto submit handlers with fetch-based logic**

Replace the **entire content of `script.js`** with:

```js
// Ti-page frontend
const API_BASE = "https://api.technik-prignitz.de";

const MAX_FILES = 10;
const MAX_FILE_BYTES = 10 * 1024 * 1024;
const MAX_TOTAL_BYTES = 50 * 1024 * 1024;

const header = document.querySelector("[data-header]");
const nav = document.querySelector("[data-nav]");
const navToggle = document.querySelector("[data-nav-toggle]");
const contactForm = document.querySelector("[data-contact-form]");
const contactStatus = document.querySelector("[data-form-status]");
const offerForm = document.querySelector("[data-offer-form]");

const setHeaderState = () => {
  const alwaysSolid = document.body.classList.contains("offer-page");
  header?.classList.toggle("is-scrolled", alwaysSolid || window.scrollY > 10);
};

const closeNav = () => {
  nav?.classList.remove("is-open");
  header?.classList.remove("is-open");
  document.body.classList.remove("nav-open");
  navToggle?.setAttribute("aria-expanded", "false");
};

navToggle?.addEventListener("click", () => {
  const isOpen = nav?.classList.toggle("is-open");
  header?.classList.toggle("is-open", Boolean(isOpen));
  document.body.classList.toggle("nav-open", Boolean(isOpen));
  navToggle.setAttribute("aria-expanded", String(Boolean(isOpen)));
});
nav?.addEventListener("click", (event) => {
  if (event.target instanceof HTMLAnchorElement) closeNav();
});
document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeNav(); });
window.addEventListener("scroll", setHeaderState, { passive: true });
setHeaderState();

// ---------- Helpers ----------

const clearFieldErrors = (form) => {
  form.querySelectorAll(".has-error").forEach(el => el.classList.remove("has-error"));
};

const showFieldErrors = (form, fields) => {
  clearFieldErrors(form);
  for (const [name, _msg] of Object.entries(fields || {})) {
    const input = form.querySelector(`[name="${name}"], [name="${name}[]"]`);
    if (input) {
      const wrapper = input.closest("label") || input.parentElement;
      wrapper?.classList.add("has-error");
    }
  }
};

const setStatus = (el, text, kind) => {
  if (!el) return;
  el.textContent = text;
  el.classList.remove("is-success", "is-error");
  if (kind) el.classList.add(`is-${kind}`);
};

// ---------- Contact form ----------

contactForm?.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!contactForm.checkValidity()) { contactForm.reportValidity(); return; }

  clearFieldErrors(contactForm);
  const button = contactForm.querySelector('button[type="submit"]');
  button.disabled = true;
  setStatus(contactStatus, "Wird gesendet…", null);

  const fd = new FormData(contactForm);
  const payload = {
    name:    String(fd.get("name") || "").trim(),
    contact: String(fd.get("contact") || "").trim(),
    topic:   String(fd.get("topic") || "").trim(),
    message: String(fd.get("message") || "").trim(),
    website: String(fd.get("website") || ""),
  };

  try {
    const res = await fetch(`${API_BASE}/contact.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    handleResponse(res, data, contactForm, contactStatus,
      "Vielen Dank! Wir melden uns innerhalb von 2 Werktagen.");
  } catch (_err) {
    setStatus(contactStatus,
      "Etwas ist schiefgelaufen. Bitte erneut versuchen oder anrufen: +49 3876 612474.",
      "error");
  } finally {
    button.disabled = false;
  }
});

// ---------- Angebot form (multi-step + uploads) ----------

if (offerForm) {
  const steps = Array.from(offerForm.querySelectorAll("[data-offer-step]"));
  const progress = offerForm.querySelector("[data-offer-progress]");
  const stepLabel = offerForm.querySelector("[data-offer-step-label]");
  const backButton = offerForm.querySelector("[data-offer-back]");
  const nextButton = offerForm.querySelector("[data-offer-next]");
  const submitButton = offerForm.querySelector("[data-offer-submit]");
  const offerStatus = offerForm.querySelector("[data-offer-status]");
  const fileInput = offerForm.querySelector("[data-offer-files]");
  const fileList = offerForm.querySelector("[data-offer-file-list]");
  let currentStep = 0;
  let selectedFiles = [];

  const setOfferStep = (index) => {
    currentStep = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((s, i) => s.classList.toggle("is-active", i === currentStep));
    if (progress) progress.style.width = `${((currentStep + 1) / steps.length) * 100}%`;
    if (stepLabel) stepLabel.textContent = `Schritt ${currentStep + 1} von ${steps.length}`;
    backButton?.toggleAttribute("disabled", currentStep === 0);
    nextButton?.classList.toggle("is-hidden", currentStep === steps.length - 1);
    submitButton?.classList.toggle("is-hidden", currentStep !== steps.length - 1);
  };

  const setStepError = (msg = "") => {
    const err = steps[currentStep]?.querySelector("[data-step-error]");
    if (err) err.textContent = msg;
  };

  const validateOfferStep = () => {
    const step = steps[currentStep];
    if (!step) return true;
    setStepError();

    const checkboxNames = new Set(
      Array.from(step.querySelectorAll('input[type="checkbox"]')).map(i => i.name)
    );
    for (const name of checkboxNames) {
      const boxes = Array.from(step.querySelectorAll(`input[name="${name}"]`));
      if (name === "components" && !boxes.some(b => b.checked)) {
        setStepError("Bitte wählen Sie mindestens eine Komponente aus.");
        return false;
      }
    }
    const radioNames = new Set(
      Array.from(step.querySelectorAll('input[type="radio"]')).map(i => i.name)
    );
    for (const name of radioNames) {
      const radios = Array.from(step.querySelectorAll(`input[name="${name}"]`));
      if (!radios.some(r => r.checked)) {
        setStepError("Bitte wählen Sie eine Option aus.");
        return false;
      }
    }
    const required = Array.from(step.querySelectorAll("[required]"));
    for (const f of required) if (!f.checkValidity()) { f.reportValidity(); return false; }
    return true;
  };

  const renderFileList = () => {
    if (!fileList) return;
    fileList.innerHTML = "";
    selectedFiles.forEach((f, idx) => {
      const li = document.createElement("li");
      li.innerHTML = `<span>${escapeHtml(f.name)}</span>
                      <small>${(f.size/1024/1024).toFixed(2)} MB</small>
                      <button type="button" class="remove" aria-label="Entfernen">×</button>`;
      li.querySelector("button.remove").addEventListener("click", () => {
        selectedFiles.splice(idx, 1); renderFileList();
      });
      fileList.appendChild(li);
    });
  };

  const escapeHtml = (s) => s.replace(/[<>&"']/g, c => ({
    "<":"&lt;",">":"&gt;","&":"&amp;",'"':"&quot;","'":"&#39;"
  }[c]));

  fileInput?.addEventListener("change", () => {
    const newOnes = Array.from(fileInput.files);
    setStepError();
    const merged = [...selectedFiles, ...newOnes];
    if (merged.length > MAX_FILES) {
      setStepError(`Maximal ${MAX_FILES} Dateien.`);
      fileInput.value = ""; return;
    }
    let total = 0;
    for (const f of merged) {
      if (f.size > MAX_FILE_BYTES) {
        setStepError(`${f.name} ist größer als 10 MB.`);
        fileInput.value = ""; return;
      }
      total += f.size;
    }
    if (total > MAX_TOTAL_BYTES) {
      setStepError("Gesamtgröße über 50 MB.");
      fileInput.value = ""; return;
    }
    selectedFiles = merged;
    fileInput.value = "";
    renderFileList();
  });

  backButton?.addEventListener("click", () => setOfferStep(currentStep - 1));
  nextButton?.addEventListener("click", () => { if (validateOfferStep()) setOfferStep(currentStep + 1); });

  offerForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!validateOfferStep()) return;

    submitButton.disabled = true;
    setStatus(offerStatus, "Wird gesendet…", null);
    clearFieldErrors(offerForm);

    const fd = new FormData();
    const native = new FormData(offerForm);
    for (const [k, v] of native.entries()) {
      if (k === "files") continue;          // we re-add below
      if (k === "components") fd.append("components[]", v);
      else fd.append(k, v);
    }
    if (selectedFiles.length > 0) fd.append("photos_followup", "1");
    selectedFiles.forEach(f => fd.append("files[]", f, f.name));

    try {
      const res = await fetch(`${API_BASE}/angebot.php`, { method: "POST", body: fd });
      const data = await res.json().catch(() => ({}));
      if (res.status === 413) {
        setStatus(offerStatus,
          "Die hochgeladenen Dateien sind zu groß. Bitte reduzieren Sie die Auswahl.",
          "error");
      } else {
        handleResponse(res, data, offerForm, offerStatus,
          "Vielen Dank! Wir melden uns innerhalb von 2 Werktagen.");
        if (res.ok && data.ok) { selectedFiles = []; renderFileList(); setOfferStep(0); }
      }
    } catch (_err) {
      setStatus(offerStatus,
        "Etwas ist schiefgelaufen. Bitte erneut versuchen oder anrufen: +49 3876 612474.",
        "error");
    } finally {
      submitButton.disabled = false;
    }
  });

  setOfferStep(0);
}

// ---------- Shared response handler ----------

function handleResponse(res, data, form, statusEl, successMsg) {
  if (res.ok && data.ok) {
    setStatus(statusEl, successMsg, "success");
    form.reset();
    return;
  }
  if (res.status === 429) {
    setStatus(statusEl,
      "Zu viele Anfragen. Bitte versuchen Sie es in einer Stunde erneut.",
      "error");
    return;
  }
  if (data.error === "validation") {
    showFieldErrors(form, data.fields);
    setStatus(statusEl, "Bitte prüfen Sie die markierten Felder.", "error");
    return;
  }
  setStatus(statusEl,
    "Etwas ist schiefgelaufen. Bitte erneut versuchen oder anrufen: +49 3876 612474.",
    "error");
}

// ---------- Lucide icons ----------

window.addEventListener("load", () => { if (window.lucide) window.lucide.createIcons(); });
```

- [ ] **Step 2: Manual smoke-test (deferred to full deploy)**

This can only be properly tested against a live backend at `api.technik-prignitz.de`. For now, open the page and verify there are no console errors on page load and the multi-step navigation still works.

- [ ] **Step 3: Commit**

```bash
git add script.js
git commit -m "feat(frontend): fetch-based submit for contact + angebot, file upload UI"
```

### Task 27: Update Datenschutzerklärung

**Files:**
- Modify: `datenschutzerklaerung/index.html`

- [ ] **Step 1: Read existing file structure to find a sensible insertion point**

Open `datenschutzerklaerung/index.html`. Look for a section heading like "Kontaktaufnahme" or the closest equivalent — insert the new block before the Impressum/contact footer or after any existing data-collection section.

- [ ] **Step 2: Insert new section**

Add the following block (adjust placement to match the document's structure):

```html
<section>
  <h2>Verarbeitung von Formularanfragen</h2>
  <p>
    Wenn Sie unser Kontaktformular oder den Angebotsassistenten nutzen, speichern wir die
    von Ihnen übermittelten Angaben (Name, Kontaktdaten, Nachricht bzw. Projektdetails,
    ggf. hochgeladene Dateien wie Fotos oder Stromrechnungen) zusammen mit Datum, Uhrzeit,
    Ihrer IP-Adresse und dem verwendeten Browser auf unserem Server in Deutschland.
  </p>
  <p>
    <strong>Rechtsgrundlage:</strong> Für den Angebotsassistenten Art. 6 Abs. 1 lit. b DSGVO
    (Anbahnung eines Vertragsverhältnisses), für das allgemeine Kontaktformular
    Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an der Bearbeitung Ihrer Anfrage).
  </p>
  <p>
    <strong>Speicherdauer:</strong> Erledigte Anfragen werden automatisch 12 Monate nach
    Bearbeitung gelöscht. IP-Adressen werden 30 Tage nach Eingang anonymisiert.
  </p>
  <p>
    <strong>Empfänger:</strong> Eine Weitergabe an Dritte erfolgt nicht. Alle Daten
    verbleiben auf unserem Server. Es kommen keine externen Auftragsverarbeiter
    (z. B. Google reCAPTCHA, Cloudflare Turnstile) zum Einsatz.
  </p>
  <p>
    <strong>Ihre Rechte:</strong> Sie können jederzeit Auskunft, Berichtigung oder Löschung
    Ihrer Daten verlangen. Schreiben Sie dazu an
    <a href="mailto:info@technik-prignitz.de">info@technik-prignitz.de</a>.
  </p>
</section>
```

- [ ] **Step 3: Commit**

```bash
git add datenschutzerklaerung/index.html
git commit -m "docs: DSGVO section for stored form data"
```

---

## Phase 6 — Deployment runbook

### Task 28: Write deployment runbook

**Files:**
- Create: `docs/superpowers/specs/2026-05-25-deployment-runbook.md`

- [ ] **Step 1: Write the runbook**

```markdown
# Deployment Runbook — Contact Backend

This runs **once** to set up the backend on the Plesk Ubuntu VPS.

## 1. Plesk subdomains

In Plesk → Websites & Domains, add two subdomains under `technik-prignitz.de`:

| Subdomain | Document root |
| --- | --- |
| `api.technik-prignitz.de`   | `httpdocs/backend/public/api`   |
| `admin.technik-prignitz.de` | `httpdocs/backend/public/admin` |

For each, click **SSL/TLS Certificates → Install Let's Encrypt → covering both domain and www if applicable**.

## 2. PHP

Both subdomains → **PHP Settings**, select **PHP 8.2** (or the highest 8.x available).

For `api.technik-prignitz.de` only, raise:

| Setting | Value |
| --- | --- |
| `upload_max_filesize` | `12M` |
| `post_max_size`       | `60M` |
| `max_file_uploads`    | `12`  |
| `memory_limit`        | `128M` |

## 3. Database

In Plesk → Databases → Add Database:
- Name: `ti_backend`
- User: `ti_backend_app` (Plesk-generated strong password — copy it)

Load schema:
```bash
mysql -u ti_backend_app -p ti_backend < backend/sql/schema.sql
```

## 4. Mail

- Create address `anfrage@technik-prignitz.de` in Plesk → Mail (or alias).
- Plesk → Mail → **Mail Settings** → confirm **DKIM spam protection** is enabled.
- Check SPF + DMARC are publishing as expected via [mxtoolbox.com](https://mxtoolbox.com) for `technik-prignitz.de`.

## 5. Backend files

SSH in and:
```bash
cd ~/httpdocs   # or your Plesk document-root parent
git clone <repo-url> .   # or git pull if already cloned
cd backend
composer install --no-dev --no-interaction
cp config/config.example.php config/config.php
chmod 640 config/config.php
mkdir -p storage/uploads storage/logs
chmod -R 750 storage
```

Edit `backend/config/config.php`:
- DB host/port/database/username/password (from step 3)
- `mail.from_address`, `to_address`
- `urls.admin_base`, `public_site`

## 6. Initial admin account

```bash
cd backend && php cron/setup-admin.php
# Enter username + password, then:
rm cron/setup-admin.php
```

## 7. Cron jobs

Plesk → Scheduled Tasks (under the domain). Add three:

| Schedule | Command |
| --- | --- |
| Hourly (`0 * * * *`) | `php /var/www/vhosts/technik-prignitz.de/httpdocs/backend/cron/cleanup_rate_limit.php` |
| Daily 03:00 (`0 3 * * *`) | `php /var/www/vhosts/technik-prignitz.de/httpdocs/backend/cron/retention.php` |
| Daily 08:00 (`0 8 * * *`) | `php /var/www/vhosts/technik-prignitz.de/httpdocs/backend/cron/mail_health.php` |

(Adjust the absolute path to match the real `httpdocs` of the site in your Plesk install.)

## 8. Smoke tests

- Visit `https://api.technik-prignitz.de/contact.php` from a browser → expect `{ok:false,error:"method_not_allowed"}`.
- Submit the homepage form on `https://technik-prignitz.de` with a real address; check inbox + admin.
- Submit the Angebot form with a small JPG; download from the admin detail view.
- Verify `mail_errors.log` does not grow (`tail -f backend/storage/logs/mail_errors.log`).

## 9. Updates afterwards

```bash
ssh <vps>
cd ~/httpdocs && git pull
cd backend && composer install --no-dev --no-interaction
# If schema.sql changed, apply diff manually via phpMyAdmin.
```
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-05-25-deployment-runbook.md
git commit -m "docs: deployment runbook for Plesk"
```

### Task 29: Final integration — run all tests + merge

- [ ] **Step 1: Run the full PHPUnit suite**

Run: `cd backend && vendor/bin/phpunit`
Expected: every test passes, no warnings.

- [ ] **Step 2: Quick lint pass (PHP syntax check)**

Run: `find backend -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l | grep -v "No syntax errors"`
Expected: command produces no output (every file parses).

- [ ] **Step 3: Merge to main**

```bash
git checkout main
git merge --no-ff feat/contact-backend -m "feat: contact + Angebot backend (admin UI, DB, file uploads, GDPR retention)"
git log --oneline -10
```

- [ ] **Step 4: Tag**

```bash
git tag -a v1.0-backend -m "Backend v1: contact + Angebot pipeline"
```

- [ ] **Step 5: Deploy**

Follow `docs/superpowers/specs/2026-05-25-deployment-runbook.md`.
