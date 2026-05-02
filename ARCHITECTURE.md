# Sherwood Events — Architecture

A short orientation for someone who's never seen this codebase. Read this
first, then `README.md`, then dive into specific files.

---

## What this app is

`events.sherwoodadventure.com` is the **upcoming-events page** for Sherwood
Adventure (mobile archery tag in Phoenix, AZ). It replaces a paid
[Tockify](https://tockify.com) embed that was used on the main site.
Plain PHP 8 + MariaDB, no framework, no Composer dependencies.

It's one of four Sherwood apps:

| App | URL | Repo | Purpose |
| --- | --- | --- | --- |
| Main site | sherwoodadventure.com | `sherwood_web` | Marketing, brand, contact form |
| Booking app | schedule.sherwoodadventure.com | `Sherwood_Schedule` | Customer-facing self-serve booking with payment |
| Tournaments | signup.sherwoodadventure.com | `out-west-announcer` | Tournament brackets, schedules, scoring |
| **Events** | **events.sherwoodadventure.com** | **`sherwood_events`** | **Public list of upcoming events + admin** |

---

## File layout

```
sherwood_events/
├── public/                      ← document root on IONOS (never put PHP secrets here)
│   ├── index.php                public list view
│   ├── event.php                single event page (with JSON-LD, share, RSVP)
│   ├── rsvp.php                 POST handler for the RSVP form
│   ├── events.ics.php           iCal feed of upcoming events
│   ├── event.ics.php            per-event .ics download
│   ├── events.rss.php           RSS feed
│   ├── sitemap.xml.php          dynamic sitemap
│   ├── admin/                   password-protected admin pages
│   │   ├── login.php / logout.php
│   │   ├── index.php            dashboard / event list
│   │   ├── edit.php             create / edit event (handles upload too)
│   │   ├── duplicate.php        copy an event as a draft
│   │   ├── delete.php           soft-delete (sets status='cancelled')
│   │   └── rsvps.php            view + CSV export RSVPs
│   ├── api/
│   │   └── intake.php           authenticated draft-event endpoint (see INTAKE.md)
│   ├── _partials/               PHP includes shared across pages
│   │   ├── head.php             <head> with OG/Twitter/JSON-LD scaffolding
│   │   ├── nav.php              site nav (mirrors main site)
│   │   ├── footer.php           site footer
│   │   ├── flashes.php          flash-message renderer
│   │   └── book-cta.php         "Book Your Adventure" call-out card
│   ├── assets/
│   │   ├── css/events.css       events-only styles (see CSS section)
│   │   └── js/events.js         minimal progressive enhancement
│   ├── uploads/                 admin-uploaded event images (gitignored)
│   ├── .htaccess                rewrites + security headers + HTTPS redirect
│   └── robots.txt
├── src/                         application code, kept above webroot when possible
│   ├── bootstrap.php            loaded by every entry point — config, session, DB
│   ├── db.php                   PDO singleton, timezone pinned
│   ├── auth.php                 admin login (single password, DB-backed throttling)
│   ├── helpers.php              e(), csrf_*, slugify, fmt_*, client_ip_hash, etc.
│   ├── events.php               event/tag query layer
│   └── rsvp.php                 RSVP queries + rate-limit
├── config/
│   ├── config.example.php       committed; copy to config.php and fill in
│   └── config.php               GITIGNORED — DB creds, admin hash, secrets
├── sql/
│   ├── 001_schema.sql           full schema for fresh installs
│   ├── 002_seed_dev.sql         sample data (dev only — do NOT run in prod)
│   ├── 003_login_attempts.sql   migration: brute-force throttling table
│   └── 004_intake.sql           migration: intake_ref column for the API
├── scripts/
│   └── make_password_hash.php   CLI helper: prompts password, prints bcrypt hash
├── deploy.sh                    server-side: git fetch/reset + chmod (run by batch)
├── .htaccess                    repo-root defense in depth (only kicks in if
│                                docroot is misaimed — see SEC section)
├── .github/workflows/deploy.yml GitHub Actions auto-deploy (currently OFF;
│                                manual workflow_dispatch only)
├── README.md                    project overview
├── DEPLOY.md                    first-time IONOS deployment
├── AUTO_DEPLOY.md               GitHub Actions setup (if ever turned on)
├── INTAKE.md                    intake API contract (used by Sherwood_Schedule)
└── ARCHITECTURE.md              this file
```

---

## Database

```
events ──── event_tags ──── tags
   │
   └── rsvps                                    login_attempts (independent)
```

**`events`** is the central table. Each row is one event — title, date(s),
location, description, optional image, RSVP toggle, capacity, status.
`status` is `'draft' | 'published' | 'cancelled'`. Soft-delete via
`status='cancelled'` is the actual flow (see `admin/delete.php` and
`event_delete()`); we never hard-delete from the UI.

**`tags`** + **`event_tags`** is a many-to-many. Tags are seeded by
`001_schema.sql` (Tournament, Community Day, Festival, Church & Faith,
Youth) and there's currently no admin UI to add/edit tags — edit them
directly in the DB if needed.

**`rsvps`** is forward-compatible with paid tickets: columns
`ticket_tier`, `amount_cents`, `payment_status`, `payment_ref` exist
but currently aren't written to. Free RSVPs are the only flow today.

**`login_attempts`** powers the per-IP brute-force throttle (see SEC).
Rows accumulate forever; if it ever grows enough to matter, periodically
delete rows older than 30 days. Index `idx_time` makes that cheap.

The `events` table also has an `intake_ref VARCHAR(60) NULL UNIQUE` column
used by `/api/intake.php` for idempotent draft creation from external
systems (see `INTAKE.md`).

---

## Request flows

### Public list (`/`)
1. `index.php` requires `bootstrap.php` (config, session, DB ready).
2. Reads optional `?tag=slug` filter.
3. Calls `events_public_upcoming($tagId)` — returns events whose end is
   in the future, OR (no end) whose start was within
   `UPCOMING_GRACE_HOURS=4` hours.
4. Calls `events_public_past(8)` for the accordion at the bottom.
5. Calls `tags_for_events($ids)` to bulk-fetch tags (avoids N+1).
6. Renders cards with month dividers; injects `_partials/book-cta.php`
   every 3 non-featured events; featured events are pinned at the top
   under a single "Featured" header.

### Single event (`/event.php?slug=...`)
1. Loads event by slug; 404 if not found OR status='draft'.
2. Builds JSON-LD `Event` schema for Google rich results.
3. Builds Google Calendar quick-add URL and per-event `.ics` link.
4. If `rsvp_enabled=1` and not cancelled, renders the RSVP form.
5. Cancelled events show a banner AND set `<meta name="robots" content="noindex">`
   so Google drops them from search.

### RSVP submit (`POST /rsvp.php`)
1. CSRF check, honeypot check, time-gate (≥3 s after page render).
2. Length-cap inputs (`mb_substr`).
3. Validate event is published + RSVP-enabled.
4. Duplicate-email check (returns "you're already on the list").
5. Capacity check (per-attendee, not per-RSVP).
6. IP-hash rate limit (5 RSVPs / 10 min).
7. Insert and redirect back to event page with `?rsvped=1`.

### Admin (`/admin/`)
1. `auth_require()` checks the session — redirects to login if absent.
2. Login: bcrypt password compare, DB-backed brute-force throttle
   keyed by HMAC of client IP.
3. Edit form handles file upload (re-encoded through GD to strip
   metadata) OR external URL (validated as `http://` or `https://`).

### Intake API (`POST /api/intake.php`)
External Sherwood systems push draft events here. Currently only
`Sherwood_Schedule`'s booking flow uses it: when a customer ticks
"allow_publish" on step 5, step 7 fires a JSON POST after the booking
commits. See [INTAKE.md](INTAKE.md) for the full contract.

---

## CSS architecture (the "master CSS" pattern)

Three layers, each with one job:

| Layer | Where it lives | Loaded by |
| --- | --- | --- |
| **Tokens** — colors, fonts, shadows | `sherwoodadventure.com/css/brand.css` | every Sherwood subdomain |
| **Components** — `.btn`, `.content-body`, `.site-nav`, `.site-footer`, typography | `sherwoodadventure.com/css/style.css` | every Sherwood subdomain |
| **Project-specific** — event cards, tag pills, admin tables, RSVP form | `events.sherwoodadventure.com/assets/css/events.css` | events only |

`events.css` should NEVER redefine anything that lives in the main site's
`style.css`. If you find yourself copying a `.btn` rule into `events.css`,
stop — either modify `style.css` so all subdomains pick up the change,
or use a more specific selector that *extends* the main rule.

**Cross-origin fonts:** `style.css` has `@font-face` rules pointing at
`/css/Lustria-Regular.woff2` etc. Browsers block cross-origin font loads
unless the server sends `Access-Control-Allow-Origin`. The main site's
top-level `.htaccess` sets that header for `.woff/.woff2/.ttf/.otf/.eot` —
without it, events would silently fall back to system fonts.

---

## Deployment

**Server:** IONOS shared hosting at `/kunden/homepages/40/d493077416/htdocs/events`.
Subdomain document root is set to `<that path>/public/` so `src/`,
`config/`, `sql/`, `scripts/` stay above webroot.

**Push-to-deploy is OFF** by intent. Auto-deploy via GitHub Actions exists
in `.github/workflows/deploy.yml` but is set to `workflow_dispatch` only —
the chosen workflow is to push all four Sherwood repos in lockstep, then
run a single batch deploy script on the server (`bash ~/deploy.sh`) that
pulls every repo. The per-repo `deploy.sh` script in this repo is invoked
by that batch script when present (it does an extra `chmod` pass on file
permissions and locks down `config.php` to mode 600).

**To re-enable per-push auto-deploy:** see `AUTO_DEPLOY.md` — add 5 GitHub
secrets and change the `on:` block in `.github/workflows/deploy.yml` back
to triggering on `push`.

---

## IONOS gotchas worth knowing about

These bit during initial deployment and will bite again next time you
debug something. They're documented inline at the relevant places, but
collected here for orientation.

1. **The default `php` CLI is PHP 4.4.9 from 2008.** No really. Use the
   versioned binary `php8.4` (or whatever current major is enabled in
   the panel) for any CLI work. The web side runs whatever PHP version
   is configured in the panel for the subdomain.

2. **The `php8.4` CLI binary is a CGI binary, not a true CLI.** It does
   NOT accept `-r` (inline code), `-F` (file), or read code from stdin.
   It DOES accept script-file invocation: `php8.4 /tmp/foo.php` works.
   Workaround pattern when you need to run a one-off script:
   ```bash
   cat > ~/events/_temp.php <<'EOF'
   <?php
   require __DIR__ . '/config/config.php';
   require __DIR__ . '/src/db.php';
   // ... do thing ...
   EOF
   php8.4 ~/events/_temp.php
   rm ~/events/_temp.php
   ```
   Note `__DIR__` works correctly only if the script lives inside the
   project; the CGI binary does NOT honor the shell's CWD for relative
   `require` paths.

3. **For random hex without PHP, use `openssl rand -hex 32`.** It's
   simpler than wrangling the CGI binary into emitting random bytes.

4. **Apache appears to run as the FTP/SSH user**, so file mode 600 on
   `config.php` works (PHP can read it as owner). The default umask
   produces 604 (`-rw----r--`) which is also fine — `deploy.sh` chmods
   to 644 / 600 as appropriate.

5. **The MariaDB connection's session timezone is pinned to `-07:00`**
   (Phoenix, no DST). All datetimes are stored and compared as Phoenix-
   naive. If Sherwood ever operates outside Arizona, this needs to
   change — see the comment in `src/db.php`.

---

## Security model

The threat model assumes a small business with a single trusted admin,
public-facing event listings, free RSVPs (no payments today). Security
posture is "industry-standard for this profile" — not Fort Knox, not
sloppy.

- **Admin auth.** Single password, bcrypt (cost 12) in `config.php`.
  Sessions are HttpOnly + Secure + SameSite=Lax, idle timeout 1 h,
  absolute max 12 h. Brute-force throttling lives in the
  `login_attempts` DB table (NOT session — sessions are trivially
  bypassable by clearing cookies).
- **CSRF.** Per-session tokens via `csrf_token()` / `csrf_check()`.
  All admin forms and the public RSVP form check it. Token rotates
  on logout via `csrf_rotate()`.
- **HTTPS.** Forced via `public/.htaccess`. HSTS header set with
  short max-age (intentionally low so we can roll back if SSL ever
  breaks; bump to 6 months once confident).
- **HMAC IP hashing.** `client_ip_hash()` uses `hash_hmac('sha256', $ip,
  CSRF_SECRET)` so RSVP / login rate-limits work without storing real
  IPs in the DB. Stable for a given (IP, secret) pair so windows work;
  opaque to anyone reading the DB.
- **Image uploads.** Whitelist MIME via `getimagesize`, reject if
  width × height > 50 megapixels (decompression-bomb DoS), re-encode
  through GD (strips EXIF and any embedded payloads). Uploaded files
  get a random-hex filename. `public/uploads/.htaccess` blocks PHP
  execution as defense in depth.
- **Open redirect defense.** `auth_require()` validates `REQUEST_URI`
  before stashing it as the after-login target.
- **JSON-LD output.** Uses `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
  | JSON_HEX_QUOT` so a malicious `</script>` smuggled into any field
  can't break out of the inline script tag.
- **Defense in depth.** Top-level `.htaccess` blocks public access to
  `src/`, `config/`, `sql/`, `scripts/`, `.git/`, etc. — only relevant
  if document root is ever mis-aimed at the repo root, but free.

Things deliberately NOT done (acceptable tradeoffs for the threat model):

- No 2FA on admin login. (Single admin, no PII / payments at risk.)
- No application-wide audit log. (`login_attempts` covers logins;
  nothing else is logged.)
- No Content-Security-Policy header. (Worth adding eventually, but
  finicky with cross-origin CSS/fonts and inline JSON-LD.)
- No automated DB backup. (IONOS panel offers backups; configure there.)

The full audit trail of what was considered and the rationale lives in
the git log — search for `SEC-` prefixes.

---

## Common tasks

### "I want to add a feature"
1. Read the relevant files in `public/` and `src/`.
2. Add tests by hand against `localhost:8080` (no automated tests today).
3. If it touches the DB schema, add a numbered migration in `sql/`.
4. Push, then run the batch deploy on IONOS.

### "Something looks wrong on the public site"
1. Hit the CSS file directly with a cache-buster:
   `https://events.sherwoodadventure.com/assets/css/events.css?v=999`.
2. Right-click → Inspect → Styles pane to see which rule is winning.
3. If the issue is in a shared component (button, footer, content
   typography), the fix probably belongs in `sherwood_web/css/style.css`,
   not here.

### "Admin login won't work"
- Have you been hitting it a lot? Check `login_attempts` — 5 fails in
  15 min from one IP locks that IP out. `DELETE FROM login_attempts
  WHERE ip_hash = '<your hash>';` to clear yourself.
- Is `ADMIN_PASSWORD_HASH` actually set in `config.php`? It's not in
  `config.example.php` — generate with `php8.4 scripts/make_password_hash.php`.

### "How do I add a new admin user?"
You don't. Admin is a single shared password by design. To rotate the
password: regenerate the hash, paste into `config.php`, deploy.

### "I want to test the intake API"
See INTAKE.md → "Quick test" — it has a curl one-liner.
