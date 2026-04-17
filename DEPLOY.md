# Deploying to IONOS

## 1. DNS

In the IONOS domain panel, add a subdomain **events.sherwoodadventure.com**
pointing at a new webspace folder, e.g. `/events/`.

## 2. Database

In the IONOS panel, **Databases → MariaDB → New database**. Note the:

- host (something like `db12345.hosting-data.io`)
- database name
- username
- password

Then import the schema using phpMyAdmin (IONOS provides it):

- Paste `sql/001_schema.sql` and run.
- Optionally paste `sql/002_seed_dev.sql` for sample events. **Skip in production.**

## 3. Config

On your workstation:

```
cp config/config.example.php config/config.php
```

Edit `config/config.php` and fill in:

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` from step 2
- `SITE_URL` → `https://events.sherwoodadventure.com`
- `ADMIN_PASSWORD_HASH` → generate with:
  ```
  php scripts/make_password_hash.php
  ```
  It prompts for a password and prints a bcrypt hash. Paste the hash.
- `CSRF_SECRET` → generate with:
  ```
  php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
  ```

**Never commit `config/config.php`.** It is in `.gitignore`.

## 4. Upload

SFTP (or the IONOS file manager) the following into the webspace folder you
pointed events.sherwoodadventure.com at:

```
public/      → goes at the webroot
src/         → one level ABOVE the webroot (not publicly reachable) is ideal.
config/      → one level ABOVE the webroot.
```

If your IONOS plan does not let you put files above the webroot, put `src/`
and `config/` inside `public/` and rely on the `.htaccess` rules that block
direct access (already in place).

Adjust paths at the top of `public/_bootstrap.php` accordingly.

Permissions:

- directories: 755
- files: 644
- `public/uploads/`: 775 (must be writable by the web user)
- `config/config.php`: 600

## 5. Point the main site at it

In `sherwood_web/upcoming-events.html`, replace the placeholder section with a
redirect or an iframe, or simply update the nav link:

```html
<li><a href="https://events.sherwoodadventure.com/" role="menuitem">Upcoming Events</a></li>
```

## 6. Verify

- `https://events.sherwoodadventure.com/` — public list
- `https://events.sherwoodadventure.com/admin/` — login
- `https://events.sherwoodadventure.com/events.ics` — calendar feed
- `https://events.sherwoodadventure.com/events.rss` — RSS feed

## Updating

Repeat the upload step for changed files. No migrations yet, but when the
schema changes add a `sql/002_*.sql`, `sql/003_*.sql` etc. and run them
through phpMyAdmin in order.
