<?php

/**
 * Local development server: build the schema into a SQLite file and add sample
 * rows, so the application can be opened in a browser without MySQL.
 *
 * Run: php bin/dev-sqlite.php
 */

declare(strict_types=1);

use App\Database\Database;
use App\Database\Migrator;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$storageDirectory = dirname(__DIR__) . '/storage';

if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0755, true) && !is_dir($storageDirectory)) {
    fwrite(STDERR, "Could not create the storage directory.\n");
    exit(1);
}

$databasePath = $storageDirectory . '/dev.sqlite';

// SQLite in WAL mode keeps its committed state in `-wal` and `-shm` sidecar
// files. Removing only the main file leaves those behind, and a later process
// can then read a mix of the new database and the old journal — which shows up
// as a freshly seeded database that appears empty. All three are removed here.
$sidecars = [$databasePath, $databasePath . '-wal', $databasePath . '-shm'];

foreach ($sidecars as $file) {
    if (!is_file($file)) {
        continue;
    }

    if (!@unlink($file)) {
        fwrite(STDERR, sprintf(
            'Cannot remove %s. A running server is probably holding it open; stop it and retry.%s',
            basename($file),
            PHP_EOL,
        ));
        exit(1);
    }
}

$database = Database::boot([
    'driver' => 'sqlite',
    'sqlite_path' => $databasePath,
]);

$migrator = new Migrator($database, dirname(__DIR__) . '/database/migrations', translateToSqlite: true);
$applied = $migrator->migrate();

echo 'Applied migrations: ' . (implode(', ', $applied) ?: '(none)') . PHP_EOL;

$database->execute(
    "INSERT INTO users (username, password_hash, role, status)
     VALUES ('MingMo', :hash, 'admin', 'active')",
    ['hash' => password_hash('inspiration-dev-password', PASSWORD_DEFAULT)],
);

$database->execute("INSERT INTO user_profiles (user_id, display_name, exp, stardust) VALUES (1, 'MingMo', 120, 500)");

$database->execute(
    "INSERT INTO post_categories (slug, name, icon, sort_order) VALUES
        ('daily', '日常吐槽', '☕', 1),
        ('game', '游戏圣殿', '🎮', 2),
        ('tech', '代码深空', '💻', 3),
        ('void', '虚空回响', '🌌', 4)",
);

$database->execute(
    "INSERT INTO shop_items (code, name, description, type, rarity, price, icon, css_class) VALUES
        ('moss', '苍绿之径苔藓', '名字特效', 'effect', 'common', 50, '🌿', 'effect-green-moss'),
        ('dream_nail', '梦之钉', '名字特效', 'effect', 'rare', 150, '🗡', 'effect-dream-nail'),
        ('radiance', '辐光', '传说名字特效', 'effect', 'legendary', 500, '✨', 'effect-radiance'),
        ('frame_weaver', '编织者之歌', '头像框', 'avatar_frame', 'rare', 120, '🕸', 'frame-weaver'),
        ('frame_grimm', '格林剧团之火', '头像框', 'avatar_frame', 'epic', 300, '🔥', 'frame-grimm')",
);

$database->execute(
    "INSERT INTO posts (user_id, category_id, content) VALUES
        (1, 3, '重构完成：所有写操作现在都经过路由层的 CSRF 校验，接口再也无法遗漏。'),
        (1, 4, '把点赞改成依赖唯一键，并发双击不会再刷出重复的赞了。')",
);

echo 'Seeded: 1 user (MingMo / inspiration-dev-password), 4 categories, 5 shop items, 2 posts.' . PHP_EOL;
echo 'Database written to storage/dev.sqlite' . PHP_EOL;
echo PHP_EOL . 'Start the site with:' . PHP_EOL;
echo '  DB_DRIVER=sqlite DB_SQLITE_PATH=storage/dev.sqlite APP_KEY=<32-byte-key> \\' . PHP_EOL;
echo '  php -S 127.0.0.1:8080 -t public' . PHP_EOL;
