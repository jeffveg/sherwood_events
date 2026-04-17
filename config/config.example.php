<?php
/**
 * Copy this file to config.php and fill in real values.
 * config.php is gitignored — never commit it.
 */

// --- Database ---
const DB_HOST = 'localhost';
const DB_NAME = 'sherwood_events';
const DB_USER = 'sherwood_events';
const DB_PASS = 'change-me';
const DB_CHARSET = 'utf8mb4';

// --- Site ---
const SITE_URL    = 'http://localhost:8080';               // no trailing slash
const SITE_NAME   = 'Sherwood Adventure — Upcoming Events';
const SITE_TAGLINE = 'Open events, tournaments, and community archery days in the Phoenix area';
const TIMEZONE    = 'America/Phoenix';

// --- Admin auth ---
// Generate with: php scripts/make_password_hash.php
const ADMIN_PASSWORD_HASH = '$2y$10$REPLACE_ME_WITH_REAL_HASH';
const ADMIN_SESSION_IDLE_SECONDS  = 3600;     // 1 hour idle timeout
const ADMIN_SESSION_MAX_SECONDS   = 43200;    // 12 hour absolute max

// --- CSRF ---
// Generate with: php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
const CSRF_SECRET = 'change-me-to-a-long-random-hex-string';

// --- Uploads ---
const UPLOAD_DIR       = __DIR__ . '/../public/uploads';    // server filesystem
const UPLOAD_URL_PATH  = '/uploads';                         // URL prefix
const UPLOAD_MAX_BYTES = 4 * 1024 * 1024;                    // 4 MB
const UPLOAD_MAX_WIDTH = 1600;                               // resize down to this

// --- Branding / main site ---
// Main site exposes brand.css + style.css + the logo — link to them from here.
const MAIN_SITE_URL  = 'https://sherwoodadventure.com';
const BOOKING_URL    = 'https://schedule.sherwoodadventure.com';
const MAIN_LOGO_URL  = 'https://sherwoodadventure.com/images/logo.png';

// --- Misc ---
const FALLBACK_EVENT_IMAGE = MAIN_SITE_URL . '/images/hero/archery-field.jpg';
