# Security policy

## Reporting a vulnerability

**Please do not open a public issue for a security problem.**

Use GitHub's private reporting flow: go to the repository's **Security** tab and
choose **Report a vulnerability**. That opens a private advisory visible only to
you and the maintainer.

If that flow is unavailable, open a normal issue that says only "I would like to
report a security issue" with no technical detail, and a private channel will be
arranged.

A useful report includes:

- what an attacker can do, and what they need in order to do it
- the shortest steps that reproduce it
- the affected version or commit
- any proof-of-concept you are willing to share

Please allow reasonable time for a response before disclosing publicly. This is
a personal project maintained by one person, not a staffed product team — an
honest expectation is measured in days, not hours.

## Supported versions

| Version | Supported |
|---|---|
| 2.x | Yes |
| 1.x | No — the 1.x line was never released, and its schema is incompatible |

Fixes are applied to the latest 2.x release. Because 1.x was never published and
uses a different database schema, there is no upgrade path from it that does not
go through the migration described in `docs/deployment.md`.

## What has already been considered

The controls in place, and — more usefully — the limits of each, are documented
in [`docs/security.md`](docs/security.md). That document states the threat model
explicitly, including what the private-note encryption does **not** protect
against.

Read it before reporting, because several plausible-sounding issues are known,
deliberate trade-offs:

- **Private notes are encrypted with a key held in the environment.** This
  protects against a database dump or a leaked backup. It does **not** protect
  against an attacker who has both `APP_KEY` and the database, and losing
  `APP_KEY` makes the notes permanently unreadable. This is a stated trade-off,
  not an oversight.
- **Rate limiting is stored in the database.** A multi-instance deployment needs
  shared storage (such as Redis) for the limits to hold across instances.
- **No second factor.** Authentication is a password plus a failed-login lockout.
- **No upstream WAF.** Throttling is application level.

## Out of scope

- Denial of service by traffic volume
- Attacks requiring an already-compromised host, or a malicious database
  administrator
- Vulnerabilities in PHP, MySQL/MariaDB, or the web server itself — report those
  upstream
- The configuration of a specific deployment, such as leaving `APP_DEBUG` on

## If you are running this yourself

The [deployment guide](docs/deployment.md) ends with a checklist. The three items
that matter most:

1. `APP_DEBUG=false` in production. It prints stack traces.
2. `APP_KEY` set, backed up, and never committed.
3. `SESSION_COOKIE_SECURE=true` whenever the site is served over HTTPS.

Also rotate any credential that has ever been committed. Removing it from the
working tree does not remove it from the history, and history is permanent.
