# Changelog

Notable changes to this project, newest first.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
version numbers follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Because this is a personal project rather than a library, "breaking change" means
the database schema or an interface changed in a way that requires the migration
steps in `docs/deployment.md`.

---

## [Unreleased]

### Fixed

Four defects found by checking the same code against MySQL rather than trusting a
green run on SQLite, plus one found by the new documentation gate.

- **The shop could not be used on MySQL.** `EconomyRepository::debit()` bound one
  named parameter twice, which MySQL rejects with emulated prepares off
  (`SQLSTATE[HY093]`). Every purchase failed; SQLite accepted the statement, so all
  28 economy assertions passed. A static-analysis rule now rejects a repeated named
  placeholder in a single statement, and the module checks exercise the economy on
  both engines.
- **A blog entry could fail to publish on MySQL.** Excerpts kept 320 characters and
  then appended an ellipsis, producing 321 for a `VARCHAR(320)` column: `Data too
  long for column 'excerpt'`. The length argument now means the maximum length of the
  result, and an assertion holds it to the column width.
- **Migrated and previously published entries showed raw Markdown** in the index and
  on the home page — `## 起因` and an opening code fence, presented as prose.
  `strip_tags()` removes HTML but leaves every Markdown marker. Excerpts are now
  built by `App\Support\Excerpt`, which is covered by its own test suite, and
  `bin/refresh-excerpts.php` rebuilds stored ones.
- **The audit log recorded almost nothing.** It declared actions for sign-in
  successes, failures, logouts, posts and comments, and recorded none of them: only
  the administration service wrote rows. `recentFailedLogins()` — the check the
  sign-in throttle is measured against — could only ever return zero. All five
  outcomes are recorded now, including requests the lockout refused.
- **Community posts displayed `[s:name]` tokens and unrendered Markdown.** The older
  site stored emoji as those tokens and rendered posts as Markdown; migrated content
  arrived intact and was printed as plain text. Posts now go through the same
  rendering pipeline as blog entries, with the shortcode table in one module shared
  with the composer's emoji palette.
- **A broken link in `docs/release-notes-v2.0.0.md`**, found the first time
  `tools/check-docs.php` ran.

### Added

- **`tools/check-docs.php`**: resolves every relative link and image path in the
  documentation and rejects encoding damage. Wired into CI.
- **`bin/refresh-excerpts.php`**: rebuilds the derived `excerpt` column from each
  entry's Markdown. Dry-runs by default; `--apply` writes.
- **MySQL support in `tools/module-smoke.php`**, behind an explicit
  `SMOKE_ALLOW_MYSQL=1` because it writes fixtures. It also applies its own schema,
  so it no longer needs a setup script.
- **Economy coverage in the module checks** — the module that the SQLite-only
  placeholder bug lived in was the one module the smoke script never touched.
- **Markdown and `[s:name]` emoji in community posts**, with an emoji palette in the
  composer built from the same table the renderer uses.
- **A repeatable placeholder rule in `tools/lint.php`**: a named parameter bound more
  than once in one SQL statement is reported as an error.
- **Recent entries, the broadcast and channel list on the home page**, and a cover
  column on every blog card so the listing is not ragged.
- **Encrypted demo notes, blog comments and likes, and original cover artwork** in
  `bin/seed-demo.php`, so a freshly seeded database demonstrates those pages.

### Changed

- **The screenshot pipeline reports more.** It signs out before the signed-out
  captures, checks that every image inside the viewport actually decoded, captures
  named sections that used to be cropped away, and no longer excuses a duplicate.
- **`bin/seed-demo.php` is safe to run twice**, including the announcement: the
  previous version deactivated the only broadcast on its second run and then skipped
  the insert that would have replaced it, leaving the site with none while reporting
  success. It also reports demo posts whose stored text has moved on, and accounts
  whose password is not the documented one, instead of printing credentials that do
  not work.

### Known gaps

Stated here rather than left to be discovered, because a changelog that only
lists accomplishments is a marketing document.

- Rate-limit rows are never pruned on a schedule. `RateLimitRepository::pruneBefore()`
  exists and must currently be called by hand.
- `ORDER BY RAND()` remains in one query, which sorts the whole table.
- The concurrency guarantees are enforced by the database and verified by probes,
  but there is no multi-process test that exercises them under real contention.
- API error payloads are consistently `message` in new code, but a few older
  paths still return `msg`.

---

## [2.0.0] — 2026-09-12

The first public release. It is a rebuild rather than an increment: the
application was restructured, the database redesigned, and the security model
completed.

> **Upgrading from the pre-release 1.x code.** The schema is incompatible.
> `bin/migrate-legacy.php` imports existing data, repairs the integrity problems
> it finds, and reports every repair. The old database is only read, so the
> operation is reversible. See `docs/deployment.md`.

### Added

- **Layered architecture** with a single front controller. Configuration,
  migrations and tests now live above the document root, so they are unreachable
  over HTTP by construction rather than by convention.
- **Repositories and services.** SQL is confined to `src/Repository/`, business
  rules to `src/Service/`. `tools/lint.php` enforces both boundaries.
- **Blog**: listing, detail pages addressed by slug, Markdown rendering, comments
  from members and signed-out readers, and likes.
- **Profile**: display name, bio, avatar upload, and password change.
- **Private notes**, encrypted with AES-256-GCM. The key is derived from
  `APP_KEY` and never stored in the database.
- **Notifications** for comments and likes, with a guard that stops users being
  notified about their own actions, plus broadcast to every active account.
- **Feedback** submission with administrator replies that notify the author.
- **Toolbox**: link directory, GitHub rankings served from cache, and a Steam
  discount panel proxied server-side.
- **Administration**: dashboard, user and role management, title grants,
  announcements, and an append-only audit log.
- **Test suite**: 78 unit assertions plus 64 module checks, and
  `tools/verify-deployment.php`, which asserts the schema's guarantees against a
  real database.
- **Continuous integration** across PHP 8.1, 8.2 and 8.3.
- **Docker Compose** environment with migrations applied on start.
- **Documentation**: architecture, security model, deployment, and a database
  reference.

### Changed

- **Data access moved from `mysqli` to PDO** with named placeholders, real
  prepared statements, and exceptions instead of silent failures.
- **Every relationship now uses an integer foreign key.** Posts previously
  referenced their author by username string, so renaming an account orphaned
  its content. 25 foreign keys enforce this.
- **Controlled values moved into the database.** Duplicate likes, duplicate
  purchases and negative balances are now rejected by unique and check
  constraints rather than by application code that could race.
- **CSRF verification moved into the router**, so it applies to every unsafe
  method and a new route cannot omit it. Previously two of seven endpoints
  checked it.
- **Sessions hardened**: `HttpOnly`, `SameSite`, idle timeout, identifier
  rotation on login and periodically thereafter, and a session directory the
  application owns.
- **Uploads validated by content, size and pixel count**, then re-encoded to
  WebP with a generated filename.
- **The front end moved from 20 inline script blocks to ES modules** that build
  DOM nodes instead of HTML strings.
- **Strict SQL mode is now required** on every connection. Without it the server
  silently coerces out-of-range values instead of rejecting them, which
  bypassed both `UNSIGNED` and `CHECK` constraints.

### Removed

- **The bundled WordPress installation** and its 4,000-odd core files. It shared
  the web root with the application, which exposed `wp-config.php` and the rest
  of the core tree over HTTP. Its only role — a blog editor — is covered by the
  application's own administration panel.
- **The hard-coded GitHub token** that had been committed as a fallback value in
  the configuration file. Credentials are now read from the environment with no
  fallback, and the token was rotated.

### Fixed

Defects found while rebuilding, each of which would have been visible to users:

- **Silent data corruption.** The bundled MariaDB configuration omits
  `STRICT_TRANS_TABLES`, so writing `-5` to an `UNSIGNED` column stored `0` and
  raised only a warning. `CHECK` constraints were never evaluated, because the
  value had already been corrected. SQLite rejects these values, so the test
  suite could not reproduce it; it surfaced only when the same probes ran against
  the real engine.
- **Two clocks in one comparison.** SQLite reports `CURRENT_TIMESTAMP` in UTC
  while PHP works in the application timezone, so cache expiry and the
  failed-login window were off by the timezone offset. Timestamps are now written
  and compared from one clock, applied to command-line entry points as well as
  web requests.
- **The home page never rendered the announcement the administration panel
  publishes**, so the broadcast feature had no visible effect.
- **The router matched only exact paths**, so `{slug}` routes could not work.
- **`ON DUPLICATE KEY UPDATE` is MySQL-only**; the toolbox and cache repositories
  now select the syntax by driver.
- **Login used a GET request to delete a note**, so any page could delete a
  note by loading an image whose address pointed at the action.

---

## [1.0.0] — not released

The pre-release line. It was developed in public but never tagged or supported,
and its schema is incompatible with 2.0.0. It is recorded here only so the
version history has no unexplained gap.

[Unreleased]: https://github.com/natsume05/inspiration-terminal/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/natsume05/inspiration-terminal/releases/tag/v2.0.0
