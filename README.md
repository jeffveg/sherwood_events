# Sherwood Events

Small PHP + MariaDB app that powers **events.sherwoodadventure.com** — a public
list of upcoming events plus a password-protected admin page to manage them.

Replaces the `tockify.com` embed previously used on the main site.

## Features

- Public list view (Tockify-style cards) with month dividers
- Per-event page with JSON-LD for Google rich results
- Optional image per event (uploaded **or** external URL)
- Optional start + end times; all-day events supported
- Map link, event-site link, ticket link (external)
- **Free RSVPs** with name/email/party-size, schema ready for paid tickets later
- **Tags / categories** with filter bar
- **Featured** pinning
- **Duplicate** existing event (saves typing)
- **iCal feed** at `/events.ics`, **RSS feed** at `/events.rss`
- **Add-to-Calendar** (Google + `.ics`) and **social share** buttons per event
- Injected "Book Your Adventure" CTA card every 3 list items
- Admin: login, list, create, edit, duplicate, cancel, view RSVPs, CSV export

## Stack

- PHP 8.1+ (PDO)
- MariaDB 10.4+ (works on IONOS shared hosting)
- No framework, no Composer deps — drop-in deployment
- Shares brand tokens with the main site via `https://sherwoodadventure.com/css/brand.css`

## Quick start (local)

1. Have PHP 8+ and MariaDB/MySQL installed.
2. Create a DB and import the schema:
   ```
   mysql -u root -p < sql/001_schema.sql
   mysql -u root -p sherwood_events < sql/002_seed_dev.sql   # optional sample data
   ```
3. Copy `config/config.example.php` → `config/config.php` and fill in DB creds.
4. Generate an admin password hash:
   ```
   php scripts/make_password_hash.php
   ```
   Paste the output into `config/config.php` as `ADMIN_PASSWORD_HASH`.
5. Serve the `public/` directory:
   ```
   php -S localhost:8080 -t public
   ```
6. Visit http://localhost:8080 — admin is at http://localhost:8080/admin/.

## Deployment

- **First-time setup on IONOS:** see [DEPLOY.md](DEPLOY.md)
- **Auto-deploy on every push to `main`:** see [AUTO_DEPLOY.md](AUTO_DEPLOY.md)

## Project layout

```
public/        document root
  admin/       password-protected admin pages
  _partials/   shared PHP partials (nav, footer, head, book-cta)
  assets/      css / js / images
  uploads/     uploaded event images (writable; gitignored)
src/           application code (db, auth, queries, helpers)
config/        config.php (gitignored), config.example.php (committed)
sql/           schema + seed
scripts/       CLI helpers (password-hash generator)
```

## Security baseline

- Prepared statements everywhere
- CSRF tokens on all admin forms and the public RSVP form
- bcrypt password (via `password_hash` / `password_verify`)
- Honeypot + time-gate on RSVP (anti-bot, same trick as main site contact form)
- Image uploads: MIME whitelist, re-encoded through GD to strip metadata
- `HttpOnly; Secure; SameSite=Lax` session cookies
- `.htaccess` blocks PHP execution inside `/uploads/` and direct access to `/config/`, `/src/`, `/sql/`

## License

Proprietary — © Sherwood Adventure LLC.
