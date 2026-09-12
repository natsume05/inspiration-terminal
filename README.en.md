# Inspiration Terminal

> A personal portal and community site written in plain PHP: a blog, an anonymous
> community, a gamified currency system, and a developer toolbox.
> **No framework, no Composer dependencies, no front-end build step** — clone it
> and run it.

[![CI](https://github.com/natsume05/inspiration-terminal/actions/workflows/ci.yml/badge.svg)](https://github.com/natsume05/inspiration-terminal/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-777BB4)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-supported-4479A1)](https://mariadb.org/)
[![Tests](https://img.shields.io/badge/tests-78%20assertions-brightgreen)](tests/)
[![Licence](https://img.shields.io/badge/licence-MIT-blue)](LICENSE)

English · **[中文](README.md)**

---

![Home page](docs/images/01-home.png)

---

## What this is

A full-stack project built and actually deployed by one person in their own time.
Four things share one site:

| Area | Contents | State |
|---|---|---|
| **Blog** | Listing, detail pages, Markdown rendering, comments and likes | Complete |
| **Community** | Posts, comments, likes, category filters, image uploads | Complete |
| **Economy** | A virtual currency: daily check-in, random drops, tiered draws, equippable items | Complete |
| **Toolbox** | Curated links, GitHub ranking search, Steam discount monitor | Complete |

**What it is not.** This is not a general-purpose forum you can point at a domain
and run: there is no multi-tenancy, no plugin system, and no tiered admin
permissions. It is **one person's site**, published so the code can be read and
learned from. If you need to deploy a community, a mature forum project will serve
you far better.

---

## Scale

Every figure below can be checked against the repository; none of them is an
estimate.

```
Application PHP    61 files    9,270 lines    src/ routes/ templates/ bin/ bootstrap/ config/ public/
Tests and tooling  12 files    1,960 lines    tests/ tools/
First-party JS      6 files      531 lines    public/assets/js/ (plus 2 vendored libraries)
CSS                 1 file     1,007 lines
──────────────────────────────────────────────────────────────────
Database tables                  23
Foreign keys                     25
Unique keys                       7
CHECK constraints                 5
Routes                           45
```

**Verification**

| Check | Result |
|---|---|
| CI on PHP 8.1 / 8.2 / 8.3 | Passing |
| Unit assertions | 78 |
| Module checks | 64, repeatable |
| Constraint probes against a real database | 6 of 6 |
| Static analysis | 0 violations across 69+ files |

---

## Stack

| Layer | Choice | Notes |
|---|---|---|
| Language | PHP 8.1+ | `declare(strict_types=1)`, PSR-4 autoloading |
| Database | MySQL 8 / MariaDB | Hand-written SQL, PDO named placeholders, 23 tables |
| Data access | PDO | Emulated prepares disabled, exceptions enabled, transaction helper |
| Sessions | Native, wrapped | Own storage directory, idle timeout, identifier rotation |
| Front end | HTML / CSS / ES modules | Flexbox and Grid. **No bundler, no framework** |
| Images | GD | Content sniffing → resize (alpha preserved) → WebP |
| Third-party APIs | cURL | GitHub REST and CheapShark, proxied server-side and cached in the database |
| Markdown | marked + DOMPurify | `marked` → **sanitise** → DOM |
| Testing | Small in-repo framework | Zero dependencies; runs straight after a clone |
| Deployment | Apache / Nginx / Docker | Document root points at `public/` |

### Why no framework

The project is not large enough to earn a framework's overhead, and building it
this way meant writing the request lifecycle, the SQL boundary, sessions and the
security model by hand. That has a real cost, and it is worth stating plainly:
**everything a framework normally does for you — dependency injection, routing,
migrations, automatic template escaping — had to be written correctly here.** That
is precisely why the tests and the documentation exist. They are not decoration;
they are how correctness was established.

If you are choosing a stack: **for anything larger than this, or for more than one
developer, use a framework.**

---

## Three decisions worth explaining

### 1. A constraint the database can express does not belong in application code

Liking, purchasing and check-in all began as read-then-write. That pattern fails
under concurrency: two requests can both read "no row exists" and both insert. It
also fails quietly — rarely enough in testing that it looks correct until real
contention appears.

Those rules now live in the schema:

| Rule | Mechanism |
|---|---|
| One like per user per post | `post_likes` composite primary key `(user_id, post_id)` |
| One of each item per user | `user_items` composite primary key |
| A balance cannot go negative | `UNSIGNED` **and** an explicit `CHECK (stardust >= 0)` |
| Deleting a post removes its likes | Foreign key `ON DELETE CASCADE` |
| A capped number of actions per day | `rate_limits` primary key `(user_id, action, window_date)` |

The code issues `INSERT IGNORE` (MySQL) or `ON CONFLICT DO NOTHING` (SQLite) and
then **reads the affected row count to learn whether anything was inserted**, rather
than trusting its own earlier query.

### 2. Cross-cutting concerns belong in the framework layer

CSRF verification was originally written into each endpoint, and only two of the
seven state-changing endpoints had it — the password change among the missing.

Verification now happens **once, in the router**, for every unsafe method, before
any handler runs. "A new route that forgets the check" is no longer possible,
because remembering is no longer part of the job.

### 3. A test should ask whether it exercises the same engine as production

The suite runs on SQLite against a translation of the production MySQL schema: it
is fast and needs no server. But a probe against the real engine found something
SQLite can never reproduce:

> The MariaDB bundled with XAMPP sets `sql_mode` without `STRICT_TRANS_TABLES`.
> In non-strict mode the server **silently coerces** out-of-range values: writing
> `-5` to an `UNSIGNED` column stores `0` and raises only a warning. More
> importantly, **`CHECK` constraints are never evaluated**, because the value has
> already been corrected by the time they would run.

The fix sets strict mode on every connection, and the probe is kept as
`tools/verify-deployment.php` so it stays part of acceptance. **Passing tests are
not the same as being correct** — the most useful lesson this project taught me.

---

## Security

The full threat model and its limits are in [`docs/security.md`](docs/security.md).

| Threat | Control |
|---|---|
| SQL injection | PDO named placeholders and real prepared statements; `LIMIT`/`OFFSET` bound explicitly as integers; static analysis rejects interpolated SQL |
| XSS | Templates print through `View::escape()`, enforced by static analysis; the client builds DOM nodes with `textContent`; CSP restricts `script-src` to `'self'` |
| CSRF | Enforced centrally in the router; 32 random bytes compared with `hash_equals`; rotated on sign-in and sign-out; `SameSite=Lax` as a second layer |
| Session fixation | Identifier rotated on sign-in and every 15 minutes; two-hour idle timeout; `use_strict_mode` |
| Password cracking | `password_hash(PASSWORD_DEFAULT)`; five failed attempts lock the address and username pair for 15 minutes |
| User enumeration | An unknown username still runs a password verification, so response timing does not reveal whether the account exists |
| Broken access control | Every user-owned query puts `user_id` **in the SQL condition**, rather than fetching and then checking in PHP |
| Malicious uploads | Seven checks in order: transport, origin, size, real content type, pixel count, re-encode, and storage outside the document root |
| Credential leakage | Secrets come only from the environment, with **no hard-coded fallback** |
| Clickjacking | `X-Frame-Options: DENY` and CSP `frame-ancestors 'none'` |

**Known limits**, stated because they are deliberate rather than overlooked:

- Private notes are encrypted with AES-256-GCM using a key held in the
  environment. That protects against a database dump, **not** against an attacker
  who controls the host; losing `APP_KEY` makes the notes permanently unreadable.
- Rate limits live in the database, so a multi-instance deployment needs shared
  storage such as Redis.
- There is no second factor and no upstream WAF.

---

## Getting started

### Docker (nothing to install)

```bash
git clone https://github.com/natsume05/inspiration-terminal.git
cd inspiration-terminal
cp .env.example .env
# generate an application key and put it in APP_KEY
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
docker compose up -d
```

Open <http://localhost:8080> and sign in with `demo@example.com` / `demo-password`.

### A local PHP install

Requires PHP 8.1+ with `pdo_mysql` (or `pdo_sqlite`), `mbstring` and `json`.
Image handling needs `gd`, the third-party integrations need `curl`, and note
encryption needs `openssl`.

```bash
cp .env.example .env      # fill in APP_KEY and DB_*
php bin/migrate.php       # create the schema
php bin/seed-demo.php     # optional sample content
php -S 127.0.0.1:8080 -t public
```

> **The document root must be `public/`.** `public/index.php` is the only entry
> point; configuration, migrations and tests sit above it and are therefore
> unreachable over HTTP.

### Just want to look at the interface

```bash
php bin/dev-sqlite.php
DB_DRIVER=sqlite DB_SQLITE_PATH=storage/dev.sqlite \
APP_KEY=0123456789abcdef0123456789abcdef \
php -S 127.0.0.1:8080 -t public
```

<details>
<summary>On Windows, <code>php</code> is not recognised?</summary>

XAMPP does not add PHP to `PATH`. Call it by full path, or add it for the session:

```powershell
D:\XAMPP\php\php.exe bin\migrate.php --status

# or
$env:Path += ';D:\XAMPP\php'
php bin\migrate.php --status
```
</details>

---

## Screenshots

| | |
|---|---|
| ![Blog](docs/images/02-blog.png) | ![Blog entry](docs/images/07-blog-entry.png) |
| Blog listing with covers and excerpts | Entry detail: Markdown, comments and likes |
| ![Community](docs/images/08-community.png) | ![Private notes](docs/images/09-notes.png) |
| Community feed: posting, likes, collapsible comments | Private notes, encrypted with AES-256-GCM |
| ![Toolbox](docs/images/15-tools-signed-in.png) | ![Admin](docs/images/13-admin.png) |
| Toolbox: links and rankings | Administration: announcements, entries, accounts, feedback |
| ![GitHub](docs/images/04-tools-github.png) | ![Steam](docs/images/05-tools-steam.png) |
| GitHub rankings from cache, searched locally | Steam discounts and the seasonal sale calendar |
| ![Mobile](docs/images/16-mobile-community.png) | ![Audit](docs/images/14-admin-audit.png) |
| Mobile layout | Audit log of consequential actions |

Every screenshot is captured from the running site by
[`tools/capture-screenshots.mjs`](tools/capture-screenshots.mjs), including the
pages behind sign-in. The script refuses to produce duplicate images, because the
failure where every screenshot turns out identical is invisible in the images
themselves.

### Live site

The production deployment is at **<https://367588.xyz>**. Two caveats: it is a
**personal site rather than a stable demo**, so its content may change at any time
and it may be mid-deployment; and if you want to see *this* code, the Docker route
is the reliable way.

---

## Layout

```
inspiration-terminal/
├── public/                 # The only web root
│   ├── index.php           # Front controller
│   └── assets/             # CSS, ES modules, images
├── src/
│   ├── Database/           # Connection, migrator, dialect adapter, legacy import
│   ├── Http/               # Request, response, router, kernel, view
│   ├── Repository/         # Data access — the only place SQL may appear
│   ├── Security/           # Session, CSRF, validation, crypto, uploads, headers
│   ├── Service/            # Business rules
│   └── Support/            # Configuration and environment
├── routes/web.php          # Route table
├── templates/              # Views
├── database/migrations/    # Migrations, applied in order
├── tests/                  # Test suite
├── tools/                  # Static analysis, screenshots, deployment, verification
├── bin/                    # Command-line entry points
└── docs/                   # Documentation
```

**Dependencies point one way**, and `tools/lint.php` enforces it mechanically:

```
Http → Service → Repository → Database
                    ↑
              Security (usable from any layer)

SQL only in Repository
Business rules only in Service
Templates render; every value they print is escaped explicitly
```

---

## Documentation

| Document | Contents |
|---|---|
| [`docs/project-overview.md`](docs/project-overview.md) | The whole picture: 23-table design, request flow, four key data flows |
| [`docs/security.md`](docs/security.md) | Threat model, controls, and the limits of each |
| [`docs/deployment.md`](docs/deployment.md) | From a local checkout to production, with a checklist |
| [`docs/interview-questions.md`](docs/interview-questions.md) | Forty questions on this project's real design and defects |
| [`CHANGELOG.md`](CHANGELOG.md) | Release history |
| [`SECURITY.md`](SECURITY.md) | How to report a vulnerability, and what is in scope |
| [`NOTICE`](NOTICE) | Third-party components and media licensing |

---

## Working on it

```bash
php tests/run.php              # full suite
php tests/run.php --verbose     # per-assertion detail
php tools/lint.php              # static analysis: layering, SQL interpolation, template escaping
php tools/lint.php --verbose    # also lists accepted template output, with the reason
php bin/migrate.php --status    # migration state
php tools/verify-deployment.php # assert the schema's guarantees against the real database
```

One rule in the static analysis deserves a note. Template output must be escaped,
and the rule reports an expression when it *could* produce untrusted content while
accepting ones that demonstrably cannot — a conditional whose branches are string
literals, an integer, a URL-encoded component — and every acceptance is listed
under `--verbose` rather than passing silently. **A rule that cries wolf gets
ignored, which is worse than having no rule at all.**

---

## Known limits

Stated plainly, because they are choices rather than oversights:

- **No framework**, so routing and templating are deliberately small. Use a
  framework for anything larger.
- **No queue.** Image processing and third-party calls happen inside the request.
- **Rate-limit rows are not pruned on a schedule**; `pruneBefore()` is called by hand.
- **The concurrency guarantees come from database constraints and probes**, and
  there is no multi-process integration test yet.
- **The interface is Chinese only**; there is no i18n.
- **`bin/migrate-legacy.php` supports MySQL only** — by definition it reads from an
  older MySQL installation, so unlike the repositories it has no SQLite counterpart
  to stay compatible with.

---

## Contributing

**This is a personal project and does not accept external pull requests.** The code
is published to be read, not to be developed in common, and saying so is better than
offering a promise that cannot be kept.

If you have found a **security issue**, please do not open a public issue — follow
the process in [`SECURITY.md`](SECURITY.md). For an ordinary bug or a question about
the design, an issue is welcome.

---

## Licence

- **Code**: [MIT](LICENSE) — use it freely, including commercially.
- **Site content** (articles, screenshots, copy, images):
  [CC BY-NC-ND 4.0](LICENSE-CONTENT) — quote and republish with attribution and a
  link; no commercial use, no distribution of modified versions.
- **Third-party components**: see [`NOTICE`](NOTICE). The bundled `marked` (MIT) and
  DOMPurify (Apache-2.0) retain their own licences.

---

<p align="center">
  <sub>The interface borrows visual and naming cues from several games. Those are
  stylistic references in a personal project, not affiliation with or endorsement by
  any of those games or their publishers.</sub>
</p>
