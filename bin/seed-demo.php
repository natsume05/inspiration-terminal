<?php

/**
 * Seed a development database with realistic sample content.
 *
 * The base `seed.php` creates only the reference rows needed to run. Screenshots,
 * manual testing and demos all look better with content that resembles real use,
 * so this command adds accounts, posts across several categories, comments,
 * likes, notifications and purchased items.
 *
 * It is idempotent: every insert is guarded on a natural key, so running it twice
 * does not duplicate anything.
 *
 * Usage:
 *   php bin/seed-demo.php
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

echo 'Database driver: ' . $database->driver() . PHP_EOL;

/**
 * Insert a row when a row with the same natural key does not exist yet.
 *
 * @param Database $database Connection.
 * @param string $table Table name.
 * @param array<string, mixed> $values Column values.
 * @param string $guardColumn Column forming the natural key.
 * @return int The existing or newly inserted identifier.
 */
function upsertBy(Database $database, string $table, array $values, string $guardColumn): int
{
    $existing = $database->selectOne(
        sprintf('SELECT id FROM %s WHERE %s = :key', $table, $guardColumn),
        ['key' => $values[$guardColumn]],
    );

    if ($existing !== null) {
        return (int) $existing['id'];
    }

    $columns = array_keys($values);
    $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

    return $database->insert(
        sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
        ),
        $values,
    );
}

/**
 * Insert a row for a composite-key table, ignoring the request when it already
 * exists.
 *
 * The duplicate-key syntax differs between engines, so it is chosen by driver.
 * Assuming MySQL here would make the seeder work on a production database and
 * fail on the SQLite database the test suite uses.
 *
 * @param Database $database Connection.
 * @param string $table Table name.
 * @param array<string, mixed> $values Column values.
 * @param string $conflictTarget Columns forming the conflict target for SQLite.
 * @return void
 */
function insertIgnoringDuplicates(Database $database, string $table, array $values, string $conflictTarget): void
{
    $columns = array_keys($values);
    $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

    $prefix = $database->driver() === 'sqlite' ? 'INSERT' : 'INSERT IGNORE';
    $suffix = $database->driver() === 'sqlite' ? ' ON CONFLICT (' . $conflictTarget . ') DO NOTHING' : '';

    $database->execute(
        sprintf(
            '%s INTO %s (%s) VALUES (%s)%s',
            $prefix,
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            $suffix,
        ),
        $values,
    );
}

// --- Accounts --------------------------------------------------------------
$accounts = [
    ['username' => 'MingMo', 'display_name' => 'MingMo', 'role' => 'admin', 'bio' => '在虚空里刻字的人。', 'exp' => 1250, 'stardust' => 3480],
    ['username' => 'keqing', 'display_name' => '刻晴', 'role' => 'moderator', 'bio' => '效率至上。', 'exp' => 860, 'stardust' => 1120],
    ['username' => 'traveler', 'display_name' => '旅行者', 'role' => 'user', 'bio' => '路过这颗星球。', 'exp' => 420, 'stardust' => 640],
    ['username' => 'hollow', 'display_name' => '小骑士', 'role' => 'user', 'bio' => '空洞骑士永不为奴。', 'exp' => 180, 'stardust' => 210],
];

$userIds = [];

foreach ($accounts as $account) {
    $userId = upsertBy($database, 'users', [
        'username' => $account['username'],
        'email' => $account['username'] . '@example.com',
        // One shared password for every demo account; this is sample data, and
        // the seeder prints the value so nobody has to guess it.
        'password_hash' => password_hash('demo-password', PASSWORD_DEFAULT),
        'role' => $account['role'],
        'status' => 'active',
    ], 'username');

    $userIds[$account['username']] = $userId;

    $profileExists = $database->selectOne('SELECT user_id FROM user_profiles WHERE user_id = :id', ['id' => $userId]);

    if ($profileExists === null) {
        $database->execute(
            'INSERT INTO user_profiles (user_id, display_name, bio, exp, stardust)
             VALUES (:user_id, :display_name, :bio, :exp, :stardust)',
            [
                'user_id' => $userId,
                'display_name' => $account['display_name'],
                'bio' => $account['bio'],
                'exp' => $account['exp'],
                'stardust' => $account['stardust'],
            ],
        );
    }
}

echo 'Accounts: ' . count($userIds) . PHP_EOL;

// --- Categories ------------------------------------------------------------
// Created here rather than assumed, so this command works on a freshly migrated
// database without requiring the reference seeder to run first.
$categories = [
    ['daily', '日常吐槽', '☕', 1],
    ['game', '游戏圣殿', '🎮', 2],
    ['tech', '代码深空', '💻', 3],
    ['void', '虚空回响', '🌌', 4],
];

$categoryIds = [];

foreach ($categories as [$slug, $name, $icon, $order]) {
    $categoryIds[$slug] = upsertBy($database, 'post_categories', [
        'slug' => $slug,
        'name' => $name,
        'icon' => $icon,
        'sort_order' => $order,
    ], 'slug');
}

// --- Shop items ------------------------------------------------------------
// The demo account buys a few of these, so they have to exist for the profile
// page to show anything in its "owned items" counter.
$shopItems = [
    ['moss', '苍绿之径苔藓', '名字特效', 'effect', 'common', 50, '🌿', 'effect-green-moss'],
    ['dream_nail', '梦之钉', '名字特效', 'effect', 'rare', 150, '🗡', 'effect-dream-nail'],
    ['radiance', '辐光', '传说名字特效', 'effect', 'legendary', 500, '✨', 'effect-radiance'],
    ['frame_weaver', '编织者之歌', '头像框', 'avatar_frame', 'rare', 120, '🕸', 'frame-weaver'],
    ['frame_grimm', '格林剧团之火', '头像框', 'avatar_frame', 'epic', 300, '🔥', 'frame-grimm'],
];

foreach ($shopItems as [$code, $name, $description, $type, $rarity, $price, $icon, $cssClass]) {
    upsertBy($database, 'shop_items', [
        'code' => $code,
        'name' => $name,
        'description' => $description,
        'type' => $type,
        'rarity' => $rarity,
        'price' => $price,
        'icon' => $icon,
        'css_class' => $cssClass,
    ], 'code');
}

// --- Toolbox links ---------------------------------------------------------
$toolLinks = [
    ['GitHub Trending', 'https://github.com/trending', '🐙', '每日热门仓库', 'dev', 1],
    ['SteamDB', 'https://steamdb.info/', '🎮', 'Steam 价格与更新追踪', 'game', 2],
    ['MDN Web Docs', 'https://developer.mozilla.org/', '📚', 'Web 平台权威文档', 'dev', 3],
];

foreach ($toolLinks as [$title, $url, $icon, $description, $category, $order]) {
    $existing = $database->selectOne('SELECT id FROM tools WHERE url = :url', ['url' => $url]);

    if ($existing !== null) {
        continue;
    }

    $database->execute(
        'INSERT INTO tools (title, url, icon, description, category, sort_order)
         VALUES (:title, :url, :icon, :description, :category, :sort_order)',
        [
            'title' => $title,
            'url' => $url,
            'icon' => $icon,
            'description' => $description,
            'category' => $category,
            'sort_order' => $order,
        ],
    );
}

// --- Posts -----------------------------------------------------------------
$posts = [
    ['MingMo', 'tech', "把点赞的「先查再写」换成了唯一键之后，双击终于不再刷出两个赞了。\n\n并发这件事最麻烦的地方是：平时测不出来。", 2],
    ['keqing', 'tech', "今天把静态检查的规则改精确了。原来它会把「只返回字符串字面量的三元表达式」也判成违规——那种规则最后一定会被无视。", 1],
    ['traveler', 'game', "星际拓荒通关了。\n\n最后那段音乐响起来的时候，我坐了很久没动。", 3],
    ['hollow', 'game', "空洞骑士的辐光打了三个小时。\n\n胜利的那一刻手是抖的。", 1],
    ['MingMo', 'void', "有些东西写下来就是为了忘记。\n\n所以才有了思维殿堂。", 2],
    ['keqing', 'daily', "整理了一下这一周的待办。\n\n删掉了十七条，剩下六条。", 0],
    ['traveler', 'void', "深夜的网站只有自己一个访客。\n\n有种奇怪的安全感。", 1],
    ['hollow', 'daily', "第一次在深空频道发帖。\n\n这里比想象中安静。", 0],
];

$postIds = [];

foreach ($posts as $index => [$author, $slug, $content, $likeCount]) {
    $signature = mb_substr($content, 0, 24);

    $existing = $database->selectOne(
        'SELECT id FROM posts WHERE content LIKE :signature ORDER BY id LIMIT 1',
        ['signature' => $signature . '%'],
    );

    if ($existing !== null) {
        $postIds[] = (int) $existing['id'];

        continue;
    }

    $postId = $database->insert(
        'INSERT INTO posts (user_id, category_id, content, created_at)
         VALUES (:user_id, :category_id, :content, :created_at)',
        [
            'user_id' => $userIds[$author],
            'category_id' => $categoryIds[$slug],
            'content' => $content,
            // Spread the timestamps so the feed does not look machine-generated.
            'created_at' => date('Y-m-d H:i:s', time() - ((count($posts) - $index) * 5400)),
        ],
    );

    $postIds[] = $postId;

    // Likes come from the other accounts, one each, which keeps the composite
    // key satisfied and the counter consistent with the rows.
    $others = array_values(array_filter($userIds, static fn (int $id): bool => $id !== $userIds[$author]));
    $likers = array_slice($others, 0, min($likeCount, count($others)));

    foreach ($likers as $likerId) {
        insertIgnoringDuplicates(
            $database,
            'post_likes',
            ['post_id' => $postId, 'user_id' => $likerId],
            'user_id, post_id',
        );
    }

    $database->execute(
        'UPDATE posts SET like_count = (SELECT COUNT(*) FROM post_likes WHERE post_id = :id) WHERE id = :id',
        ['id' => $postId],
    );
}

echo 'Posts: ' . count($postIds) . PHP_EOL;

// --- Comments --------------------------------------------------------------
$comments = [
    [0, 'keqing', '这个我上周也踩过，最后也是靠唯一键解决的。'],
    [0, 'traveler', '所以测试要跑在真实引擎上才有意义。'],
    [1, 'MingPo', '规则太吵和没有规则一样糟。'],
    [2, 'hollow', '那一段确实是整个游戏的高光。'],
    [3, 'traveler', '恭喜！我卡在无眼。'],
    [4, 'keqing', '这个设计很有意思。'],
];

foreach ($comments as [$postIndex, $author, $content]) {
    if (!isset($postIds[$postIndex]) || !isset($userIds[$author])) {
        continue;
    }

    $postId = $postIds[$postIndex];

    $existing = $database->selectOne(
        'SELECT id FROM comments WHERE post_id = :post_id AND content = :content',
        ['post_id' => $postId, 'content' => $content],
    );

    if ($existing !== null) {
        continue;
    }

    $database->execute(
        'INSERT INTO comments (post_id, user_id, content, created_at) VALUES (:post_id, :user_id, :content, :created_at)',
        [
            'post_id' => $postId,
            'user_id' => $userIds[$author],
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s', time() - 1800),
        ],
    );
}

// No table alias here: SQLite rejects an aliased UPDATE that has no FROM clause,
// and the column names are unambiguous without one.
$database->execute(
    'UPDATE posts SET comment_count = (
        SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.is_deleted = 0
     )',
);

// --- Blog entries ----------------------------------------------------------
$entries = [
    [
        'title' => '把数据一致性下沉到数据库层',
        'content' => "# 起因\n\n点赞功能上线后偶尔会出现「一个赞算成两个」。原因是先查再写的逻辑在并发下失效：两个请求都查到「不存在」，然后都插入。\n\n## 改法\n\n把约束交给数据库：\n\n```sql\nCREATE TABLE post_likes (\n    user_id INT UNSIGNED NOT NULL,\n    post_id INT UNSIGNED NOT NULL,\n    PRIMARY KEY (user_id, post_id)\n);\n```\n\n复合主键让重复插入直接被拒绝，代码再用 `INSERT IGNORE` 配合影响行数判断是否真的新增。\n\n## 结论\n\n**能在数据库层表达的约束，就不要放在应用层。**",
    ],
    [
        'title' => '一个只在真实数据库上复现的问题',
        'content' => "本地跑测试全绿，部署到真实 MariaDB 之后，约束却没生效。\n\n## 原因\n\n`sql_mode` 缺少 `STRICT_TRANS_TABLES`。非严格模式下，写入越界值会被**静默修正**——写 `-5` 到 `UNSIGNED` 列，实际存进去的是 `0`，只产生一条警告。\n\n更要紧的是：`CHECK` 约束根本不会被求值，因为值在检查之前就已经被改掉了。\n\n## 为什么测试没抓到\n\nSQLite 会直接拒绝这些值。**测试通过不等于正确，要问自己：我测的是不是线上同一个引擎。**",
    ],
    [
        'title' => '一个只在 Windows 上失效的质量门禁',
        'content' => <<<'MARKDOWN'
我写了个静态检查脚本，其中一条规则是「模板输出必须转义」。

它在 Windows 上从来没生效过：

```php
$relative = str_replace($basePath . '/', '', $file);
str_starts_with($relative, 'templates/')   // Windows 上是反斜杠，永远匹配不上
```

结果本地「通过」是因为规则没跑，第一个诚实信号来自 Linux 上的 CI。

> **一个只在特定平台上生效的检查，比没有检查更危险，因为它给你虚假的安全感。**
MARKDOWN,
    ],
];

foreach ($entries as $index => $entry) {
    $existing = $database->selectOne(
        'SELECT id FROM blog_posts WHERE title = :title',
        ['title' => $entry['title']],
    );

    if ($existing !== null) {
        continue;
    }

    $slug = strtolower(trim((string) preg_replace('/[^\p{Han}\p{Latin}\p{N}]+/u', '-', $entry['title']), '-'));

    $database->execute(
        'INSERT INTO blog_posts (author_id, title, slug, excerpt, content, is_published, published_at, created_at)
         VALUES (:author_id, :title, :slug, :excerpt, :content, 1, :published, :created)',
        [
            'author_id' => $userIds['MingMo'],
            'title' => $entry['title'],
            'slug' => $slug . '-' . ($index + 1),
            'excerpt' => mb_substr(trim(strip_tags($entry['content'])), 0, 160),
            'content' => $entry['content'],
            'published' => date('Y-m-d H:i:s', time() - (($index + 1) * 86400)),
            'created' => date('Y-m-d H:i:s', time() - (($index + 1) * 86400)),
        ],
    );
}

// --- Purchases -------------------------------------------------------------
$items = $database->select('SELECT id, price FROM shop_items ORDER BY price ASC LIMIT 3');

foreach ($items as $item) {
    insertIgnoringDuplicates(
        $database,
        'user_items',
        ['user_id' => $userIds['MingMo'], 'item_id' => (int) $item['id']],
        'user_id, item_id',
    );
}

// Equip one item so the profile page shows the decoration path.
$firstItem = $database->selectOne(
    'SELECT ui.item_id FROM user_items ui WHERE ui.user_id = :user_id ORDER BY ui.item_id LIMIT 1',
    ['user_id' => $userIds['MingMo']],
);

if ($firstItem !== null) {
    $database->execute(
        'UPDATE user_items SET is_equipped = 1 WHERE user_id = :user_id AND item_id = :item_id',
        ['user_id' => $userIds['MingMo'], 'item_id' => (int) $firstItem['item_id']],
    );
}

// --- Notifications ---------------------------------------------------------
$notifications = [
    [$userIds['MingMo'], 'comment', $userIds['keqing'], '刻晴 评论了你的帖子。', 'post', $postIds[0] ?? null],
    [$userIds['MingMo'], 'like', $userIds['traveler'], '旅行者 赞了你的帖子。', 'post', $postIds[0] ?? null],
    [$userIds['MingMo'], 'system', null, '欢迎使用灵感传输终端 v2.0。', 'announcement', null],
];

foreach ($notifications as [$recipient, $type, $actor, $message, $targetType, $targetId]) {
    $existing = $database->selectOne(
        'SELECT id FROM notifications WHERE user_id = :user_id AND message = :message',
        ['user_id' => $recipient, 'message' => $message],
    );

    if ($existing !== null) {
        continue;
    }

    $database->execute(
        'INSERT INTO notifications (user_id, type, actor_id, target_type, target_id, message, read_at, created_at)
         VALUES (:user_id, :type, :actor_id, :target_type, :target_id, :message, :read_at, :created_at)',
        [
            'user_id' => $recipient,
            'type' => $type,
            'actor_id' => $actor,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'message' => $message,
            // The first two are left unread so the navigation badge has something
            // to show in screenshots.
            'read_at' => $type === 'system' ? date('Y-m-d H:i:s') : null,
            'created_at' => date('Y-m-d H:i:s', time() - 3600),
        ],
    );
}

// --- Announcement ----------------------------------------------------------
$database->execute('UPDATE announcements SET is_active = 0 WHERE is_active = 1');

$existingAnnouncement = $database->selectOne('SELECT id FROM announcements WHERE content = :content', [
    'content' => 'v2.0 已上线：数据库重新设计、安全加固、完整测试覆盖。',
]);

if ($existingAnnouncement === null) {
    $database->execute(
        'INSERT INTO announcements (content, is_active, created_by) VALUES (:content, 1, :by)',
        ['content' => 'v2.0 已上线：数据库重新设计、安全加固、完整测试覆盖。', 'by' => $userIds['MingMo']],
    );
}

// --- Feedback --------------------------------------------------------------
$feedbackExists = $database->selectOne('SELECT id FROM feedback LIMIT 1');

if ($feedbackExists === null) {
    $database->execute(
        "INSERT INTO feedback (user_id, type, content, status) VALUES (:user_id, 'suggestion', :content, 'pending')",
        [
            'user_id' => $userIds['traveler'],
            'content' => '希望社区支持按热度排序，现在只能按时间看。',
        ],
    );
}

// --- Summary ---------------------------------------------------------------
echo PHP_EOL . 'Demo data ready.' . PHP_EOL;

// One literal statement per table rather than a concatenated name, so the table
// list stays a fixed set and the linter's rule against interpolated SQL has no
// exception carved into it.
$summaryQueries = [
    'users' => 'SELECT COUNT(*) AS total FROM users',
    'posts' => 'SELECT COUNT(*) AS total FROM posts',
    'comments' => 'SELECT COUNT(*) AS total FROM comments',
    'post_likes' => 'SELECT COUNT(*) AS total FROM post_likes',
    'blog_posts' => 'SELECT COUNT(*) AS total FROM blog_posts',
    'notifications' => 'SELECT COUNT(*) AS total FROM notifications',
    'user_items' => 'SELECT COUNT(*) AS total FROM user_items',
];

foreach ($summaryQueries as $label => $statement) {
    $row = $database->selectOne($statement);
    printf('  %-14s %s%s', $label, $row['total'] ?? 0, PHP_EOL);
}

echo PHP_EOL . 'Sign in with:' . PHP_EOL;
echo '  MingMo / demo-password     (administrator — sees everything)' . PHP_EOL;
echo '  keqing / demo-password     (moderator)' . PHP_EOL;
echo '  traveler / demo-password   (member)' . PHP_EOL;

exit(0);
