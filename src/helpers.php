<?php
declare(strict_types=1);

// -----------------------------------------------------------------------------
// Output escaping
// -----------------------------------------------------------------------------
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// -----------------------------------------------------------------------------
// CSRF
// -----------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $supplied = $_POST['_csrf'] ?? '';
    if (!is_string($supplied) || !hash_equals($_SESSION['csrf'] ?? '', $supplied)) {
        http_response_code(419);
        exit('CSRF validation failed. Please go back and try again.');
    }
}

// -----------------------------------------------------------------------------
// Slugify
// -----------------------------------------------------------------------------
function slugify(string $text, int $maxLen = 120): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'event-' . bin2hex(random_bytes(3));
    }
    return substr($text, 0, $maxLen);
}

// -----------------------------------------------------------------------------
// Date formatting (America/Phoenix, no DST)
// -----------------------------------------------------------------------------
function fmt_date(string $dt): string
{
    return (new DateTimeImmutable($dt))->format('D, M j, Y');
}

function fmt_time(string $dt): string
{
    return (new DateTimeImmutable($dt))->format('g:i A');
}

function fmt_month_key(string $dt): string
{
    return (new DateTimeImmutable($dt))->format('Y-m');
}

function fmt_month_label(string $dt): string
{
    return (new DateTimeImmutable($dt))->format('F Y');
}

/**
 * Human date + time range, compact.
 *   Sat, Jun 14, 2026 · 4:00 – 10:00 PM
 *   Sat, May 3, 2026 · 10:00 AM
 *   Sat, Apr 18, 2026 (all day)
 */
function fmt_event_when(array $ev): string
{
    $d = fmt_date($ev['start_datetime']);
    if (!empty($ev['all_day'])) {
        return $d . ' (all day)';
    }
    $start = fmt_time($ev['start_datetime']);
    if (!empty($ev['end_datetime'])) {
        $end = fmt_time($ev['end_datetime']);
        $sameMer = substr($start, -2) === substr($end, -2);
        if ($sameMer) {
            $startShort = preg_replace('/ ?(AM|PM)$/', '', $start);
            return "$d · $startShort – $end";
        }
        return "$d · $start – $end";
    }
    return "$d · $start";
}

// -----------------------------------------------------------------------------
// URL helpers
// -----------------------------------------------------------------------------
function url(string $path = '/'): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

// -----------------------------------------------------------------------------
// Image src resolution
// image_path may be an uploaded filename like "abc123.jpg" OR a full URL.
// -----------------------------------------------------------------------------
function event_image_src(?string $imagePath): string
{
    if (!$imagePath) {
        return FALLBACK_EVENT_IMAGE;
    }
    if (preg_match('#^https?://#i', $imagePath)) {
        return $imagePath;
    }
    return UPLOAD_URL_PATH . '/' . ltrim($imagePath, '/');
}

// -----------------------------------------------------------------------------
// Flash messages
// -----------------------------------------------------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_pop(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

// -----------------------------------------------------------------------------
// Short description for list view (first paragraph, ~200 chars)
// -----------------------------------------------------------------------------
function short_desc(string $html, int $max = 220): string
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    if (mb_strlen($plain) <= $max) {
        return $plain;
    }
    return rtrim(mb_substr($plain, 0, $max)) . '…';
}

// -----------------------------------------------------------------------------
// Return a string only if it's a valid CSS hex color; otherwise empty string.
// Used when inlining tag colors into style="…" attributes so that even a
// malicious value in the DB can't break out of the declaration.
// -----------------------------------------------------------------------------
function safe_css_color(?string $c): string
{
    if ($c === null || $c === '') return '';
    $c = trim($c);
    return preg_match('/^#[0-9a-f]{3,8}$/i', $c) ? $c : '';
}

// -----------------------------------------------------------------------------
// Neutralize CSV-injection payloads. Excel will execute a cell that starts
// with =, +, -, @, tab, or CR, treating it as a formula. Prefix such values
// with a single quote so Excel displays them as plain text.
// -----------------------------------------------------------------------------
function csv_safe($v): string
{
    $s = (string)$v;
    if ($s === '') return $s;
    $first = $s[0];
    if ($first === '=' || $first === '+' || $first === '-' || $first === '@'
        || $first === "\t" || $first === "\r") {
        return "'" . $s;
    }
    return $s;
}

// -----------------------------------------------------------------------------
// RFC 5545 line folding for iCalendar. Content lines SHOULD NOT exceed 75
// octets; longer lines are split with CRLF + single space at the start of
// each continuation. UTF-8 safe: never splits mid-character.
// -----------------------------------------------------------------------------
function ical_fold(string $line): string
{
    if (strlen($line) <= 75) return $line;

    $out   = '';
    $pos   = 0;
    $len   = strlen($line);
    $limit = 75;                                 // first chunk: 75 octets
    while ($pos < $len) {
        $take = min($limit, $len - $pos);
        // Back up if we'd split in the middle of a UTF-8 multi-byte sequence.
        while ($take > 0 && $pos + $take < $len
               && (ord($line[$pos + $take]) & 0xC0) === 0x80) {
            $take--;
        }
        $out  .= ($pos === 0 ? '' : "\r\n ") . substr($line, $pos, $take);
        $pos  += $take;
        $limit = 74;                             // continuation lines: 74 + leading space = 75
    }
    return $out;
}
