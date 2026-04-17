-- Sherwood Events — initial schema
-- MariaDB 10.4+ / MySQL 5.7+
-- Run once against the target database.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- events
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(120) NOT NULL UNIQUE,
  title           VARCHAR(200) NOT NULL,
  description     TEXT         NOT NULL,
  start_datetime  DATETIME     NOT NULL,
  end_datetime    DATETIME     NULL,
  all_day         TINYINT(1)   NOT NULL DEFAULT 0,
  location_name   VARCHAR(200) NULL,
  location_addr   VARCHAR(300) NULL,
  map_url         VARCHAR(500) NULL,
  event_site_url  VARCHAR(500) NULL,
  ticket_url      VARCHAR(500) NULL,
  image_path      VARCHAR(500) NULL,            -- relative upload path OR absolute URL
  image_alt       VARCHAR(200) NULL,
  status          ENUM('draft','published','cancelled') NOT NULL DEFAULT 'published',
  featured        TINYINT(1)   NOT NULL DEFAULT 0,
  rsvp_enabled    TINYINT(1)   NOT NULL DEFAULT 0,
  rsvp_capacity   INT UNSIGNED NULL,            -- NULL = unlimited
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_start (start_datetime),
  INDEX idx_status_start (status, start_datetime),
  INDEX idx_featured (featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- tags
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
  id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug   VARCHAR(60) NOT NULL UNIQUE,
  name   VARCHAR(80) NOT NULL,
  color  VARCHAR(20) NULL        -- optional hex for the tag pill
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_tags (
  event_id  INT UNSIGNED NOT NULL,
  tag_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (event_id, tag_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id)   REFERENCES tags(id)   ON DELETE CASCADE,
  INDEX idx_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- rsvps
-- Designed so paid ticketing can be added later without schema changes.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rsvps (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id        INT UNSIGNED NOT NULL,
  name            VARCHAR(120) NOT NULL,
  email           VARCHAR(200) NOT NULL,
  phone           VARCHAR(40)  NULL,
  party_size      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  notes           VARCHAR(500) NULL,
  -- future-proofing for paid tickets:
  ticket_tier     VARCHAR(60)  NULL,
  amount_cents    INT UNSIGNED NULL,
  payment_status  ENUM('none','pending','paid','refunded') NOT NULL DEFAULT 'none',
  payment_ref     VARCHAR(120) NULL,            -- e.g. Stripe payment_intent id
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash         CHAR(64)     NULL,            -- sha256 of IP + CSRF_SECRET for rate-limiting
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_event (event_id),
  INDEX idx_event_email (event_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Default tag palette (safe to re-run; uses INSERT IGNORE)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO tags (slug, name, color) VALUES
  ('tournament',    'Tournament',    '#fed611'),
  ('community-day', 'Community Day', '#149cb3'),
  ('festival',      'Festival',      '#ffa133'),
  ('church',        'Church & Faith','#dfdfdf'),
  ('youth',         'Youth',         '#2d8a4e');
