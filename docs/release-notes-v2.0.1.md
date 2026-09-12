# v2.0.1 — defects found by testing against MySQL

v2.0.0 was green on every local gate and shipped anyway. This release is what
running the same checks against the engine production actually uses turned up: four
defects that SQLite cannot reproduce, and one that the new documentation check found
on its first run.

If you deployed v2.0.0, **upgrade** — one of these makes the item shop unusable.
There are no schema changes and no upgrade steps beyond replacing the files.

## The shop did not work on MySQL

`EconomyRepository::debit()` bound a single named parameter twice:

```sql
UPDATE user_profiles SET stardust = stardust - :amount
 WHERE user_id = :user_id AND stardust >= :amount
```

The connection disables prepared-statement emulation (`ATTR_EMULATE_PREPARES =>
false`), and MySQL rejects a repeated named placeholder with
`SQLSTATE[HY093]: Invalid parameter number`. Every purchase failed. SQLite accepts
the statement, so all 28 economy assertions passed and nothing else noticed — the
module checks did not cover the economy at all.

`bin/seed-demo.php` carried the same mistake in its like-counter update, which is how
it surfaced: seeding stopped at the eighth post on MySQL and ran clean on SQLite.

`tools/lint.php` now rejects a repeated named placeholder in a single SQL statement.
It found `debit()` the moment it was added. The rule reads whole string literals
rather than lines, because SQL here spans several lines and only the complete
statement shows the repeat.

## A blog entry could fail to publish on MySQL

Excerpts kept 320 characters and *then* appended an ellipsis — 321 for a
`VARCHAR(320)` column, which is `Data too long for column 'excerpt'`. SQLite does not
enforce column widths, so the value was written and quietly truncated instead.

The length argument now means the maximum length of the result rather than the number
of characters kept, and an assertion holds it to the column width.

## The audit log recorded almost nothing

`AuditRepository` declared actions for sign-in successes, failures, logouts, posts and
comments, and recorded none of them; only the administration service ever wrote a row.
`recentFailedLogins()` — the counter the sign-in throttle is measured against — could
only ever return zero, so a brute-force attempt was invisible by construction.

All five outcomes are recorded now, including a request the lockout refused, because
that is the clearest evidence that a client address is still guessing. The page also
stopped being an empty state on a site that had been running for months.

## Community posts showed `[s:name]` tokens and raw Markdown

The older installation stored emoji as `[s:name]` tokens and rendered posts as
Markdown. Migrated content arrived intact and was printed as plain text, so real posts
displayed `## heading`, code fences and pasted HTML tags as literal characters.

Posts now go through the same `marked` → `DOMPurify` pipeline as blog entries. The
shortcode table lives in one module, `assets/js/emojis.js`, shared by the renderer and
the composer's new emoji palette, so the two cannot disagree about what a token means.
A token the table does not know is left exactly as typed rather than deleted from the
middle of a sentence.

Post text is also rendered server-side first, so a reader whose browser never loads the
bundles sees the text instead of an empty box.

## Excerpts showed Markdown markers in every listing

`strip_tags()` removes HTML but leaves every Markdown marker, which is why the blog
index and the home page advertised `## 起因` and an opening code fence as though the
renderer had failed. `App\Support\Excerpt` builds plain text from Markdown and has its
own test suite; `bin/refresh-excerpts.php` rebuilds the values already in the database,
dry-running by default so you can see what it would change.

## What else changed

- `tools/module-smoke.php` can run against MySQL behind an explicit
  `SMOKE_ALLOW_MYSQL=1`, applies its own schema, is repeatable on both engines, and now
  covers the economy — the module the placeholder defect lived in was the one it never
  touched.
- `tools/check-docs.php` resolves every relative link and image path in the
  documentation, validates anchors against the headings in the same file, and rejects
  encoding damage. It is wired into CI and found a real broken link on its first run.
- `bin/seed-demo.php` is safe to run twice. The previous version deactivated the only
  announcement on its second run and then skipped the insert that would have replaced
  it, leaving the site with none while reporting success.
- The documentation screenshots were recaptured from this code with the demo data
  seeded, and the capture script now signs out before the signed-out captures and
  reports images that failed to decode.
- `tools/doctor.php` checks the configuration for the mistakes that do not report
  themselves, and exits non-zero so a deployment step can act on it.
