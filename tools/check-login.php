<?php

/**
 * Diagnostic: does the seeded development password verify against the stored hash?
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Database\Database;

$database = Database::boot([
    'driver' => 'sqlite',
    'sqlite_path' => dirname(__DIR__) . '/storage/dev.sqlite',
]);

$row = $database->selectOne('SELECT id, username, password_hash, role, status FROM users ORDER BY id LIMIT 1');

if ($row === null) {
    echo 'No users in the database.' . PHP_EOL;
    exit(1);
}

printf("user: %s (id %d)%s", $row['username'], $row['id'], PHP_EOL);
printf("role: %s   status: %s%s", $row['role'], $row['status'], PHP_EOL);
printf("hash length: %d, prefix: %s%s", strlen((string) $row['password_hash']), substr((string) $row['password_hash'], 0, 7), PHP_EOL);

foreach (['inspiration-dev-password', 'inspiration-dev-password ', ''] as $candidate) {
    $ok = password_verify($candidate, (string) $row['password_hash']);
    printf("verify(%-26s) => %s%s", "'" . $candidate . "'", $ok ? 'TRUE' : 'false', PHP_EOL);
}

// Confirm the script's own credentials are what the login route would look up.
$script = file_get_contents(dirname(__DIR__) . '/bin/dev-sqlite.php');
if (preg_match("/password_hash\('([^']+)'/", (string) $script, $matches) === 1) {
    printf("%sSeeding script hashes the password: '%s'%s", PHP_EOL, $matches[1], PHP_EOL);
}
