<?php

/**
 * Write reference and demonstration data.
 *
 * Safe to run repeatedly: every insert is guarded, so seeding twice does not
 * duplicate rows or overwrite an existing account's password.
 *
 * Usage:
 *   php bin/seed.php
 *   php bin/seed.php --with-demo-user "Name" "password"
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

$email = 'demo@example.com';
$password = 'demo-password';

// Optional: seed a specific account instead of the demo one.
$userFlag = array_search('--with-demo-user', $argv, true);
if ($userFlag !== false && isset($argv[$userFlag + 1], $argv[$userFlag + 2])) {
    $email = $argv[$userFlag + 1];
    $password = $argv[$userFlag + 2];
}

// --- Categories ------------------------------------------------------------
$categories = [
    ['daily', '日常吐槽', '☕', 1],
    ['game', '游戏圣殿', '🎮', 2],
    ['tech', '代码深空', '💻', 3],
    ['void', '虚空回响', '🌌', 4],
];

foreach ($categories as [$slug, $name, $icon, $order]) {
    $exists = $database->selectOne('SELECT id FROM post_categories WHERE slug = :slug', ['slug' => $slug]);

    if ($exists === null) {
        $database->execute(
            'INSERT INTO post_categories (slug, name, icon, sort_order)
             VALUES (:slug, :name, :icon, :sort_order)',
            ['slug' => $slug, 'name' => $name, 'icon' => $icon, 'sort_order' => $order],
        );
    }
}

// --- Shop items ------------------------------------------------------------
$items = [
    ['moss', '苍绿之径苔藓', '名字特效', 'effect', 'common', 50, '🌿', 'effect-green-moss'],
    ['dream_nail', '梦之钉', '名字特效', 'effect', 'rare', 150, '🗡', 'effect-dream-nail'],
    ['sprint_master', '冲刺大师', '名字特效', 'effect', 'rare', 180, '💨', 'effect-sprint-master'],
    ['radiance', '辐光', '传说名字特效', 'effect', 'legendary', 500, '✨', 'effect-radiance'],
    ['void_heart', '虚空之心', '传说名字特效', 'effect', 'legendary', 800, '🖤', 'effect-void-heart'],
    ['frame_weaver', '编织者之歌', '头像框', 'avatar_frame', 'rare', 120, '🕸', 'frame-weaver'],
    ['frame_grimm', '格林剧团之火', '头像框', 'avatar_frame', 'epic', 300, '🔥', 'frame-grimm'],
    ['frame_silksong', '丝之歌旋律', '头像框', 'avatar_frame', 'legendary', 700, '🎵', 'frame-silksong'],
    ['badge_paimon', '派蒙的王冠', '徽章', 'badge', 'epic', 260, '👑', ''],
];

foreach ($items as [$code, $name, $description, $type, $rarity, $price, $icon, $cssClass]) {
    $exists = $database->selectOne('SELECT id FROM shop_items WHERE code = :code', ['code' => $code]);

    if ($exists === null) {
        $database->execute(
            'INSERT INTO shop_items (code, name, description, type, rarity, price, icon, css_class)
             VALUES (:code, :name, :description, :type, :rarity, :price, :icon, :css_class)',
            [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'rarity' => $rarity,
                'price' => $price,
                'icon' => $icon,
                'css_class' => $cssClass,
            ],
        );
    }
}

// --- Toolbox links ---------------------------------------------------------
$tools = [
    ['GitHub Trending', 'https://github.com/trending', '🐙', '每日热门仓库', 'dev', 1],
    ['SteamDB', 'https://steamdb.info/', '🎮', 'Steam 价格与更新追踪', 'game', 2],
    ['MDN Web Docs', 'https://developer.mozilla.org/', '📚', 'Web 平台权威文档', 'dev', 3],
];

foreach ($tools as [$title, $url, $icon, $description, $category, $order]) {
    $exists = $database->selectOne('SELECT id FROM tools WHERE url = :url', ['url' => $url]);

    if ($exists === null) {
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
}

// --- Demo account ----------------------------------------------------------
// Both unique columns are checked: an account created by an earlier run may
// match on username while carrying a different email, so testing only the email
// would attempt an insert that violates the username unique key.
$existing = $database->selectOne(
    'SELECT id FROM users WHERE email = :email OR username = :username',
    ['email' => $email, 'username' => 'MingMo'],
);

if ($existing === null) {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $userId = $database->transaction(static function (Database $database) use ($email, $hash): int {
        $id = $database->insert(
            "INSERT INTO users (username, email, password_hash, role, status)
             VALUES (:username, :email, :hash, 'admin', 'active')",
            ['username' => 'MingMo', 'email' => $email, 'hash' => $hash],
        );

        $database->execute(
            'INSERT INTO user_profiles (user_id, display_name, exp, stardust)
             VALUES (:user_id, :display_name, 120, 500)',
            ['user_id' => $id, 'display_name' => 'MingMo'],
        );

        return $id;
    });

    $category = $database->selectOne("SELECT id FROM post_categories WHERE slug = 'tech'");

    if ($category !== null) {
        $database->execute(
            'INSERT INTO posts (user_id, category_id, content) VALUES (:user_id, :category_id, :content)',
            [
                'user_id' => $userId,
                'category_id' => (int) $category['id'],
                'content' => '欢迎来到灵感传输终端。这是种子数据生成的示例帖子。',
            ],
        );
    }

    printf('Created demo account: %s / %s%s', $email, $password, PHP_EOL);
} else {
    echo 'Demo account already exists; left unchanged.' . PHP_EOL;
}

echo 'Seeding complete.' . PHP_EOL;
