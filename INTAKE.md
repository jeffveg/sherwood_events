# Intake API — pushing draft events from other Sherwood apps

`POST https://events.sherwoodadventure.com/api/intake.php`

External Sherwood applications (currently just the
[Sherwood_Schedule](https://github.com/jeffveg/Sherwood_Schedule) booking app)
can push draft events into this site by calling the intake API.
The drafts land in the admin queue with `status='draft'`. An admin reviews,
edits if needed, and publishes from the normal admin UI.

## Authentication

Shared API key in the `X-API-Key` header. The key lives in
`config/config.php` on the events side as `INTAKE_API_KEY` and in the
calling app's config under whatever name it uses for the same value.

Both halves must contain **identical** strings. Generate with:

```
openssl rand -hex 32
```

The endpoint compares using `hash_equals` (constant-time) to avoid
timing oracles.

## Request

```http
POST /api/intake.php HTTP/1.1
Host: events.sherwoodadventure.com
Content-Type: application/json
X-API-Key: <64-char hex>

{
  "intake_ref":     "SA-2026-001",
  "title":          "Archery Tag Tournament",
  "start_datetime": "2026-06-14 16:00:00",
  "end_datetime":   "2026-06-14 22:00:00",
  "description":    "Optional notes from the customer's intake form.",
  "location_name":  "Sherwood Park",
  "location_addr":  "123 Main St, Glendale, AZ 85308"
}
```

| Field | Required | Notes |
| --- | --- | --- |
| `intake_ref` | yes | Caller's own reference (e.g. booking_ref). Used as idempotency key — duplicate calls return the existing draft instead of creating a new one. ≤60 chars. |
| `title` | yes | ≤200 chars (truncated server-side if longer). |
| `start_datetime` | yes | `YYYY-MM-DD HH:MM:SS` in **Phoenix local time** (the events DB connection is pinned to MST). |
| `end_datetime` | no | Same format as start. Must be ≥ start if provided. |
| `description` | no | ≤16000 chars. |
| `location_name` | no | ≤200. |
| `location_addr` | no | ≤300. |

The endpoint always sets `status='draft'` regardless of any other input —
admin review is required before anything goes public.

## Responses

| Code | When | Body |
| --- | --- | --- |
| `201 Created` | New draft inserted | `{"id": 42, "slug": "archery-tag-2", "status": "draft", "edit_url": "https://events.sherwoodadventure.com/admin/edit.php?id=42"}` |
| `200 OK` | A draft with this `intake_ref` already exists (idempotent) | Same shape as 201, plus a `message` field explaining it's a returning record. |
| `400 Bad Request` | Validation errors (semicolon-separated) | `{"error": "title is required; start_datetime must be YYYY-MM-DD HH:MM:SS"}` |
| `401 Unauthorized` | Missing or wrong `X-API-Key` | `{"error": "Unauthorized"}` |
| `405 Method Not Allowed` | Non-POST | `{"error": "Method not allowed. Use POST."}` |
| `415 Unsupported Media Type` | Non-JSON body | `{"error": "Content-Type must be application/json"}` |
| `500 Internal Server Error` | Unexpected DB failure | `{"error": "Internal error creating event."}` |

## Quick test

After configuring the key and running migration `sql/004_intake.sql`, you
can probe the endpoint from anywhere:

```bash
curl -X POST https://events.sherwoodadventure.com/api/intake.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $INTAKE_API_KEY" \
  -d '{
    "intake_ref":     "TEST-001",
    "title":          "Test Draft from Curl",
    "start_datetime": "2099-12-31 18:00:00"
  }'
```

You should get a 201 back. Repeat the same call — second time gets a 200
with the same id.

## Calling-side: Sherwood_Schedule integration

After a successful booking insert in `step7.php`, fire this when
`allow_publish=1`. Don't fail the booking if the events site is down —
just log it and let the admin notice the missing draft later.

```php
if ($allow_publish == 1) {
    $payload = json_encode([
        'intake_ref'     => $booking_ref,
        'title'          => $attraction['name'],
        'start_datetime' => $event_date . ' ' . $start_time,
        'end_datetime'   => $event_date . ' ' . $end_time,
        'description'    => wizard_get('event_notes') ?: '',
        'location_name'  => wizard_get('venue_name')  ?: '',
        'location_addr'  => trim(
            (wizard_get('venue_address') ?: '') . ', ' .
            (wizard_get('venue_city')    ?: '') . ', ' .
            (wizard_get('venue_state')   ?: '') . ' ' .
            (wizard_get('venue_zip')     ?: '')
        ),
    ]);
    $ch = curl_init(EVENTS_INTAKE_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . EVENTS_INTAKE_API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 201 && $code !== 200) {
        error_log("Events intake failed for $booking_ref: HTTP $code — $resp");
    }
}
```

Add to `config/config.example.php`:

```php
define('EVENTS_INTAKE_URL',     'https://events.sherwoodadventure.com/api/intake.php');
define('EVENTS_INTAKE_API_KEY', 'paste-the-same-value-as-events-INTAKE_API_KEY');
```
