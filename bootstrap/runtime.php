<?php

/**
 * Shared runtime bootstrap for command-line entry points.
 *
 * The web kernel configures the timezone while handling a request. A CLI script
 * that only loads the autoloader inherits PHP's ambient timezone instead, which
 * is whatever the interpreter's default happens to be — often UTC, and on this
 * machine `Europe/Berlin`. That produces two failures that are hard to see:
 *
 *   * rows written with `CURRENT_TIMESTAMP` (UTC in SQLite, session timezone in
 *     MySQL) do not line up with timestamps PHP computes locally, so a window
 *     query such as "failures in the last hour" silently matches nothing;
 *   * cache entries written with a local `expires_at` and read against a UTC
 *     clock expire hours early or late.
 *
 * Both entry points therefore agree on one timezone from one place, rather than
 * each script remembering to set it.
 */

declare(strict_types=1);

use App\Support\Config;
use App\Support\Env;

/**
 * Load configuration and apply the application timezone, once.
 *
 * Safe to call more than once: environment values already present win, and the
 * timezone is simply set to the same value again.
 *
 * @param string $basePath Absolute path to the project root.
 * @return void
 */
function bootstrap_runtime(string $basePath): void
{
    static $loaded = [];

    if (isset($loaded[$basePath])) {
        return;
    }

    $loaded[$basePath] = true;

    Env::load($basePath . '/.env');

    $config = Config::fromFile($basePath . '/config/app.php');

    date_default_timezone_set((string) $config->get('app.timezone', 'UTC'));
}
