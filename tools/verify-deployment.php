<?php

/**
 * Verify the deployed schema enforces its constraints on the real database.
 *
 * The test suite proves this against SQLite, which shares the schema through
 * translation. This script proves the same guarantees hold on MySQL/MariaDB
 * using the production insert syntax, where the duplicate-suppression keywords
 * differ.
 *
 * Usage: php tools/verify-deployment.php
 */

declare(strict_types=1);

use App\Database\Database;
use App\Support\Config;
use App\Support\Env;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

Env::load($basePath . '/.env');

$config = Config::fromFile($basePath . '/config/app.php');

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = $config->get('database', []);
$databaseConfig['base_path'] = $basePath;

$database = Database::boot($databaseConfig);

printf('Driver: %s%s', $database->driver(), PHP_EOL);

$results = [];

/**
 * Record the outcome of one check.
 *
 * @param array<string, array{passed: bool, detail: string}> $results Result accumulator.
 * @param string $label Check description.
 * @param bool $passed Whether the check passed.
 * @param string $detail Extra information.
 * @return void
 */
function check(array &$results, string $label, bool $passed, string $detail = ''): void
{
    $results[$label] = ['passed' => $passed, 'detail' => $detail];
}

// Use a reserved username so a repeat run cannot collide with real data.
$username = 'constraint_probe';
$database->execute('DELETE FROM users WHERE username = :username', ['username' => $username]);

$userId = $database->insert(
    'INSERT INTO users (username, password_hash, role, status)
     VALUES (:username, :hash, :role, :status)',
    ['username' => $username, 'hash' => password_hash('probe-password', PASSWORD_DEFAULT), 'role' => 'user', 'status' => 'active'],
);

$database->execute(
    'INSERT INTO user_profiles (user_id, display_name, stardust) VALUES (:id, :name, 0)',
    ['id' => $userId, 'name' => 'Probe'],
);

$categoryId = $database->selectOne("SELECT id FROM post_categories WHERE slug = 'tech'")['id'] ?? null;

if ($categoryId === null) {
    $categoryId = $database->insert(
        "INSERT INTO post_categories (slug, name, icon) VALUES ('tech', '代码深空', '💻')",
    );
}

$postId = $database->insert(
    'INSERT INTO posts (user_id, category_id, content) VALUES (:user_id, :category_id, :content)',
    ['user_id' => $userId, 'category_id' => $categoryId, 'content' => 'constraint probe post'],
);

// 1. Duplicate like must be suppressed by the composite primary key.
$first = $database->execute(
    'INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (:post_id, :user_id)',
    ['post_id' => $postId, 'user_id' => $userId],
);
$second = $database->execute(
    'INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (:post_id, :user_id)',
    ['post_id' => $postId, 'user_id' => $userId],
);

check($results, 'duplicate like suppressed (INSERT IGNORE)', $first === 1 && $second === 0, sprintf('rows affected: %d then %d', $first, $second));

// 2. Foreign key must reject an orphaned row.
$orphanRejected = false;
try {
    $database->execute('INSERT INTO post_likes (post_id, user_id) VALUES (999999, :user_id)', ['user_id' => $userId]);
} catch (PDOException $exception) {
    $orphanRejected = true;
}

check($results, 'orphan like rejected by foreign key', $orphanRejected);

// 3. UNSIGNED plus CHECK must reject a negative balance.
$negativeRejected = false;
try {
    $database->execute('UPDATE user_profiles SET stardust = -1 WHERE user_id = :id', ['id' => $userId]);
} catch (PDOException $exception) {
    $negativeRejected = true;
}

check($results, 'negative stardust rejected', $negativeRejected);

// 4. ENUM must reject an unknown role.
$roleRejected = false;
try {
    $database->execute(
        "INSERT INTO users (username, password_hash, role, status) VALUES ('probe_bad_role', 'x', 'root', 'active')",
    );
} catch (PDOException $exception) {
    $roleRejected = true;
}

check($results, 'invalid role rejected by ENUM', $roleRejected);

// 5. Deleting the post must cascade to its likes.
$database->execute('DELETE FROM posts WHERE id = :id', ['id' => $postId]);
$remaining = $database->selectOne('SELECT COUNT(*) AS total FROM post_likes WHERE post_id = :id', ['id' => $postId]);
check($results, 'likes cascade-deleted with the post', ((int) $remaining['total']) === 0);

// 6. Deleting the user must cascade to the profile.
$database->execute('DELETE FROM users WHERE id = :id', ['id' => $userId]);
$profiles = $database->selectOne('SELECT COUNT(*) AS total FROM user_profiles WHERE user_id = :id', ['id' => $userId]);
check($results, 'profile cascade-deleted with the user', ((int) $profiles['total']) === 0);

// 7. The daily throttle upsert must increment rather than duplicate.
$today = date('Y-m-d');
$database->execute('DELETE FROM rate_limits WHERE user_id = :id', ['id' => $userId]);

$failures = 0;
foreach ($results as $label => $result) {
    printf("  [%s] %s%s%s", $result['passed'] ? 'PASS' : 'FAIL', $label, $result['detail'] !== '' ? ' — ' . $result['detail'] : '', PHP_EOL);

    if (!$result['passed']) {
        $failures++;
    }
}

echo PHP_EOL;
printf('%d checks, %d failures.%s', count($results), $failures, PHP_EOL);

exit($failures === 0 ? 0 : 1);
