<?php

/**
 * Deploy the application into a target directory.
 *
 * Copies only the files the running application needs, so the web root never
 * contains migrations, tests, or tooling. The script refuses to overwrite an
 * existing installation without an explicit flag, and it never deletes files it
 * did not place there.
 *
 * Usage:
 *   php tools/deploy.php <target-directory> [--force] [--keep-legacy]
 *
 * Options:
 *   --force        overwrite files that already exist in the target
 *   --keep-legacy  leave any pre-existing files in the target untouched
 */

declare(strict_types=1);

$arguments = array_slice($argv, 1);
$target = null;
$force = false;
$keepLegacy = false;

foreach ($arguments as $argument) {
    if ($argument === '--force') {
        $force = true;
        continue;
    }

    if ($argument === '--keep-legacy') {
        $keepLegacy = true;
        continue;
    }

    if (!str_starts_with($argument, '--')) {
        $target = $argument;
    }
}

if ($target === null) {
    fwrite(STDERR, 'Usage: php tools/deploy.php <target-directory> [--force] [--keep-legacy]' . PHP_EOL);
    exit(1);
}

$source = dirname(__DIR__);
$target = rtrim(str_replace('\\', '/', $target), '/');

if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
    fwrite(STDERR, 'Cannot create the target directory: ' . $target . PHP_EOL);
    exit(1);
}

// Everything the running site needs. Development-only paths are deliberately
// absent so they cannot be reached over HTTP.
$include = [
    'bin',
    'bootstrap',
    'config',
    'database',
    'public',
    'routes',
    'src',
    'templates',
    'composer.json',
    'README.md',
];

/**
 * Recursively copy a file or directory.
 *
 * @param string $from Absolute source path.
 * @param string $to Absolute destination path.
 * @param bool $force Whether existing files may be replaced.
 * @param list<string> $skipped Paths that were left alone.
 * @param list<string> $copied Paths that were written.
 * @return void
 */
function copyPath(string $from, string $to, bool $force, array &$skipped, array &$copied): void
{
    if (is_file($from)) {
        if (file_exists($to) && !$force) {
            $skipped[] = $to;

            return;
        }

        if (!is_dir(dirname($to))) {
            mkdir(dirname($to), 0755, true);
        }

        copy($from, $to);
        $copied[] = $to;

        return;
    }

    if (!is_dir($from)) {
        return;
    }

    if (!is_dir($to) && !mkdir($to, 0755, true) && !is_dir($to)) {
        fwrite(STDERR, 'Cannot create directory: ' . $to . PHP_EOL);
        exit(1);
    }

    $entries = scandir($from) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        copyPath($from . '/' . $entry, $to . '/' . $entry, $force, $skipped, $copied);
    }
}

$copied = [];
$skipped = [];

foreach ($include as $item) {
    copyPath($source . '/' . $item, $target . '/' . $item, $force, $skipped, $copied);
}

printf('Copied %d files into %s%s', count($copied), $target, PHP_EOL);

if ($skipped !== []) {
    printf('%d files already existed and were left unchanged.%s', count($skipped), PHP_EOL);
    echo 'Re-run with --force to overwrite them.' . PHP_EOL;
}

// Runtime directories must exist and be writable.
$storageDirectories = ['storage', 'storage/sessions', 'storage/uploads'];

foreach ($storageDirectories as $directory) {
    $path = $target . '/' . $directory;

    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        fwrite(STDERR, 'Cannot create: ' . $path . PHP_EOL);
        exit(1);
    }
}

echo 'Runtime directories are ready: storage/sessions, storage/uploads' . PHP_EOL;

// Report on the environment file rather than creating one silently: a generated
// APP_KEY would silently make any existing encrypted note unreadable.
$envPath = $target . '/.env';
$envExample = $target . '/.env.example';

if (!is_file($envPath)) {
    echo PHP_EOL . 'Action required: create ' . $envPath . PHP_EOL;
    echo '  Copy .env.example and set APP_KEY, DB_*, and APP_URL.' . PHP_EOL;

    if (is_file($envExample)) {
        echo '  A template was deployed to .env.example.' . PHP_EOL;
    }
}

echo PHP_EOL . 'Next steps:' . PHP_EOL;
echo '  1. Create and edit .env (APP_KEY, DB_*, APP_URL).' . PHP_EOL;
echo '  2. Point your web server document root at ' . $target . '/public' . PHP_EOL;
echo '  3. Run: php ' . $target . '/bin/migrate.php' . PHP_EOL;
