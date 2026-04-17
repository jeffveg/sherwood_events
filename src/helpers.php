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
