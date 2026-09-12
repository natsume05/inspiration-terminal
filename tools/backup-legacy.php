<?php

/**
 * Back up the legacy database and application files before a deployment.
 *
 * Deployment is destructive in the sense that it changes a working
 * installation, so the first step is always a restorable copy: a SQL dump of
 * the existing database and an archive of the existing web root. Nothing here
 * modifies the source; it only reads and writes into a timestamped folder.
 *
 * Usage:
 *   php tools/backup-legacy.php <web-root> <backup-directory> [--db name] [--user u] [--password p] [--host h]
 */

declare(strict_types=1);

/**
 * Parse a `--key value` style argument list.
 *
 * @param list<string> $arguments Raw argument list.
 * @param string $key Option name without dashes.
 * @param string $default Value used when the option is absent.
 * @return string The resolved value.
 */
function option(array $arguments, string $key, string $default): string
{
    $index = array_search('--' . $key, $arguments, true);

    if ($index !== false && isset($arguments[$index + 1])) {
        return $arguments[$index + 1];
    }

    return $default;
}

$arguments = array_slice($argv, 1);
$positional = [];

// Walk the list once: an argument following a `--option` is that option's
// value, never a positional argument.
for ($index = 0; $index < count($arguments); $index++) {
    $argument = $arguments[$index];

    if (str_starts_with($argument, '--')) {
        $index++;

        continue;
    }

    $positional[] = $argument;
}

$webRoot = $positional[0] ?? null;
$backupRoot = $positional[1] ?? null;

if ($webRoot === null || $backupRoot === null) {
    fwrite(STDERR, 'Usage: php tools/backup-legacy.php <web-root> <backup-directory> [--db name] [--user u] [--password p] [--host h]' . PHP_EOL);
    exit(1);
}

$database = option($arguments, 'db', 'my_forum');
$user = option($arguments, 'user', 'root');
$password = option($arguments, 'password', '');
$host = option($arguments, 'host', '127.0.0.1');
$mysqldump = option($arguments, 'mysqldump', 'mysqldump');

$stamp = date('Ymd-His');
$destination = rtrim($backupRoot, '/\\') . '/' . $stamp;

if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
    fwrite(STDERR, 'Cannot create the backup directory: ' . $destination . PHP_EOL);
    exit(1);
}

echo 'Backing up into ' . $destination . PHP_EOL;

// --- Database --------------------------------------------------------------
$dumpFile = $destination . '/database-' . $database . '.sql';
$command = sprintf(
    '%s --host=%s --user=%s %s --routines --events --single-transaction --default-character-set=utf8mb4 %s > %s 2>%s',
    escapeshellarg($mysqldump),
    escapeshellarg($host),
    escapeshellarg($user),
    $password === '' ? '' : '--password=' . escapeshellarg($password),
    escapeshellarg($database),
    escapeshellarg($dumpFile),
    escapeshellarg($destination . '/mysqldump-error.log'),
);

$exitCode = 0;
$output = [];
exec($command, $output, $exitCode);

if ($exitCode === 0 && is_file($dumpFile) && filesize($dumpFile) > 0) {
    printf('  database dump: %s (%.1f KB)%s', basename($dumpFile), filesize($dumpFile) / 1024, PHP_EOL);
} else {
    fwrite(STDERR, '  database dump FAILED (exit code ' . $exitCode . ')' . PHP_EOL);
    $errorLog = $destination . '/mysqldump-error.log';
    if (is_file($errorLog)) {
        fwrite(STDERR, '  ' . trim((string) file_get_contents($errorLog)) . PHP_EOL);
    }
    fwrite(STDERR, '  The database server may not be running. Files were still backed up.' . PHP_EOL);
}

// --- Application files -----------------------------------------------------
$targets = ['includes', 'admin', 'assets', '.agents', '.htaccess', '.gitignore', 'README.md', 'wp-config.php'];

$manifest = [];
$failed = [];

foreach ($targets as $target) {
    $source = rtrim($webRoot, '/\\') . '/' . $target;

    if (!file_exists($source)) {
        continue;
    }

    $destinationPath = $destination . '/webroot/' . $target;

    if (is_dir($source)) {
        $command = sprintf(
            'xcopy %s %s /E /I /Q /Y >NUL 2>&1',
            escapeshellarg(str_replace('/', '\\', $source)),
            escapeshellarg(str_replace('/', '\\', $destinationPath)),
        );
        $exitCode = 0;
        exec($command, $unused, $exitCode);

        // xcopy exits 0 on success and 1 when no files were copied.
        if ($exitCode > 1) {
            $failed[] = $target;
            continue;
        }

        $manifest[$target] = 'directory';
    } else {
        if (!is_dir(dirname($destinationPath))) {
            mkdir(dirname($destinationPath), 0755, true);
        }

        if (copy($source, $destinationPath)) {
            $manifest[$target] = 'file';
        } else {
            $failed[] = $target;
        }
    }
}

// Record the custom PHP files individually so the backup is auditable.
$phpFiles = glob(rtrim($webRoot, '/\\') . '/*.php') ?: [];
$rootCopy = $destination . '/webroot-root-php';
mkdir($rootCopy, 0755, true);
$count = 0;

foreach ($phpFiles as $file) {
    $name = basename($file);
    // Skip WordPress core files: the back-up is for the custom application.
    if (str_starts_with($name, 'wp-')) {
        continue;
    }

    if (copy($file, $rootCopy . '/' . $name)) {
        $count++;
    }
}

printf('  web root: %d top-level PHP files plus %d entries%s', $count, count($manifest), PHP_EOL);

if ($failed !== []) {
    fwrite(STDERR, '  FAILED to back up: ' . implode(', ', $failed) . PHP_EOL);
}

file_put_contents(
    $destination . '/BACKUP-MANIFEST.txt',
    implode(PHP_EOL, [
        'Backup created: ' . date('c'),
        'Source web root: ' . $webRoot,
        'Database: ' . $database,
        'Database dump: ' . (is_file($dumpFile) ? basename($dumpFile) : 'FAILED'),
        'Entries captured: ' . implode(', ', array_keys($manifest)),
        'Root PHP files: ' . $count,
    ]) . PHP_EOL,
);

echo 'Backup complete: ' . $destination . PHP_EOL;

exit($failed === [] ? 0 : 1);
