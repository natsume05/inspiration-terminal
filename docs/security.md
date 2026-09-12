# Security model

This document states what the application defends against, how, and where the
protection stops. It is written to be checkable: every claim names the code that
implements it.

---

## Threat model

The application is a small public community site. The assets worth protecting
are user credentials, private notes, the virtual-currency balances, and the
integrity of user-generated content. The assumed attacker can send arbitrary
HTTP requests, register accounts, upload files, and read any public page.

Out of scope: a compromised host, a malicious database administrator, and
denial of service by volume.

---

## Controls

### SQL injection

Every query uses PDO with **named placeholders** and real prepared statements
(`PDO::ATTR_EMULATE_PREPARES => false`), so escaping happens in the driver rather
than in PHP string handling.

`LIMIT` and `OFFSET` cannot be bound as strings, so they are bound explicitly as
integers (`PostRepository::feed()`). They are never interpolated.

`tools/lint.php` fails the build when a SQL keyword appears on a line that also
interpolates a variable.

### Cross-site scripting (XSS)

Two layers:

- **Server**: templates print through `View::escape()`, which is
  `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. The linter
  rejects a template that echoes a variable without it.
- **Client**: `public/assets/js/dom.js` builds elements with `textContent` and
  `append`, never `innerHTML`, so comment and post text cannot be parsed as
  markup.

A Content-Security-Policy with `script-src 'self'` is sent on every HTML
response, which blocks inline script even if an injection point were missed.

### Cross-site request forgery (CSRF)

`Router::dispatch()` verifies the token for **every** unsafe method before any
handler runs. This is the structural fix for the previous version, where each
endpoint had to remember the check and only two of seven did.

- Token: 32 random bytes as hex, stored in the session.
- Comparison: `hash_equals()`, so timing does not leak the token.
- Transport: `csrf_token` form field or `X-CSRF-Token` header.
- Rotation: after login and logout, so a token captured before a privilege
  change cannot be replayed after it.

`SameSite=Lax` on the session cookie is a second, independent layer.

### Session security

| Property | Value | Why |
|---|---|---|
| `HttpOnly` | true | An XSS bug cannot read the session id |
| `SameSite` | Lax | Blocks the cookie on cross-site requests |
| `Secure` | configurable | Must be `true` on HTTPS |
| `use_strict_mode` | on | Rejects a session id the server never issued |
| `use_only_cookies` | on | Ignores a session id in the URL |
| Rotation | on login, and every 15 minutes | Bounds the life of a stolen id |
| Idle timeout | 2 hours | Limits an unattended session |

Session files are written under `storage/sessions` rather than the host's shared
temp directory. That directory is frequently unwritable on shared hosting, and
when it is, `session_start()` fails with a warning while the request continues —
so logins silently fail to persist.

### Authentication and password storage

- Passwords use `password_hash($password, PASSWORD_DEFAULT)` (bcrypt, per-hash
  salt) and are verified with `password_verify()`.
- Minimum length is enforced (8 characters).
- **Login throttling**: five failures for the same address and username lock that
  pair for 15 minutes (`AuthService`). Without this, passwords can be brute
  forced at network speed.
- **User enumeration resistance**: an unknown username still runs a password
  verification against a dummy hash, so the response time does not reveal
  whether the account exists, and both failure cases return the same message.
- Suspended accounts cannot authenticate.

### Authorisation

Guard rules:

- Queries that read or mutate user-owned rows include `user_id` **in the SQL
  condition** (`WHERE id = :id AND user_id = :user_id`), never fetch-then-check
  in PHP. A bug in a later conditional therefore cannot expose another user's
  row.
- Roles are read from the session and compared against an allow-list
  (`AuthService::isModerator()`, `isAdmin()`).

### File uploads

`ImageUploader` enforces, in order:

1. The transport succeeded (`UPLOAD_ERR_OK`), and the message a specific error
   for each failure.
2. `is_uploaded_file()`, which rejects a crafted `tmp_name` pointing at a server
   file.
3. Size within `UPLOAD_MAX_BYTES` **before** decoding, because decoding is what
   consumes memory.
4. Type by **file contents** via `getimagesize()`, not the client-supplied
   filename, so a PHP script renamed to `.png` is rejected.
5. Pixel count within `UPLOAD_MAX_PIXELS`, guarding against a decompression bomb
   where a small file expands to a huge bitmap.
6. Re-encoding through GD to WebP. The stored file is always produced by the
   server, so anything embedded in the original cannot survive.
7. A generated filename of random bytes, so a client cannot choose the path.

The upload directory sits outside the document root and is served through the
front controller, which means uploaded content can never be executed as script
even if a handler is misconfigured.

### Secrets

All secrets come from the environment. `config/app.php` reads `getenv()` with
development fallbacks that are safe to commit. `.env` is git-ignored.

The previous version defined a fallback that hard-coded a live GitHub token in
`config.php`. That is removed; the token must be rotated regardless, because it
was committed and a commit is permanent.

### Private notes

Notes are sealed with AES-256-GCM (`Crypto`), which authenticates the ciphertext
so tampering is detected on read instead of decrypting to garbage. The key is
derived from `APP_KEY` with HKDF-SHA256 and a domain-separation label, and never
stored in the database. A fresh random nonce is generated per message, because
reusing a nonce with the same key destroys both confidentiality and integrity.

**Stated limit:** the key lives in the environment, so an attacker who reads
`APP_KEY` *and* the database can decrypt every note. This protects against a
database dump or a backup leak, not against full host compromise. Users also lose
their notes if `APP_KEY` is lost or rotated, which is the deliberate trade-off of
not storing the key next to the data.

### Information disclosure

- `display_errors` is on only when `APP_DEBUG=true`.
- Database and boot failures are logged and answered with a generic message;
  driver text (which contains credentials and schema details) never reaches the
  browser.
- `X-Content-Type-Options: nosniff` stops content-type sniffing.
- Apache is configured to unset `X-Powered-By`.

### Clickjacking and transport

`X-Frame-Options: DENY` and CSP `frame-ancestors 'none'` are sent on every
response. `Strict-Transport-Security` is sent when `SESSION_COOKIE_SECURE` is
enabled, which is the signal that the deployment is on HTTPS.

---

## Database-level guarantees

Uniqueness and range rules that the business depends on are enforced by the
database, so a race between two requests cannot violate them:

| Rule | Mechanism |
|---|---|
| A user can like a post once | `post_likes` composite primary key |
| A user can own an item once | `user_items` composite primary key |
| A like cannot exist without its post | foreign key, `ON DELETE CASCADE` |
| Deleting a user removes their profile | foreign key, `ON DELETE CASCADE` |
| A balance cannot go negative | `UNSIGNED` **and** an explicit `CHECK` |
| A role must be one of three values | `ENUM` |
| One rewarded action per user per day | `rate_limits` composite primary key |

### Strict SQL mode is required

`STRICT_TRANS_TABLES` is set on every connection in `Database::connect()`, and
this is a security control rather than a preference.

Under non-strict mode the server *coerces* out-of-range values instead of
rejecting them: writing `-5` to an `UNSIGNED` column silently stores `0`, and an
invalid `ENUM` value silently stores the empty string. Both write data that
contradicts the schema, and neither raises an error the application can see. A
`CHECK` constraint is not even evaluated, because the value has already been
corrected by the time it would run.

XAMPP's bundled MariaDB ships with `sql_mode` set to
`NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION` — without
`STRICT_TRANS_TABLES`. This was observed directly: a probe writing `-1` to
`stardust` returned success and a warning, and the stored value was `0`. Setting
strict mode makes both cases raise a `PDOException`, which is what the
application expects.

---

## Verifying these claims

```bash
php tests/run.php                      # 78 assertions, including the security cases
php tools/lint.php                     # SQL interpolation and escaping rules
php tools/verify-deployment.php        # constraints against the real database
php tools/schema-smoke.php             # schema builds and constraints fire
```

`tools/verify-deployment.php` is the one that caught the strict-mode problem: the
SQLite suite passed because SQLite always rejects those values, while MariaDB
silently accepted them. Running the suite against the real engine is what makes
the difference visible.

---

## Reporting a problem

Open an issue describing the impact and the steps to reproduce. Please do not
include working exploit code for anything that is not already public.
