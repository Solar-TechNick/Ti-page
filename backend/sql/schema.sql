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

CREATE TABLE IF NOT EXISTS vouchers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50) NOT NULL,
    expires_at  DATETIME NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotent add of voucher_code column on angebot_requests
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'angebot_requests'
               AND column_name = 'voucher_code');
SET @sql := IF(@col = 0,
  'ALTER TABLE angebot_requests ADD COLUMN voucher_code VARCHAR(50) NULL AFTER details',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Idempotent add of address_street column on angebot_requests
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'angebot_requests'
               AND column_name = 'address_street');
SET @sql := IF(@col = 0,
  'ALTER TABLE angebot_requests ADD COLUMN address_street VARCHAR(200) NULL AFTER location',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Idempotent add of address_postal column on angebot_requests
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'angebot_requests'
               AND column_name = 'address_postal');
SET @sql := IF(@col = 0,
  'ALTER TABLE angebot_requests ADD COLUMN address_postal VARCHAR(20) NULL AFTER address_street',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Idempotent add of address_city column on angebot_requests
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'angebot_requests'
               AND column_name = 'address_city');
SET @sql := IF(@col = 0,
  'ALTER TABLE angebot_requests ADD COLUMN address_city VARCHAR(100) NULL AFTER address_postal',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
