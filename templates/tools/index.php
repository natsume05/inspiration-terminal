<?php

/**
 * Toolbox index.
 *
 * @var array<string, list<array<string,mixed>>> $links
 * @var int $projectCount
 */

use App\Http\View;

/**
 * Present a category key as a readable heading and an icon.
 *
 * The keys are whatever the operator typed into the `tools` table, so anything
 * unrecognised falls back to the key itself rather than being hidden — an
 * unmapped category is a data problem, and silently dropping it would make the
 * page look like the links had vanished.
 *
 * @param string $category Category key.
 * @return array{0: string, 1: string} Label and icon.
 */
$categoryMeta = static function (string $category): array {
    return match ($category) {
        'dev', 'tools' => ['开发工具', '🛠'],
        'game' => ['游戏', '🎮'],
        'design' => ['设计', '🎨'],
        'life' => ['生活', '🌿'],
        'impression' => ['灵感', '✨'],
        'general' => ['通用', '📎'],
        default => [$category, '📎'],
    };
};

/**
 * Fall back to a generic mark when a link has no icon.
 *
 * Fourteen of the seeded rows carry an empty icon, and an empty span renders as
 * blank space, which reads as a broken card rather than as a link without an
 * emoji.
 *
 * @param mixed $icon Stored icon value.
 * @return string Icon to render.
 */
$iconFor = static function (mixed $icon): string {
    $icon = trim((string) $icon);

    return $icon === '' ? '🔗' : $icon;
};
?>
<section class="feed">
    <header class="feed-header">
        <h1>提瓦特百宝箱</h1>
        <p class="muted">工具导航、开源榜单与 Steam 折扣监控。</p>
    </header>

    <div class="module-grid">
        <a class="module-card" href="/tools/github">
            <span class="card-icon" aria-hidden="true">🐙</span>
            <span class="module-body">
                <span class="card-title">GitHub 开源猎手</span>
                <span class="card-desc">
                    七日新秀榜与万星总榜，支持按仓库名、简介与语言检索本地缓存。
                </span>
                <span class="module-meta">
                    已缓存 <strong><?= (int) $projectCount ?></strong> 个仓库
                    <?php if ((int) $projectCount === 0): ?>
                        · <span class="muted">运行 <code>php bin/fetch-github.php</code> 填充</span>
                    <?php endif; ?>
                </span>
            </span>
        </a>

        <a class="module-card" href="/tools/steam">
            <span class="card-icon" aria-hidden="true">🎮</span>
            <span class="module-body">
                <span class="card-title">Steam 战略指挥室</span>
                <span class="card-desc">
                    实时折扣、口碑榜单与全年大促日历，折扣数据由服务端代理并缓存。
                </span>
                <span class="module-meta">由 CheapShark 提供数据</span>
            </span>
        </a>
    </div>

    <?php if ($links === []): ?>
        <section class="panel">
            <h2>导航链接</h2>
            <div class="empty-state">
                <p><strong>还没有配置任何导航链接。</strong></p>
                <p>管理员可以在<strong>舰长控制台 → 导航管理</strong>中逐条添加，或直接写入 <code>tools</code> 表。</p>
            </div>
        </section>
    <?php endif; ?>

    <?php foreach ($links as $category => $items): ?>
        <?php [$label, $icon] = $categoryMeta((string) $category); ?>
        <section class="panel">
            <h2><span aria-hidden="true"><?= View::escape($icon) ?></span> <?= View::escape($label) ?>
                <span class="muted">（<?= count($items) ?>）</span>
            </h2>

            <ul class="link-grid">
                <?php foreach ($items as $link): ?>
                    <li class="link-card">
                        <a class="link-anchor" href="<?= View::escape($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="link-icon" aria-hidden="true"><?= View::escape($iconFor($link['icon'] ?? '')) ?></span>
                            <span class="link-title"><?= View::escape($link['title']) ?></span>
                        </a>
                        <?php if (!empty($link['description'])): ?>
                            <p class="link-desc"><?= View::escape($link['description']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</section>
