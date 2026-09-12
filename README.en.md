# Inspiration Terminal

> A personal portal and community site written in plain PHP: a blog, an anonymous
> community, a gamified currency system, and a developer toolbox.
> **No framework, no Composer install, no front-end build step** — clone it and run it.

[![CI](https://github.com/natsume05/inspiration-terminal/actions/workflows/ci.yml/badge.svg)](https://github.com/natsume05/inspiration-terminal/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-777BB4)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-supported-4479A1)](https://mariadb.org/)
[![Tests](https://img.shields.io/badge/tests-92%20assertions-brightgreen)](tests/)
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
| **Blog** (深空日志) | Listing, detail pages, Markdown rendering, comments and likes | Complete |
| **Community** (虚空枢纽) | Posts, comments, likes, category filters, image uploads, Markdown and emoji | Complete |
| **Economy** (虚空经济) | A virtual currency: daily check-in, random drops, tiered draws, equippable items | Complete |
| **Toolbox** (提瓦特百宝箱) | Curated links, GitHub ranking search, Steam discount monitor | Complete |

**What it is not.** This is not a general-purpose forum you can point at a domain
and run: there is no multi-tenancy, no plugin system, and no tiered admin
permissions. It is **one person's site**, published so the code can be read and
learned from. If you need to deploy a community, a mature forum project will serve
you far better.

**Three things worth knowing up front.** The interface is Chinese only — the section
names above are given in Chinese because that is what you will see on screen. The
repository has no runtime dependencies: `composer.json` records the PHP version and
extensions required, and **`composer install` is not needed**. There is no build step
either; what sits in `public/` is what the browser receives.

The production site is at **<https://367588.xyz>**. It is a **personal site rather
than a stable demo**: content changes, and it may be mid-deployment. To see *this*
code, use the Docker route.

---

## What is in it

### Blog

- **Listing** with cover art, excerpt, author and comment count, newest first,
  paginated.
- **Detail pages addressed by slug** (`/blog/把数据一致性下沉到数据库层-1`), so links
  stay stable instead of exposing an auto-incrementing id.
- **Markdown rendering that sanitises afterwards**: `marked` converts, then
  `DOMPurify` cleans. The order is not interchangeable — `marked` performs no
  sanitisation of its own and Markdown permits raw HTML, so inserting its output
  directly would be a cross-site-scripting hole.
- **Comments open to signed-out readers**, who supply a name; signed-in readers get
  their current display name. The name is captured when the comment is written, so
  renaming an account does not rewrite its history.
- **Likes** deduplicated by a `(blog_post_id, user_id)` composite primary key.

### Community

- **Posts with images**, converted to WebP by GD on upload.
- **Post bodies share the blog's rendering pipeline** — `marked`, then `DOMPurify`.
  This was not a nicety: the older site stored Markdown and the occasional pasted
  HTML document in post bodies, so rendering them as plain text produced `## heading`
  and bare tags in the middle of the feed.
- **`[s:name]` emoji.** The older site stored emoji as those tokens and the migrated
  content is full of them. The table lives in one module, `assets/js/emojis.js`, shared
  by the renderer and the composer's palette, so the two cannot disagree about what a
  token means. **A token the table does not know is left exactly as typed** instead of
  being deleted from the middle of a sentence.
- **Post text is rendered server-side first.** If the bundles fail to load, the reader
  sees the text rather than an empty box: "rendering failed" and "this post is empty"
  are not the same thing.
- **Category filters**: daily, games, code, and the void.
- **Comments load on demand** from a JSON endpoint rather than being rendered into
  the page wholesale.
- **Likes can trigger a random drop** of currency — at most once per day.

### Economy

The most carefully designed part of the project, and the easiest to get wrong.

- **Daily check-in** grants a random 20–50 stardust plus 20 experience. It is
  idempotent because the composite primary key of `rate_limits` refuses the second
  attempt rather than paying out twice.
- **Comment and post rewards are capped per day.** An earlier version had no cap
  here: posting a one-character comment in a loop minted unlimited currency and
  would have collapsed the economy. That was a real defect, since fixed, not a
  hypothetical one.
- **Tiered daily draw.** Sixty percent pays stardust; the rest draws an unowned item
  by rarity, and falls back to stardust when a rarity is exhausted, so there is no
  such thing as a prize with nothing behind it.
- **Equippable items**, mutually exclusive within a type, split into name effects,
  avatar frames and badges. One `UPDATE ... JOIN` clears the previous item of the
  same type and a second equips the new one, both inside a transaction.
- **Balances cannot go negative**: the column is `UNSIGNED` *and* carries an
  explicit `CHECK (stardust >= 0)`. Two constraints on purpose — `UNSIGNED` behaves
  according to the server's `sql_mode`, while the check does not.

### Toolbox

- **Curated links** by category, with a generic icon substituted when a link has
  none rather than leaving a blank space.
- **GitHub explorer**: two rankings — repositories gaining stars this week, and the
  all-time most-starred — served from a **local cache**, searchable by name,
  description and language. The cache exists so the API quota is never spent, which
  also means the panel works with no token configured.
- **Steam panel**: discount data is **proxied server-side** and cached, so the
  browser never calls the third party directly. The seasonal calendar is editorial
  content and stays available when the upstream API does not. On an upstream failure
  the last successful data is shown instead of an empty panel.

### Profile, private notes and administration

- Change display name and bio, upload an avatar, reset the password. **Resetting it
  rotates the session identifier**, so a session established with the old credential
  stops working.
- **Private notes** (思维殿堂) are sealed with AES-256-GCM under a key derived from
  `APP_KEY` that is never stored in the database, so a database dump reveals nothing.
  The trade-off is stated where it is implemented: losing `APP_KEY` makes the notes
  permanently unreadable.
- **Administration** (舰长控制台): announcements that appear on the home page, blog
  publishing, link management, account roles and titles, feedback replies, and an
  append-only audit log at `/admin/audit`. A reply to feedback notifies the author. An
  administrator **cannot change their own role** — that can leave a site with nobody
  able to administer it, and it cannot be undone from the interface.
- **The audit log records sign-in outcomes, not just edits**: successes, failures and
  logouts, alongside posts, comments, announcements, blog entries and link changes.
  The sign-in throttle is keyed on the client address and the account together, and
  without those rows there is no way to answer which accounts an address was guessing,
  or whether it moved on after the lockout expired. A request the lockout refused is
  recorded too.

### Notifications (信号记录)

Comments, likes, rewards and broadcasts all produce a notification, with an unread
count in the navigation. Nobody is notified about their own action, and that rule
lives in the repository so a new caller cannot forget it.

---

## Scale

Every figure below is counted, not estimated: each one can be reproduced in this
repository with `wc -l`. Lines are counted by newline, so the minified bundles are
not part of the first-party figure.

```
Application PHP    63 files   11,691 lines    src/ routes/ templates/ bin/ bootstrap/ config/ public/
Tests and tooling  16 files    3,139 lines    tests/ tools/
First-party JS      8 files      893 lines    public/assets/js/ (plus 2 vendored bundles)
CSS                 1 file     1,678 lines    public/assets/css/
──────────────────────────────────────────────────────────────────
Database tables                  23
Foreign keys                     25
Unique keys                       7
CHECK constraints                 5
Routes                           45
```

**Verification**

The results below are from the current working tree, not from a past build.

| Check | Result |
|---|---|
| CI on PHP 8.1 / 8.2 / 8.3 | Passing |
| Unit assertions | 92 across 3 suites |
| Module checks | 83, repeatable |
| Constraint probes against a real database | 6 of 6 |
| Static analysis | 0 violations across 75 files |
| Documentation links and encoding | 10 documents, 61 local links |

Both the module checks and the static analysis can run against **MySQL**, not only
SQLite. Making that possible took deliberate work, and it paid for itself: three
defects in this project fail only on MySQL and every one of them "passed" on SQLite.
See [the third design note](#3-a-test-should-ask-whether-it-exercises-the-same-engine-as-production).

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
any handler runs. A new route cannot omit the check, because the check no longer
depends on its author remembering to add one.

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

The same lesson arrived twice more. The connection disables prepared-statement
emulation (`ATTR_EMULATE_PREPARES => false`), so MySQL refuses a statement that binds
**the same named placeholder twice**, while SQLite is happy with it:
| Where | Consequence | What SQLite showed |
|---|---|---|
| `EconomyRepository::debit()` | `HY093` on every purchase, so **the shop was unusable** | 28 economy assertions passing |
| The like counter in `bin/seed-demo.php` | Seeding stopped at the eighth post | The same script ran clean |
| `Excerpt::from()` keeping one character too many | `Data too long for column` when writing `VARCHAR(320)` | Long values stored without complaint, then silently truncated |

The third is worth remembering for how small it was: the excerpt took 320 characters
and then appended an ellipsis. SQLite does not care; MySQL refuses the row. The length
argument now means "maximum length of the result" rather than "characters kept", and an
assertion holds it to the column width.

The first two are now guarded by a **static-analysis rule**: a repeated named
placeholder in one SQL statement is an error. It found `debit()` the moment it was
added. The module checks also gained the economy module, which they had never touched,
and can be pointed at MySQL — which is what "test against the same engine" means,
written out in full.

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

Open <http://localhost:8080> and sign in with **`MingMo` / `demo-password`**.
(The account is created with the email `demo@example.com`, but sign-in accepts the
username, not the email address.)

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

This path only builds the schema and a minimal data set; sign in with
**`MingMo` / `inspiration-dev-password`**. The public pages — home, blog, toolbox —
need no sign-in at all.

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
| ![Home](docs/images/01-home.png) | ![Blog](docs/images/02-blog.png) |
| Home: broadcast, latest entries, channels | Blog listing: covers, excerpts, pagination |
| ![Blog entry](docs/images/07-blog-entry.png) | ![Comments](docs/images/07b-blog-entry-comments.png) |
| Entry detail: rendered Markdown and code | The foot of the same entry: likes and replies |
| ![Community](docs/images/08-community.png) | ![Private notes](docs/images/09-notes.png) |
| Community: posts rendered from Markdown and `[s:name]` | Private notes, sealed with AES-256-GCM |
| ![Toolbox](docs/images/03-tools.png) | ![GitHub](docs/images/04-tools-github.png) |
| Toolbox: links grouped by category | GitHub explorer: cached rankings, local search |
| ![Steam](docs/images/05-tools-steam.png) | ![Sale calendar](docs/images/05b-tools-steam-calendar.png) |
| Steam panel: discounts and both prices | The same page, scrolled to the sale calendar |
| ![Profile](docs/images/11-profile.png) | ![Admin](docs/images/13-admin.png) |
| Profile: titles, avatar and currency balance | Administration: dashboard, broadcast, publishing |
| ![Accounts](docs/images/13b-admin-accounts.png) | ![Audit](docs/images/14-admin-audit.png) |
| Administration: accounts, roles and status | Audit log: sign-ins, posts and edits |
| ![Notifications](docs/images/10-notifications.png) | ![Mobile](docs/images/16-mobile-community.png) |
| Signal log: notifications and read state | Mobile layout: same templates, one column |

The capture run produces **20** screenshots in total — the sign-in page, the feedback
form and the mobile home page among them. All of them are in
[`docs/images/`](docs/images/); the table above shows sixteen.

Every screenshot is captured from the running site by
[`tools/capture-screenshots.mjs`](tools/capture-screenshots.mjs) — the public pages
signed out, the rest signed in. The script refuses three things it has no business
allowing:

- **Duplicate images.** It compares the byte size of every capture, because a run that
  produced seventeen copies of the same blank page is invisible in the images themselves.
- **Images that did not decode.** A failed `<img>` still occupies its layout slot, so the
  page looks fine with a correctly sized hole in it. The Steam covers come from a
  third-party CDN, and this check exists because of them.
- **The wrong sign-in state.** It signs out before the signed-out captures, because a
  session left in the browser profile once turned the sign-in page into the community
  page.

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
├── bootstrap/              # Autoloading and runtime initialisation
├── config/app.php          # Configuration (values come from the environment)
├── tests/                  # Test suite
├── tools/                  # Static analysis, documentation check, screenshots, deployment
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
| [`docs/release-notes-v2.0.1.md`](docs/release-notes-v2.0.1.md) | The v2.0.1 release notes: four defects that only appear on MySQL |
| [`docs/release-notes-v2.0.0.md`](docs/release-notes-v2.0.0.md) | The v2.0.0 release notes, including upgrade cautions |
| [`SECURITY.md`](SECURITY.md) | How to report a vulnerability, and what is in scope |
| [`NOTICE`](NOTICE) | Third-party components and media licensing |
| [`LICENSE`](LICENSE) | Licence for the code: MIT |
| [`LICENSE-CONTENT.md`](LICENSE-CONTENT.md) | Licence for the writing and site content: CC BY-NC-ND 4.0 |

The two licences are separate on purpose: the code is free to take, the prose and
articles are not. The code file keeps the plain `LICENSE` name because **hosting
platforms only recognise that filename** — calling it `LICENSE-CODE.md` makes the
repository report its licence as `NOASSERTION`, which is worse than an untidy name.

Both READMEs are maintained by hand, so the English one may lag behind the Chinese
one; where they disagree, the Chinese version is authoritative. `tools/check-docs.php`
verifies that every relative link and path resolves and that the encoding is intact —
it cannot tell you the two files have drifted apart.

---

## Working on it

```bash
php tests/run.php              # full suite
php tests/run.php --verbose     # per-assertion detail
php tools/lint.php              # static analysis: layering, SQL interpolation, template escaping, placeholder reuse
php tools/lint.php --verbose    # also lists accepted template output, with the reason
php tools/doctor.php            # check the configuration: keys, database, cache, permissions
php tools/check-docs.php        # documentation links and encoding
php bin/migrate.php --status    # migration state
php bin/fetch-github.php        # populate the GitHub ranking cache
php tools/verify-deployment.php # assert the schema's guarantees against the real database
php bin/seed-demo.php           # insert data that is worth screenshotting and demoing
php bin/refresh-excerpts.php    # rebuild blog excerpts from the current rules (dry run; --apply writes)
```

### Running the module checks against MySQL

They default to SQLite. Pointing them at the production engine takes four environment
variables. **They write fixture rows**, so the target must be a throwaway database and
the confirmation has to be explicit:

```bash
mysql -uroot -e 'CREATE DATABASE inspiration_smoke CHARACTER SET utf8mb4'
DB_DRIVER=mysql DB_DATABASE=inspiration_smoke SMOKE_ALLOW_MYSQL=1 php tools/module-smoke.php
```

Without `SMOKE_ALLOW_MYSQL=1` it refuses to start, rather than writing sample accounts
and test posts into whatever database it was handed. It applies its own schema, so no
other script has to run first.

### Four tools worth a paragraph

**`tools/doctor.php`** exists for a class of mistake that **fails silently**.
`APP_KEY` accepts any string of sixteen characters or more, so pasting a GitHub token
into it leaves the site running and encryption "succeeding" — with a credential that
also sits in the GitHub UI and your shell history now serving as the key for private
notes. Nothing complains, and the cost only surfaces when you rotate. The script points
it out, and checks database connectivity, migration state, whether the cache is empty,
and whether the session directory is writable while it is there. It **reports failures
through its exit code**, so a deployment step can act on it.

**`tools/lint.php`** enforces one rule worth naming: template output must be escaped.
It reports an expression when it *could* produce untrusted content and accepts ones that
demonstrably cannot — a conditional whose branches are string literals, an integer, a
URL-encoded component — and every acceptance is listed under `--verbose` rather than
passing silently. **A rule that fires too often stops being read, which is worse than
having no rule at all.**

**`tools/check-docs.php`** checks the one file nobody compiles: the README. It resolves
every relative link and image path in the documentation and looks for encoding damage.
It found a defect the day it was added — `docs/release-notes-v2.0.0.md` linked to
`docs/deployment.md` from inside `docs/`, so the target was really
`docs/docs/deployment.md`. **The front page is the front page, and nothing else was
checking it.**

**`bin/refresh-excerpts.php`** deals with the inevitable consequence of a derived
column: an entry's excerpt is computed from its Markdown, so changing the rules leaves
the old values in the database — which is how migrated entries came to advertise `## 起因`
and an opening code fence in the index. It dry-runs by default and lists what it would
change; `--apply` writes. It is safe on a live database and converges on a second run.
It also tripped over itself first: the original version compared the column in its
`WHERE` clause against the value it had just read out of that same column, so the
condition was always false and it reported seven rebuilds it had never written.

---

## Roadmap

This is not a list of shortcomings; it is where the project goes next. Two kinds of
thing are separated on purpose: the **deliberate trade-offs** are costs that were
considered and accepted, and the **unfinished** list is work that genuinely is not done.

### Deliberate trade-offs

| Trade-off | Why |
|---|---|
| **No framework** | See [why no framework](#why-no-framework) above: the project is too small to earn a framework's overhead, and writing the request lifecycle by hand was the point |
| **Synchronous request handling** | There is no queue. Image compression and third-party calls happen inside the request, so a slow upstream slows one response |
| **Rate limits in the database** | It avoids another component to run. The cost is that a multi-instance deployment needs shared storage |
| **Upstream data cached in the database** | It keeps the API rate limit untouched, works with no token, and keeps the page up when the upstream is down. The cost is that rankings lag |
| **Chinese interface only** | This is a Chinese-language site, not a missing translation |
| **The legacy migration reads MySQL only** | Its input is by definition an older MySQL installation, so unlike the repositories it has no SQLite counterpart to stay compatible with |

### Unfinished

Ordered by the cost of leaving it undone, not by how hard it is.

1. **Scheduled pruning of the rate-limit table.** `RateLimitRepository::pruneBefore()`
   exists and must currently be called by hand, so the table only grows.
2. **A real concurrency test.** The guarantees come from database constraints and
   from probes — reasoned about carefully and verified — but no test yet starts two
   processes against the same endpoint. The intent is to replace inference with
   measurement.
3. **One remaining `ORDER BY RAND()`** (the random entry jump), which sorts the whole
   table. A random id range or a precomputed column is the fix.
4. **Consistent API error fields.** New code returns `message`; a few older paths still
   return `msg`.

### Considered and declined

- **Multi-tenancy**: that is a different product, not this one.
- **A plugin system**: a plugin protocol for a one-person site adds complexity with
  nobody to benefit from it.
- **Finer-grained admin permissions**: three levels (member, moderator, administrator)
  are sufficient; more would only make every check harder to reason about.

---

## Contributing

**This is a personal project and does not accept external pull requests.** The code is
published to be read, not to be developed in common.

If you have found a **security issue**, please do not open a public issue — follow
the process in [`SECURITY.md`](SECURITY.md). For an ordinary bug or a question about
the design, an issue is welcome.

---

## Licence

- **Code**: [MIT](LICENSE) — use it freely, including commercially.
- **Site content** (articles, screenshots, copy, images):
  [CC BY-NC-ND 4.0](LICENSE-CONTENT.md) — you may quote and repost it with attribution
  and a link; no commercial use, and no distributing modified versions.
- **Third-party components**: see [`NOTICE`](NOTICE). The bundled `marked` (MIT) and
  DOMPurify (Apache-2.0) retain their own licences.

---

<p align="center">
  <sub>The interface borrows visual and naming cues from several games. Those are
  stylistic references in a personal project, not affiliation with or endorsement by
  any of those games or their publishers.</sub>
</p>
