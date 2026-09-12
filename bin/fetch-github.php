<?php

/**
 * Refresh the cached GitHub rankings.
 *
 * Run on a schedule or by hand. Refreshing here rather than during a page view
 * keeps the sixty-request hourly limit for unauthenticated callers from being
 * spent by ordinary traffic, and means a failed refresh degrades to the last
 * stored ranking instead of an empty panel.
 *
 * Usage:
 *   php bin/fetch-github.php
 *
 * A GITHUB_TOKEN is optional but recommended; without one GitHub allows far
 * fewer requests per hour.
 */

declare(strict_types=1);

use App\Database\Database;
use App\Repository\ApiCacheRepository;
use App\Repository\ToolRepository;
use App\Service\GithubService;
use App\Support\Config;
use App\Support\Env;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

Env::load($basePath . '/.env');

$config = Config::fromFile($basePath . '/config/app.php');

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = $config->get('database', []);
$databaseConfig['base_path'] = $basePath;

try {
    $database = Database::boot($databaseConfig);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Cannot connect to the database: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

$service = new GithubService(
    new ToolRepository($database),
    new ApiCacheRepository($database),
    $config,
);

$token = (string) $config->get('integrations.github.token', '');

if ($token === '') {
    echo 'Note: GITHUB_TOKEN is not set, so GitHub will rate limit this run aggressively.' . PHP_EOL;
}

$total = 0;

foreach (['trending', 'all_time'] as $listType) {
    echo 'Fetching ' . $listType . '...' . PHP_EOL;

    try {
        $stored = $service->refresh($listType);
    } catch (Throwable $throwable) {
        fwrite(STDERR, '  failed: ' . $throwable->getMessage() . PHP_EOL);

        continue;
    }

    printf('  stored %d repositories%s', $stored, PHP_EOL);
    $total += $stored;
}

// Housekeeping: expired cache entries serve no purpose once superseded.
$pruned = (new ApiCacheRepository($database))->pruneExpired();

printf('%sTotal: %d repositories. Pruned %d expired cache entries.%s', PHP_EOL, $total, $pruned, PHP_EOL);

exit($total > 0 ? 0 : 1);
