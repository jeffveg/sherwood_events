<?php
/**
 * Intake API — accepts authenticated draft events from external systems.
 *
 * Currently called by the Sherwood_Schedule booking app at the end of step7
 * when the customer has set allow_publish=1 on their booking. Designed to be
 * generic so any future Sherwood app can push draft events.
 *
 *   POST /api/intake.php
 *   Headers:
 *     Content-Type: application/json
 *     X-API-Key: <INTAKE_API_KEY from events config>
 *   Body (JSON):
 *     intake_ref      string   required  external system's reference (e.g. "SA-2026-001")
 *     title           string   required  event title (≤200 chars)
 *     start_datetime  string   required  YYYY-MM-DD HH:MM:SS (Phoenix local)
 *     end_datetime    string   optional  YYYY-MM-DD HH:MM:SS
 *     description     string   optional  ≤16000 chars
 *     location_name   string   optional  ≤200
 *     location_addr   string   optional  ≤300
 *
 * Responses:
 *   201 Created       — new draft event inserted
 *   200 OK            — event with this intake_ref already exists (idempotent)
 *   400 Bad Request   — validation error
 *   401 Unauthorized  — missing or wrong API key
 *   405 Method Not Allowed — non-POST
 *   415 Unsupported Media Type — non-JSON body
 *
 * All responses are JSON. Errors look like {"error": "..."}.
 * Successes look like {"id": 42, "slug": "...", "edit_url": "...", "status": "draft"}.
 */

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/events.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Method gate ─────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// ── Auth: shared API key, constant-time compare ─────────────────────────
$supplied = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!is_string($supplied) || $supplied === ''
    || !defined('INTAKE_API_KEY')
    || !hash_equals(INTAKE_API_KEY, $supplied)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Body: must be JSON ──────────────────────────────────────────────────
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') === false) {
    http_response_code(415);
    echo json_encode(['error' => 'Content-Type must be application/json']);
    exit;
}

$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Body must be a JSON object']);
    exit;
}

// ── Validate ────────────────────────────────────────────────────────────
$errors = [];

$intakeRef = trim((string)($data['intake_ref'] ?? ''));
if ($intakeRef === '')                   $errors[] = 'intake_ref is required';
elseif (mb_strlen($intakeRef) > 60)      $errors[] = 'intake_ref exceeds 60 chars';

$title = trim((string)($data['title'] ?? ''));
if ($title === '')                       $errors[] = 'title is required';

$start = trim((string)($data['start_datetime'] ?? ''));
if ($start === '')                       $errors[] = 'start_datetime is required';
elseif (!validate_datetime($start))      $errors[] = 'start_datetime must be YYYY-MM-DD HH:MM:SS';

$end = trim((string)($data['end_datetime'] ?? ''));
if ($end !== '' && !validate_datetime($end)) $errors[] = 'end_datetime must be YYYY-MM-DD HH:MM:SS';
if ($end !== '' && $start !== '' && $end < $start) $errors[] = 'end_datetime must be after start_datetime';

if ($errors) {
    http_response_code(400);
    echo json_encode(['error' => implode('; ', $errors)]);
    exit;
}

// ── Idempotency: if a row already exists for this intake_ref, return it ──
$existing = event_find_by_intake_ref($intakeRef);
if ($existing) {
    http_response_code(200);
    echo json_encode([
        'id'       => (int)$existing['id'],
        'slug'     => $existing['slug'],
        'status'   => $existing['status'],
        'edit_url' => rtrim(SITE_URL, '/') . '/admin/edit.php?id=' . (int)$existing['id'],
        'message'  => 'A draft already exists for this intake_ref; returning the existing record.',
    ]);
    exit;
}

// ── Insert ──────────────────────────────────────────────────────────────
$slugFinal = slug_unique(slugify($title));

$payload = [
    'slug'           => $slugFinal,
    'title'          => mb_substr($title, 0, 200),
    'description'    => mb_substr(trim((string)($data['description']   ?? '')), 0, 16000),
    'start_datetime' => $start,
    'end_datetime'   => $end !== '' ? $end : null,
    'all_day'        => 0,
    'location_name'  => mb_substr(trim((string)($data['location_name'] ?? '')), 0, 200) ?: null,
    'location_addr'  => mb_substr(trim((string)($data['location_addr'] ?? '')), 0, 300) ?: null,
    'map_url'        => null,
    'event_site_url' => null,
    'ticket_url'     => null,
    'image_path'     => null,
    'image_alt'      => null,
    'status'         => 'draft',                      // intake always lands as draft
    'featured'       => 0,
    'rsvp_enabled'   => 0,
    'rsvp_capacity'  => null,
];

try {
    $newId = event_create_with_intake_ref($payload, $intakeRef);
} catch (Throwable $ex) {
    // Defensive: if the UNIQUE index on intake_ref fires due to a race
    // (two near-simultaneous calls with the same ref), re-fetch and
    // return the row that won.
    $existing = event_find_by_intake_ref($intakeRef);
    if ($existing) {
        http_response_code(200);
        echo json_encode([
            'id'       => (int)$existing['id'],
            'slug'     => $existing['slug'],
            'status'   => $existing['status'],
            'edit_url' => rtrim(SITE_URL, '/') . '/admin/edit.php?id=' . (int)$existing['id'],
            'message'  => 'Race-resolved: existing draft returned.',
        ]);
        exit;
    }
    error_log('intake.php insert failed: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error creating event.']);
    exit;
}

http_response_code(201);
echo json_encode([
    'id'       => $newId,
    'slug'     => $slugFinal,
    'status'   => 'draft',
    'edit_url' => rtrim(SITE_URL, '/') . '/admin/edit.php?id=' . $newId,
]);

// -----------------------------------------------------------------------
// Helpers used only by this endpoint
// -----------------------------------------------------------------------
function validate_datetime(string $s): bool
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $s);
    return $d !== false && $d->format('Y-m-d H:i:s') === $s;
}
