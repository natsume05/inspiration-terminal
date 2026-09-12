<?php

/**
 * Check a deployment's configuration and report anything wrong.
 *
 * Written because one class of mistake here fails silently rather than loudly.
 * Any string of sixteen characters or more is accepted as `APP_KEY`, so pasting a
 * credential into that variable produces a working site whose note encryption is
 * keyed by the wrong secret. Nothing complains; the damage is only discovered when
 * the key is rotated and the notes cannot be read.
 *
 * Usage:
 *   php tools/doctor.php
 *
 * Exit code is 0 when nothing needs attention, 1 when something does.
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

use App\Support\Config;

$config = Config::fromFile($basePath . '/config/app.php');

/** @var list<array{level: string, label: string, detail: string}> $findings */
$findings = [];

/**
 * Record one finding.
 *
 * @param string $level One of `ok`, `warn`, or `fail`.
 * @param string $label What was checked.
 * @param string $detail What was found.
 * @return void
 */
function report(array &$findings, string $level, string $label, string $detail): void
{
    $findings[] = ['level' => $level, 'label' => $label, 'detail' => $detail];
}

$envFile = $basePath . '/.env';

if (!is_file($envFile)) {
    report($findings, 'warn', '.env', 'not present; relying on the process environment and the fallbacks in config/app.php');
}

// --- APP_KEY ---------------------------------------------------------------
$appKey = (string) $config->get('security.app_key', '');

if ($appKey === '') {
    report($findings, 'fail', 'APP_KEY', 'not set — private notes cannot be encrypted at all');
} elseif (preg_match('/^(ghp_|github_pat_|gho_|ghs_|ghu_)/', $appKey) === 1) {
    // The specific mistake this tool exists for.
    report(
        $findings,
        'fail',
        'APP_KEY',
        'looks like a GitHub token, not an application key. Generate one with '
        . '`php -r "echo bin2hex(random_bytes(32));"` and put the token in GITHUB_TOKEN instead. '
        . 'Encryption currently works, which is why this is easy to miss.',
    );
} elseif (preg_match('/^[0-9a-f]{64}$/i', $appKey) !== 1) {
    report(
        $findings,
        'warn',
        'APP_KEY',
        sprintf(
            'accepted but unusual (length %d, not 64 hex characters). The expected value comes from '
            . '`php -r "echo bin2hex(random_bytes(32));"`.',
            strlen($appKey),
        ),
    );
} else {
    // Prove it end to end rather than only checking its shape.
    try {
        $crypto = new App\Security\Crypto($appKey);
        $sealed = $crypto->encrypt('doctor probe');
        $roundTrip = $crypto->decrypt($sealed['ciphertext'], $sealed['nonce']) === 'doctor probe';

        report(
            $findings,
            $roundTrip ? 'ok' : 'fail',
            'APP_KEY',
            $roundTrip ? 'encrypts and decrypts correctly' : 'encryption round trip failed',
        );
    } catch (Throwable $throwable) {
        report($findings, 'fail', 'APP_KEY', 'cannot be used: ' . $throwable->getMessage());
    }
}

// --- GitHub token ----------------------------------------------------------
$githubToken = (string) $config->get('integrations.github.token', '');

if ($githubToken === '') {
    report($findings, 'warn', 'GITHUB_TOKEN', 'not set. The toolbox works, but GitHub throttles unauthenticated requests hard.');
} elseif (preg_match('/^(ghp_|github_pat_)/', $githubToken) !== 1) {
    report($findings, 'warn', 'GITHUB_TOKEN', 'does not look like a GitHub token (expected a ghp_ or github_pat_ prefix)');
} else {
    report($findings, 'ok', 'GITHUB_TOKEN', 'set');
}

if ($githubToken !== '' && $githubToken === $appKey) {
    report($findings, 'fail', 'secrets', 'APP_KEY and GITHUB_TOKEN hold the same value; they must be different secrets');
}

// --- Other settings --------------------------------------------------------
if ($config->isDebug() && $config->get('app.env') !== 'local') {
    report($findings, 'fail', 'APP_DEBUG', 'enabled outside the local environment; it prints stack traces to visitors');
} else {
    report($findings, 'ok', 'APP_DEBUG', $config->isDebug() ? 'enabled (fine for local)' : 'disabled');
}

$appUrl = (string) $config->get('app.url', '');

if ($appUrl === '') {
    report($findings, 'warn', 'APP_URL', 'not set');
} elseif (str_starts_with($appUrl, 'https://') && !$config->get('session.cookie_secure', false)) {
    report(
        $findings,
        'warn',
        'SESSION_COOKIE_SECURE',
        'APP_URL is HTTPS but the cookie flag is off; the session cookie can travel over plain HTTP',
    );
} else {
    report($findings, 'ok', 'APP_URL', $appUrl);
}

// --- Database --------------------------------------------------------------
try {
    $databaseConfig = $config->get('database', []);
    $databaseConfig['base_path'] = $basePath;
    $database = App\Database\Database::boot($databaseConfig);

    $applied = $database->select('SELECT version FROM schema_migrations ORDER BY version');
    $migrator = new App\Database\Migrator($database, $basePath . '/database/migrations');
    $pending = $migrator->pending();

    report(
        $findings,
        $pending === [] ? 'ok' : 'warn',
        'Database',
        sprintf('%s, %d migration(s) applied', $database->driver(), count($applied)),
    );

    if ($pending !== []) {
        report($findings, 'warn', 'Migrations', count($pending) . ' pending — run `php bin/migrate.php`');
    }

    // The encryption round trip is only meaningful against real stored data.
    $noteCount = $database->selectOne('SELECT COUNT(*) AS total FROM private_notes');
    report($findings, 'ok', 'private_notes', ($noteCount['total'] ?? 0) . ' row(s) stored');

    $projectCount = $database->selectOne('SELECT COUNT(*) AS total FROM github_projects');
    $projects = (int) ($projectCount['total'] ?? 0);

    report(
        $findings,
        $projects > 0 ? 'ok' : 'warn',
        'github_projects',
        $projects > 0
            ? $projects . ' repositories cached'
            : 'empty, so the GitHub search has nothing to match. Run `php bin/fetch-github.php`.',
    );
} catch (Throwable $throwable) {
    report($findings, 'fail', 'Database', 'cannot connect: ' . $throwable->getMessage());
}

// --- Storage ---------------------------------------------------------------
foreach (['storage', 'storage/sessions', 'storage/uploads'] as $directory) {
    $path = $basePath . '/' . $directory;

    if (!is_dir($path)) {
        report($findings, 'warn', $directory, 'missing');
    } elseif (!is_writable($path)) {
        report($findings, 'fail', $directory, 'not writable — sessions or uploads will fail');
    }
}

$sessionsReady = is_writable($basePath . '/storage/sessions');
report(
    $findings,
    $sessionsReady ? 'ok' : 'fail',
    'Session storage',
    $sessionsReady ? 'storage/sessions is writable' : 'storage/sessions is not writable, so sign-in will not persist',
);

// --- Report ----------------------------------------------------------------
$symbols = ['ok' => '  ok  ', 'warn' => ' warn ', 'fail' => ' FAIL '];
$counts = ['ok' => 0, 'warn' => 0, 'fail' => 0];

echo 'Configuration check' . PHP_EOL . str_repeat('-', 68) . PHP_EOL;

foreach ($findings as $finding) {
    $counts[$finding['level']]++;
    printf("[%s] %-22s %s%s", $symbols[$finding['level']], $finding['label'], $finding['detail'], PHP_EOL);
}

printf(
    '%s%s%s%d ok, %d warning(s), %d failure(s).%s',
    PHP_EOL,
    str_repeat('-', 68),
    PHP_EOL,
    $counts['ok'],
    $counts['warn'],
    $counts['fail'],
    PHP_EOL,
);

if ($counts['fail'] > 0) {
    echo 'Fix the failures above before relying on this deployment.' . PHP_EOL;

    exit(1);
}

if ($counts['warn'] > 0) {
    echo 'No failures. The warnings are worth reading but not blocking.' . PHP_EOL;

    exit(0);
}

echo 'Everything checks out.' . PHP_EOL;

exit(0);
