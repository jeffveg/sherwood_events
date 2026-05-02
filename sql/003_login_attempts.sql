-- Sherwood Events — migration 003
--
-- Adds a login_attempts table so admin brute-force protection lives in the
-- database (per-IP) instead of in the session (per-cookie, trivially bypassed).
--
-- Run once against the production DB:
--   mysql -h <host> -u <user> -p <db> < sql/003_login_attempts.sql

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_hash      CHAR(64)     NOT NULL,
  attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  succeeded    TINYINT(1)   NOT NULL DEFAULT 0,
  INDEX idx_ip_time (ip_hash, attempted_at),
  INDEX idx_time   (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
