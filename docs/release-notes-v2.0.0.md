# v2.0.0 — first public release

A rebuild rather than an increment: the application was restructured, the database
redesigned, and the security model completed. The schema is **incompatible** with the
unreleased 1.x line.

## Database

23 tables carrying 25 foreign keys, 7 unique keys and 5 `CHECK` constraints.

Duplicate likes, duplicate purchases and negative balances are now rejected by the
schema rather than by application code that could race. The previous design checked
in PHP and then wrote, which two concurrent requests can both pass.

- `post_likes` and `user_items` use composite primary keys, so a duplicate insert is
  refused by the database and the counter is only incremented when a row was really
  created.
- Balance changes are relative SQL updates with the check inside the `WHERE` clause:
  `UPDATE ... SET stardust = stardust - :amount WHERE user_id = :id AND stardust >= :amount`.
- One generic `rate_limits` table keyed on `(user_id, action, window_date)` replaces
  three per-feature counters, so a new throttled action needs no schema change.
- Authors are referenced by integer id everywhere. Posts previously stored their
  author as a username string, so renaming an account orphaned its content.

## Architecture

A single front controller in `public/`, with configuration, migrations and tests
above the document root and therefore unreachable over HTTP.

Repository, Service, Security and Http layers, with SQL confined to repositories and
business rules to services — both boundaries enforced by `tools/lint.php` rather than
by convention.

## Security

- **CSRF verification moved into the router**, so it applies to every unsafe method and
  a new route cannot omit it. Previously two of seven state-changing endpoints checked
  it; the password change was among the missing.
- **Sessions hardened**: `HttpOnly`, `SameSite`, idle timeout, identifier rotation on
  sign-in and periodically afterwards, and a session directory the application owns
  instead of the host's shared temp directory.
- **Uploads** validated by content, size and pixel count, then re-encoded to WebP with
  a generated filename, and stored outside the document root.
- **Private notes** encrypted with AES-256-GCM under a key derived from `APP_KEY`, which
  is never stored in the database.
- **Login throttling** with a fixed-cost comparison for unknown accounts, so response
  timing does not reveal whether a username exists.
- **Strict SQL mode is now required** on every connection. Without it the server silently
  coerces out-of-range values instead of rejecting them, which bypassed both `UNSIGNED`
  and `CHECK` constraints.
- **The hard-coded GitHub token is gone** and has been rotated. Credentials are read only
  from the environment, with no fallback value.

## Removed

The bundled **WordPress** installation and its 4,000-odd core files. It shared the web
root with the application, which exposed `wp-config.php` and the rest of the core tree
over HTTP, and its only role — a blog editor — is covered by the application's own
administration panel.

## Fixed

Defects found while rebuilding, each of which a visitor would have noticed:

- **Silent data corruption.** The MariaDB bundled with XAMPP sets `sql_mode` without
  `STRICT_TRANS_TABLES`, so writing `-5` to an `UNSIGNED` column stored `0` and raised
  only a warning, and `CHECK` constraints were never evaluated. SQLite rejects these
  values, so the test suite could not reproduce it; it surfaced only when the same probes
  ran against the real engine.
- **Two clocks in one comparison.** SQLite reports `CURRENT_TIMESTAMP` in UTC while PHP
  works in the application timezone, so cache expiry and the failed-login window were off
  by the timezone offset.
- **The home page never rendered the announcement the admin panel publishes**, so the
  broadcast feature had no visible effect.
- **The router matched only exact paths**, so `{slug}` routes could not work.
- **`ON DUPLICATE KEY UPDATE` is MySQL-only**, so the toolbox and cache repositories now
  select the syntax by driver.
- **Note deletion used a GET request**, which let any page delete a note by loading an
  image whose address pointed at the action.
- **The Steam panel rendered with missing images**, because the Content-Security-Policy
  allowed images only from this origin while the thumbnails are served by CheapShark.
- **Every page requested `/favicon.ico` and received a 404**, because there was no icon.

## Verification

| Check | Result |
|---|---|
| CI on PHP 8.1 / 8.2 / 8.3 | Passing |
| Unit assertions | 78 |
| Module checks | 64, repeatable |
| Constraint probes against a real database | 6 of 6 |

## Upgrading from the pre-release 1.x code

```bash
php tools/backup-legacy.php /path/to/webroot /path/to/backups --db my_forum
php bin/migrate-legacy.php --dry-run     # report what would be read, write nothing
php bin/migrate-legacy.php               # import
```

The importer repairs the data problems it finds rather than aborting, and reports every
repair: posts whose author has no account are reattributed to a suspended placeholder
account instead of being dropped, the zero date `0000-00-00 00:00:00` is replaced
because strict mode rejects it, duplicate likes and inventory rows are collapsed with the
counters recomputed, plaintext notes are encrypted on the way in, and accounts holding an
invalid role are reset. **The legacy database is only ever read.**

## Installing fresh

```bash
git clone https://github.com/natsume05/inspiration-terminal.git
cd inspiration-terminal
cp .env.example .env
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # put this in APP_KEY
docker compose up -d
```

See [`docs/deployment.md`](docs/deployment.md) for the non-Docker path and a
post-deployment checklist.
