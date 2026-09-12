<?php

/**
 * Rebuild every blog excerpt from its entry's Markdown.
 *
 * The excerpt is a derived column: it exists so listing pages can print one line
 * of plain text without parsing Markdown for every row. Because it is derived, a
 * change to the rules leaves old rows holding output the rules no longer produce
 * — which is how migrated entries ended up advertising `## 起因` and an opening
 * code fence in the blog index.
 *
 * This command brings them back in line. It only reads `content` and only writes
 * `excerpt`, so it is safe to run on a live database and safe to run twice.
 *
 * Usage:
 *   php bin/refresh-excerpts.php            # report what would change
 *   php bin/refresh-excerpts.php --apply    # write the new excerpts
 */

declare(strict_types=1);

use App\Database\Database;
use App\Support\Config;
use App\Support\Env;
use App\Support\Excerpt;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

Env::load($basePath . '/.env');

$config = Config::fromFile($basePath . '/config/app.php');

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = $config->get('database', []);
$databaseConfig['base_path'] = $basePath;

$database = Database::boot($databaseConfig);

$apply = in_array('--apply', $argv, true);

echo 'Database driver: ' . $database->driver() . PHP_EOL;
echo $apply ? 'Mode: apply' . PHP_EOL : 'Mode: dry run (pass --apply to write)' . PHP_EOL . PHP_EOL;

$rows = $database->select('SELECT id, title, content, excerpt FROM blog_posts ORDER BY id');

$changed = 0;

foreach ($rows as $row) {
    $current = (string) ($row['excerpt'] ?? '');
    $rebuilt = Excerpt::from((string) $row['content'], 320);

    if ($current === $rebuilt) {
        continue;
    }

    $changed++;

    printf("#%s %s%s", $row['id'], (string) $row['title'], PHP_EOL);
    printf("   was: %s%s", mb_substr($current, 0, 90), PHP_EOL);
    printf("   now: %s%s", mb_substr($rebuilt, 0, 90), PHP_EOL);

    if ($apply) {
        // No "only if it changed" guard in the statement. The obvious version —
        // comparing the column against the value that was just read out of it —
        // is always false, so every row was skipped and the command reported
        // seven rebuilds it had not written.
        $database->execute(
            'UPDATE blog_posts SET excerpt = :rebuilt WHERE id = :blog_id',
            ['rebuilt' => $rebuilt, 'blog_id' => (int) $row['id']],
        );
    }
}

printf(
    '%s%d of %d excerpt(s) %s.%s',
    PHP_EOL,
    $changed,
    count($rows),
    $apply ? 'rebuilt' : 'would change',
    PHP_EOL,
);

exit(0);
