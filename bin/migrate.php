<?php

/**
 * Apply database migrations.
 *
 * Usage:
 *   php bin/migrate.php            apply every pending migration
 *   php bin/migrate.php --status    report which migrations have run
 */

declare(strict_types=1);

use App\Database\Database;
use App\Database\Migrator;
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
    fwrite(STDERR, 'Check the DB_* values in your .env file.' . PHP_EOL);
    exit(1);
}

$migrator = new Migrator($database, $basePath . '/database/migrations');

if (in_array('--status', $argv, true)) {
    $pending = $migrator->pending();

    printf("Driver: %s%s", $database->driver(), PHP_EOL);

    if ($pending === []) {
        echo 'All migrations have been applied.' . PHP_EOL;
        exit(0);
    }

    echo 'Pending migrations:' . PHP_EOL;
    foreach ($pending as $path) {
        echo '  - ' . basename($path) . PHP_EOL;
    }

    exit(0);
}

try {
    $applied = $migrator->migrate();
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Migration failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

if ($applied === []) {
    echo 'Nothing to migrate; the database is up to date.' . PHP_EOL;
    exit(0);
}

echo 'Applied:' . PHP_EOL;
foreach ($applied as $version) {
    echo '  - ' . $version . PHP_EOL;
}

exit(0);
