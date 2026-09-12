<?php

/**
 * Copy data from the legacy `my_forum` schema into the current one.
 *
 * Reads the old database and writes into the new one. The legacy database is
 * only ever read, so it remains a usable fallback if the result is not what you
 * expect.
 *
 * Usage:
 *   php bin/migrate-legacy.php [--legacy-db my_forum] [--dry-run]
 *
 * Options:
 *   --legacy-db <name>  Legacy database name (default: my_forum)
 *   --dry-run           Report what would be read without writing anything
 */

declare(strict_types=1);

use App\Database\Database;
use App\Database\LegacyMigrator;
use App\Security\Crypto;
use App\Support\Config;
use App\Support\Env;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

Env::load($basePath . '/.env');

$arguments = array_slice($argv, 1);
$legacyDatabase = 'my_forum';
$dryRun = in_array('--dry-run', $arguments, true);

$databaseFlag = array_search('--legacy-db', $arguments, true);
if ($databaseFlag !== false && isset($arguments[$databaseFlag + 1])) {
    $legacyDatabase = $arguments[$databaseFlag + 1];
}

$config = Config::fromFile($basePath . '/config/app.php');

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = $config->get('database', []);
$databaseConfig['base_path'] = $basePath;

try {
    $target = Database::boot($databaseConfig);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Cannot connect to the target database: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

// The legacy connection reuses the same credentials but a different schema.
$legacyDsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    (string) ($databaseConfig['host'] ?? '127.0.0.1'),
    (int) ($databaseConfig['port'] ?? 3306),
    $legacyDatabase,
    (string) ($databaseConfig['charset'] ?? 'utf8mb4'),
);

try {
    $source = new PDO(
        $legacyDsn,
        (string) ($databaseConfig['username'] ?? ''),
        (string) ($databaseConfig['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, sprintf('Cannot connect to the legacy database "%s": %s%s', $legacyDatabase, $throwable->getMessage(), PHP_EOL));
    exit(1);
}

printf('Source: %s%s', $legacyDatabase, PHP_EOL);
printf('Target: %s (%s)%s', (string) ($databaseConfig['name'] ?? '?'), $target->driver(), PHP_EOL);

// Summary of the source so the mapping is reviewed before anything is written.
// Each statement is a literal rather than an interpolated table name: the
// linter rejects SQL built by concatenation, and a fixed statement per table
// keeps that rule absolute instead of carving out an exception for it.
$sourceTables = [
    'users' => 'SELECT COUNT(*) AS total FROM users',
    'posts' => 'SELECT COUNT(*) AS total FROM posts',
    'comments' => 'SELECT COUNT(*) AS total FROM comments',
    'likes' => 'SELECT COUNT(*) AS total FROM likes',
    'blog_posts' => 'SELECT COUNT(*) AS total FROM blog_posts',
    'tools' => 'SELECT COUNT(*) AS total FROM tools',
    'shop_items' => 'SELECT COUNT(*) AS total FROM shop_items',
    'user_inventory' => 'SELECT COUNT(*) AS total FROM user_inventory',
    'private_notes' => 'SELECT COUNT(*) AS total FROM private_notes',
    'feedback' => 'SELECT COUNT(*) AS total FROM feedback',
];

echo PHP_EOL . 'Source rows:' . PHP_EOL;
foreach ($sourceTables as $label => $statement) {
    try {
        $row = $source->query($statement)->fetch();
        printf('  %-16s %s%s', $label, $row['total'] ?? '0', PHP_EOL);
    } catch (Throwable $throwable) {
        printf('  %-16s (table not present)%s', $label, PHP_EOL);
    }
}

if ($dryRun) {
    echo PHP_EOL . 'Dry run: nothing was written.' . PHP_EOL;
    exit(0);
}

// Legacy notes are plaintext and must be encrypted on the way in. If no key is
// configured the notes are reported and skipped rather than copied as plaintext,
// because storing them unencrypted would contradict what the schema promises.
$crypto = null;
$appKey = (string) $config->get('security.app_key', '');

if ($appKey !== '') {
    $crypto = new Crypto($appKey);
} else {
    echo PHP_EOL . 'Note: APP_KEY is not set, so legacy private notes will be skipped.' . PHP_EOL;
}

$migrator = new LegacyMigrator($source, $target, $crypto);

echo PHP_EOL . 'Migrating...' . PHP_EOL;

try {
    $migrator->migrate();
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Migration failed: ' . $throwable->getMessage() . PHP_EOL);
    fwrite(STDERR, $throwable->getFile() . ':' . $throwable->getLine() . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'Migrated:' . PHP_EOL;
foreach ($migrator->report() as $label => $count) {
    if ($count > 0) {
        printf('  %-28s %d%s', $label, $count, PHP_EOL);
    }
}

$warnings = $migrator->warnings();

if ($warnings !== []) {
    printf('%s%d warning(s):%s', PHP_EOL, count($warnings), PHP_EOL);
    foreach ($warnings as $warning) {
        echo '  - ' . $warning . PHP_EOL;
    }
}

echo PHP_EOL . 'The legacy database was only read and is unchanged.' . PHP_EOL;

exit(0);
