# Deployment

Two supported paths: Docker for a reproducible environment, or a plain PHP host
for a shared-hosting deployment. Both end with the document root pointing at
`public/`.

---

## Why the document root must be `public/`

`public/index.php` is the only PHP file reachable over HTTP. Configuration,
migrations, tests, and the `src/` tree sit above it, so they cannot be requested
at all. Deploying with the project root as the document root would expose
`config/app.php`, `.env`, and the test suite.

---

## Option 1 — Docker

```bash
git clone https://github.com/natsume05/inspiration-terminal.git
cd inspiration-terminal
cp .env.example .env

# Generate the application key and paste it into APP_KEY.
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"

docker compose up -d
```

Compose starts MariaDB, waits for it to accept connections, runs migrations and
seeds demo data, then starts the application on <http://localhost:8080>. Uploads
live in a named volume so a rebuild does not discard them.

---

## Option 2 — A PHP host (XAMPP, cPanel, a VPS)

### 1. Place the application above the web root

Put the project somewhere the web server can read, then point the document root
at its `public/` directory.

For XAMPP, the cleanest local equivalent is a virtual host or an alias so the
existing `htdocs` keeps working:

```apache
Alias /inspiration "D:/XAMPP/xampp/htdocs/inspiration-terminal/public"
<Directory "D:/XAMPP/xampp/htdocs/inspiration-terminal/public">
    AllowOverride All
    Require all granted
</Directory>
```

Reload Apache and open <http://localhost/inspiration>.

### 2. Create the database and configure the environment

On cPanel, create the database and user through the panel first; hosts usually
prefix both with the account name.

```bash
cp .env.example .env
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

That command prints a **64-character hexadecimal string**. It belongs in
`APP_KEY` and nowhere else.

> **`APP_KEY` and `GITHUB_TOKEN` are different kinds of secret and are not
> interchangeable.** `APP_KEY` is generated locally from random bytes and never
> leaves your machine; it derives the encryption key for private notes, so losing
> it makes those notes permanently unreadable. `GITHUB_TOKEN` is issued by GitHub
> and is used only to raise the API rate limit for the toolbox.
>
> Putting a `ghp_…` token into `APP_KEY` does not fail loudly — encryption still
> runs, because any string of 16 characters or more is accepted. The result is
> that note encryption is keyed by a credential that also lives in GitHub's
> interface and in your shell history, which defeats the point of having a
> separate application key. Check with `php tools/doctor.php` after editing.

Edit `.env`:

```ini
APP_ENV=production
APP_DEBUG=false                 # must be false: it prints stack traces
APP_URL=https://example.com
APP_KEY=<the 64-character hex value generated above>

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_DATABASE=<host-provided name>
DB_USERNAME=<host-provided user>
DB_PASSWORD=<host-provided password>

# Set true once the site is served over HTTPS.
SESSION_COOKIE_SECURE=true

# Optional. A fine-grained, read-only token; leave empty to run unauthenticated.
# GITHUB_TOKEN=github_pat_...
```

### 3. Migrate

```bash
php bin/migrate.php            # apply pending migrations
php bin/migrate.php --status    # show what has run
php bin/seed.php                # optional: categories, shop items, demo account
```

On Windows with XAMPP, `php` is usually not on `PATH`, so the command reports
that it is not recognised. Either call the interpreter by its full path:

```powershell
D:\XAMPP\php\php.exe bin\migrate.php
D:\XAMPP\php\php.exe bin\migrate.php --status
D:\XAMPP\php\php.exe bin\seed.php
D:\XAMPP\php\php.exe tools\verify-deployment.php
```

…or add it to `PATH` for the current session:

```powershell
$env:Path += ';D:\XAMPP\php'
php bin/migrate.php
```

### 4. Make `storage/` writable

Sessions and uploads are written under `storage/`:

```bash
mkdir -p storage/sessions storage/uploads
chmod -R 0755 storage
```

Keeping sessions here rather than in the host's shared temp directory is
deliberate: that directory is often unwritable on shared hosting, and when it is,
`session_start()` fails with a warning while the request continues, so logins
silently never persist.

### 5. Serve uploads

Uploads are stored outside the document root. On a host that cannot be told to
alias a directory, symlink it into the public tree:

```bash
ln -s ../storage/uploads public/uploads
```

The uploader re-encodes every image to WebP and generates its own filename, so
nothing a client submits is ever executed or served under its original name.

---

## Optional: refresh the GitHub toolbox cache

The toolbox serves cached rankings, so ordinary traffic never spends the API
rate limit.

```bash
php bin/fetch-github.php
```

Add it to cron (once or twice a day is plenty) and set `GITHUB_TOKEN` in `.env`.
Without a token GitHub allows far fewer requests per hour, and the command says
so when it starts.

---

## Upgrading an existing `my_forum` installation

If you are coming from the earlier schema, take a backup first:

```bash
php tools/backup-legacy.php /path/to/webroot /path/to/backups --db my_forum
```

That writes a SQL dump and a copy of the old web root into a timestamped
directory, then the data can be imported:

```bash
php bin/migrate-legacy.php --legacy-db my_forum
php bin/migrate-legacy.php --dry-run      # report what would be read, write nothing
```

The legacy database is only read. The importer repairs the data problems it
finds rather than aborting, and reports every repair as a warning:

- posts whose author has no account are reattributed to a suspended placeholder
  account instead of being dropped;
- the zero date `0000-00-00 00:00:00` is replaced, because strict mode rejects it;
- duplicate likes and inventory rows are collapsed, and the counters recomputed
  from the rows that actually exist;
- plaintext private notes are encrypted with AES-256-GCM on the way in;
- accounts holding an invalid role are reset to `user`.

---

## Post-deployment checklist

- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set, backed up, and **not** committed. Losing it makes every
      encrypted note unreadable.
- [ ] `SESSION_COOKIE_SECURE=true` when serving HTTPS
- [ ] Document root points at `public/`
- [ ] `storage/` writable by the web server, and not reachable over HTTP
- [ ] `php bin/migrate.php --status` reports nothing pending
- [ ] `php tools/doctor.php` reports no failures
- [ ] `php tools/verify-deployment.php` passes against the live database
- [ ] Database user has only the privileges the application needs
- [ ] Any token that has ever been committed has been rotated, not just removed

### Rotating APP_KEY

Rotating `APP_KEY` is not a configuration change like any other: it is the key for
every private note. Notes written under the previous key cannot be read afterwards,
and the notes page will say so rather than showing nothing —
`（此笔记无法解密：密钥可能已更换，或数据已被修改。）` — one row at a time, which is the
point: the alternative is silently empty notes.

There is no re-encryption path, because the old key is exactly what you are trying to
retire. Before rotating, decide which of these you want:

1. **Accept the loss.** Correct when the notes are throwaway, or when the key was
   exposed and keeping the notes is not worth the risk.
2. **Re-encrypt first, while both keys are available.** Read the notes with the old
   key, write them back with the new one, and only then retire the old value. This has
   to happen while both keys still exist, so it is a planned operation rather than a
   recovery step.

The deployment this project runs was rotated once, for the reason above: the key slot
held a GitHub token. One note was lost to it, which is how the failure mode is
documented here rather than assumed.
