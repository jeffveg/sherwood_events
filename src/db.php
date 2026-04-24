<?php
declare(strict_types=1);

/**
 * Lazy PDO singleton. Usage: $pdo = db();
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // ── Timezone ──────────────────────────────────────────────────────────
    // Sherwood Adventure only operates in Arizona, which sits on MST
    // year-round (no DST). So we pin the MySQL connection to a fixed
    // -07:00 offset. Admin forms submit Phoenix-local datetimes, PHP
    // formats them back in Phoenix, and NOW() in SQL now matches — so
    // "upcoming" / "past" comparisons are correct.
    //
    // NICE TO KNOW: If Sherwood ever operates outside Arizona (or Arizona
    // ever adopts DST — unlikely), this needs to change to 'America/Phoenix'
    // (requires the mysql.time_zone_tables to be loaded on the server) or
    // the app needs to store UTC and convert at the edges.
    // ──────────────────────────────────────────────────────────────────────
    $pdo->exec("SET time_zone = '-07:00'");

    return $pdo;
}
