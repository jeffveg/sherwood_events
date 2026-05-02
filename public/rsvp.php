<?php
/**
 * RSVP form POST handler — events.sherwoodadventure.com/rsvp.php
 *
 * Receives the form posted from /event.php?slug=...#rsvp. On success,
 * inserts a row in `rsvps` and redirects back to the event page with
 * either ?rsvped=1 (new RSVP) or ?already=1 (duplicate email).
 *
 * Defenses, in order of evaluation:
 *   1. CSRF token check
 *   2. Honeypot field (_gotcha) — bots fill it, humans don't
 *   3. Time gate — submission < 3 s after page load = bot
 *   4. PHP-side length caps mirroring DB column widths
 *   5. Email validation via filter_var
 *   6. Duplicate-email check (friendly path; not an error)
 *   7. Capacity check (counts existing party_size sums)
 *   8. IP rate limit (5 RSVPs / 10 minutes per HMAC'd IP)
 *
 * Flash errors set with flash() are rendered on the event page via
 * _partials/flashes.php.
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/events.php';
require_once __DIR__ . '/../src/rsvp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

csrf_check();

// Honeypot
if (!empty($_POST['_gotcha'])) {
    redirect('/');
}
// Time-gate: reject submissions faster than 3 seconds
$ts = isset($_POST['_ts']) ? (int)$_POST['_ts'] : 0;
if ($ts === 0 || ((int)(microtime(true) * 1000) - $ts) < 3000) {
    redirect('/');
}

$eventId = (int)($_POST['event_id'] ?? 0);
$event   = $eventId ? event_find_by_id($eventId) : null;

if (!$event || $event['status'] !== 'published' || empty($event['rsvp_enabled'])) {
    http_response_code(404);
    exit('Event not available for RSVP.');
}

// PHP-side length caps mirror the DB column widths — bots can otherwise
// POST megabytes of data, which PHP parses into memory before PDO rejects
// the oversized values. mb_substr is multi-byte safe.
$name  = mb_substr(trim((string)($_POST['name']  ?? '')), 0, 120);
$email = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 200);
$phone = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 40);
$party = max(1, min(20, (int)($_POST['party_size'] ?? 1)));
$notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 500);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Please enter a valid name and email.');
    redirect('/event.php?slug=' . urlencode($event['slug']) . '#rsvp');
}

// Friendly duplicate check — if this email is already on the list for this
// event, skip the insert and show a "you're already in" message.
$dup = db()->prepare("SELECT id FROM rsvps WHERE event_id = :e AND email = :em LIMIT 1");
$dup->execute(['e' => (int)$event['id'], 'em' => $email]);
if ($dup->fetch()) {
    redirect('/event.php?slug=' . urlencode($event['slug']) . '&already=1#rsvp');
}

// Capacity check (per attendee, not per RSVP)
if ($event['rsvp_capacity'] !== null) {
    $taken = rsvp_attendee_count((int)$event['id']);
    if ($taken + $party > (int)$event['rsvp_capacity']) {
        flash('error', 'Sorry — not enough spots left for a party that size.');
        redirect('/event.php?slug=' . urlencode($event['slug']) . '#rsvp');
    }
}

// Rate limit
$ipHash = client_ip_hash();
if (rsvp_rate_limited($ipHash)) {
    http_response_code(429);
    exit('Too many RSVPs from this location. Please wait a bit and try again.');
}

rsvp_create([
    'event_id'       => (int)$event['id'],
    'name'           => $name,
    'email'          => $email,
    'phone'          => $phone ?: null,
    'party_size'     => $party,
    'notes'          => $notes ?: null,
    'ticket_tier'    => null,
    'amount_cents'   => null,
    'payment_status' => 'none',
    'payment_ref'    => null,
    'ip_hash'        => $ipHash,
]);

redirect('/event.php?slug=' . urlencode($event['slug']) . '&rsvped=1#rsvp');
