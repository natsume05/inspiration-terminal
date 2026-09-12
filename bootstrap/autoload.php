<?php

/**
 * Composer-independent class loader.
 *
 * The application ships without a required Composer install so it can run on
 * plain shared hosting. When Composer's autoloader is present it is preferred;
 * otherwise this PSR-4 loader maps `App\` onto `src/` and `Tests\` onto `tests/`.
 */

declare(strict_types=1);

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\' => dirname(__DIR__) . '/src/',
        'Tests\\' => dirname(__DIR__) . '/tests/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});

// Configuration and the application timezone are applied for every entry point,
// including command-line scripts, so the web request path and a cron run cannot
// disagree about what "now" means.
require __DIR__ . '/runtime.php';

bootstrap_runtime(dirname(__DIR__));
