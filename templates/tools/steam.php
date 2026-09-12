<?php

/**
 * Steam discount monitor.
 *
 * @var array{ok: bool, cached?: bool, stale?: bool, message?: string, deals: list<array<string,mixed>>} $deals
 * @var list<array<string,string>> $calendar
 */

use App\Http\View;
?>
<section class="feed">
    <header class="feed-header">
        <h1>Steam 战略指挥室</h1>
        <p class="muted">
            折扣数据由服务端代理请求并缓存，浏览器不直接访问第三方接口。
            <?php if (!empty($deals['stale'])): ?>
                <strong>当前展示的是缓存数据</strong>（上游接口暂时不可用）。
            <?php endif; ?>
        </p>
    </header>

    <section class="panel">
        <h2>当前折扣</h2>

        <?php if (!$deals['ok']): ?>
            <p class="alert alert-error"><?= View::escape($deals['message'] ?? '暂时无法获取折扣数据。') ?></p>
        <?php elseif ($deals['deals'] === []): ?>
            <p class="muted">当前没有符合条件的折扣。</p>
        <?php else: ?>
            <ul class="deal-list">
                <?php foreach ($deals['deals'] as $deal): ?>
                    <li class="deal-item">
                        <?php if (!empty($deal['thumb'])): ?>
                            <img class="deal-thumb" src="<?= View::escape($deal['thumb']) ?>" alt="" loading="lazy">
                        <?php endif; ?>

                        <div class="deal-body">
                            <span class="deal-title"><?= View::escape($deal['title']) ?></span>
                            <span class="deal-price">
                                <s><?= View::escape($deal['normal_price']) ?></s>
                                <strong><?= View::escape($deal['sale_price']) ?></strong>
                            </span>
                            <span class="pill pill-gold">-<?= View::escape(number_format((float) $deal['savings'], 0)) ?>%</span>
                            <?php if ($deal['metacritic'] !== ''): ?>
                                <span class="pill">MC <?= View::escape($deal['metacritic']) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>全年大促日历</h2>
        <ul class="calendar-list">
            <?php foreach ($calendar as $event): ?>
                <li class="calendar-item">
                    <span class="calendar-icon" aria-hidden="true"><?= View::escape($event['icon']) ?></span>
                    <span class="calendar-name"><?= View::escape($event['name']) ?></span>
                    <time class="calendar-date" datetime="<?= View::escape($event['date']) ?>">
                        <?= View::escape($event['date']) ?>
                    </time>
                    <span class="muted"><?= View::escape($event['description']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</section>
