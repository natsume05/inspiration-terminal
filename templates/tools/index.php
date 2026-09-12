<?php

/**
 * Toolbox index.
 *
 * @var array<string, list<array<string,mixed>>> $links
 * @var int $projectCount
 */

use App\Http\View;

/**
 * Present a category key as a readable heading.
 *
 * @param string $category Category key.
 * @return string Human-readable label.
 */
$categoryLabel = static function (string $category): string {
    return match ($category) {
        'dev' => '开发',
        'game' => '游戏',
        'design' => '设计',
        'general' => '通用',
        default => $category,
    };
};
?>
<section class="feed">
    <header class="feed-header">
        <h1>提瓦特百宝箱</h1>
        <p class="muted">工具导航、开源榜单与 Steam 折扣监控。</p>
    </header>

    <div class="lobby-grid">
        <a class="lobby-card" href="/tools/github">
            <span class="card-icon" aria-hidden="true">🐙</span>
            <h3 class="card-title">GitHub 开源猎手</h3>
            <p class="card-desc">七日新秀榜与万星总榜，已缓存 <?= (int) $projectCount ?> 个仓库，支持本地检索。</p>
        </a>

        <a class="lobby-card" href="/tools/steam">
            <span class="card-icon" aria-hidden="true">🎮</span>
            <h3 class="card-title">Steam 战略指挥室</h3>
            <p class="card-desc">实时折扣、口碑榜单与全年大促日历。</p>
        </a>
    </div>

    <?php if ($links === []): ?>
        <p class="muted">还没有配置任何导航链接。</p>
    <?php endif; ?>

    <?php foreach ($links as $category => $items): ?>
        <section class="panel">
            <h2><?= View::escape($categoryLabel((string) $category)) ?></h2>

            <ul class="link-grid">
                <?php foreach ($items as $link): ?>
                    <li class="link-card">
                        <a href="<?= View::escape($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="link-icon" aria-hidden="true"><?= View::escape($link['icon']) ?></span>
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
