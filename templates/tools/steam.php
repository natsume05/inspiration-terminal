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
            <div class="empty-state">
                <p><strong><?= View::escape($deals['message'] ?? '暂时无法获取折扣数据。') ?></strong></p>
                <p>
                    页面不会因为上游接口不可用而变成空白：如果此前成功获取过数据，这里会继续显示缓存内容。
                    完全没有缓存时才会看到这条提示，稍后刷新即可。
                </p>
            </div>
        <?php elseif ($deals['deals'] === []): ?>
            <div class="empty-state">
                <p><strong>当前没有符合筛选条件的折扣。</strong></p>
                <p>筛选条件是 Steam 商店、正在打折、Metacritic 75 分以上，按折扣力度排序。淡季时可能确实没有结果。</p>
            </div>
        <?php else: ?>
            <ul class="deal-grid">
                <?php foreach ($deals['deals'] as $deal): ?>
                    <li class="deal-card">
                        <?php
                        // No `loading="lazy"` here. With a dozen thumbnails the
                        // deferral buys nothing, and it produced an image whose DOM
                        // state reported `complete` with real natural dimensions
                        // while the compositor still had not painted the bytes —
                        // so the cards rendered as blank rectangles in screenshots
                        // and on a fast scroll, which looks like a broken page.
                        ?>
                        <?php if (!empty($deal['thumb'])): ?>
                            <img class="deal-thumb" src="<?= View::escape($deal['thumb']) ?>"
                                 alt="<?= View::escape($deal['title']) ?> 的封面" decoding="async">
                        <?php endif; ?>

                        <div class="deal-body">
                            <span class="deal-title"><?= View::escape($deal['title']) ?></span>

                            <span class="deal-prices">
                                <s class="deal-was"><?= View::escape($deal['normal_price']) ?></s>
                                <strong class="deal-now"><?= View::escape($deal['sale_price']) ?></strong>
                            </span>

                            <span class="deal-tags">
                                <span class="pill pill-gold">-<?= View::escape(number_format((float) $deal['savings'], 0)) ?>%</span>
                                <?php if ($deal['metacritic'] !== ''): ?>
                                    <span class="pill" title="Metacritic 评分">MC <?= View::escape($deal['metacritic']) ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>全年大促日历</h2>
        <p class="muted">这部分是编辑内容，不依赖上游接口，因此始终可用。</p>

        <ul class="calendar-grid">
            <?php foreach ($calendar as $event): ?>
                <li class="calendar-card">
                    <span class="calendar-icon" aria-hidden="true"><?= View::escape($event['icon']) ?></span>
                    <span class="calendar-body">
                        <span class="calendar-name"><?= View::escape($event['name']) ?></span>
                        <time class="calendar-date" datetime="<?= View::escape($event['date']) ?>">
                            <?= View::escape($event['date']) ?>
                        </time>
                        <span class="calendar-desc"><?= View::escape($event['description']) ?></span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</section>
