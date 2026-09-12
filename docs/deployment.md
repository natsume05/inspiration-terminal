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

Edit `.env`:

```ini
APP_ENV=production
APP_DEBUG=false                 # must be false: it prints stack traces
APP_URL=https://example.com
APP_KEY=<the generated value>

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_DATABASE=<host-provided name>
DB_USERNAME=<host-provided user>
DB_PASSWORD=<host-provided password>

# Set true once the site is served over HTTPS.
SESSION_COOKIE_SECURE=true
```

### 3. Migrate

```bash
php bin/migrate.php            # apply pending migrations
php bin/migrate.php --status    # show what has run
php bin/seed.php                # optional: categories, shop items, demo account
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
- [ ] `php tools/verify-deployment.php` passes against the live database
- [ ] Database user has only the privileges the application needs
- [ ] Any token that has ever been committed has been rotated, not just removed
