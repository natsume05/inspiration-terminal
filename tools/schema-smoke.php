<?php

/**
 * One-off diagnostic: build the production schema in SQLite and report on it.
 *
 * Run: php tools/schema-smoke.php
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap/autoload.php';

use App\Database\Database;
use App\Database\Migrator;

$database = Database::boot([
    'driver' => 'sqlite',
    'sqlite_path' => ':memory:',
]);

$migrator = new Migrator($database, dirname(__DIR__) . '/database/migrations', translateToSqlite: true);
$applied = $migrator->migrate();

echo "Applied migrations: " . implode(', ', $applied) . PHP_EOL;

$tables = $database->select(
    "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
);

echo "Tables created: " . count($tables) . PHP_EOL;
foreach ($tables as $table) {
    echo '  - ' . $table['name'] . PHP_EOL;
}

$indexes = $database->select(
    "SELECT name, tbl_name FROM sqlite_master WHERE type = 'index' AND name NOT LIKE 'sqlite_%' ORDER BY tbl_name, name",
);

echo "Indexes created: " . count($indexes) . PHP_EOL;
foreach ($indexes as $index) {
    echo '  - ' . $index['tbl_name'] . '.' . $index['name'] . PHP_EOL;
}

// Verify the constraints the concurrency fixes depend on are actually present.
$checks = [];

$checks['post_likes unique pair'] = (bool) $database->selectOne(
    "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='post_likes' AND name='sqlite_autoindex_post_likes_1'",
);

$checks['foreign keys enabled'] = (int) ($database->selectOne('PRAGMA foreign_keys')['foreign_keys'] ?? 0) === 1;

echo PHP_EOL . 'Constraint checks:' . PHP_EOL;
foreach ($checks as $label => $passed) {
    echo sprintf('  [%s] %s', $passed ? 'PASS' : 'FAIL', $label) . PHP_EOL;
}

// Exercise the constraints rather than only inspecting the catalogue.
$now = 'CURRENT_TIMESTAMP';
$database->execute(
    "INSERT INTO users (username, password_hash, role, status) VALUES ('tester', 'hash', 'user', 'active')",
);
$database->execute(
    "INSERT INTO user_profiles (user_id, display_name, exp, stardust) VALUES (1, 'Tester', 0, 100)",
);
$database->execute("INSERT INTO post_categories (slug, name, icon) VALUES ('daily', 'Daily', '')");
$database->execute(
    "INSERT INTO posts (user_id, category_id, content) VALUES (1, 1, 'hello world')",
);

$database->execute('INSERT INTO post_likes (user_id, post_id) VALUES (1, 1)');

$duplicateRejected = false;
try {
    $database->execute('INSERT INTO post_likes (user_id, post_id) VALUES (1, 1)');
} catch (PDOException $exception) {
    $duplicateRejected = true;
}

$checks2 = [];

$checks2['duplicate like rejected by database'] = $duplicateRejected;

$orphanRejected = false;
try {
    $database->execute("INSERT INTO posts (user_id, category_id, content) VALUES (999, NULL, 'orphan')");
} catch (PDOException $exception) {
    $orphanRejected = true;
}

$checks2['orphan post rejected by foreign key'] = $orphanRejected;

$negativeRejected = false;
try {
    $database->execute('UPDATE user_profiles SET stardust = -50 WHERE user_id = 1');
} catch (PDOException $exception) {
    $negativeRejected = true;
}

$checks2['negative stardust rejected by CHECK'] = $negativeRejected;

$invalidRoleRejected = false;
try {
    $database->execute(
        "INSERT INTO users (username, password_hash, role, status) VALUES ('bad', 'hash', 'superuser', 'active')",
    );
} catch (PDOException $exception) {
    $invalidRoleRejected = true;
}

$checks2['invalid role rejected by CHECK'] = $invalidRoleRejected;

$cascadeWorked = false;
$database->execute('DELETE FROM posts WHERE id = 1');
$remaining = $database->select('SELECT COUNT(*) AS total FROM post_likes WHERE post_id = 1');
$cascadeWorked = ((int) $remaining[0]['total']) === 0;

$checks2['likes cascade-deleted with post'] = $cascadeWorked;

echo PHP_EOL . 'Behaviour checks:' . PHP_EOL;
foreach ($checks2 as $label => $passed) {
    echo sprintf('  [%s] %s', $passed ? 'PASS' : 'FAIL', $label) . PHP_EOL;
}
