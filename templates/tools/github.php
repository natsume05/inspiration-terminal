<?php

/**
 * GitHub rankings and search.
 *
 * The search reads the local cache rather than the live API, so it costs no
 * rate-limit budget and works with no token configured. The trade-off is that an
 * empty result can mean two very different things — nothing matched, or nothing
 * has been fetched yet — and the page has to say which, because from the outside
 * they otherwise look identical. That ambiguity was reported as "the search is
 * broken", twice.
 *
 * @var string $term
 * @var list<array<string,mixed>> $results
 * @var list<array<string,mixed>> $trending
 * @var list<array<string,mixed>> $allTime
 * @var int $cachedTotal
 */

use App\Http\View;

$cachedTotal = (int) ($cachedTotal ?? 0);

/**
 * Render one project as a card.
 *
 * @param array<string,mixed> $project Project row.
 * @return void
 */
$renderProject = static function (array $project): void {
    ?>
    <li class="project-card">
        <a class="project-name" href="<?= View::escape($project['url']) ?>" target="_blank" rel="noopener noreferrer">
            <?= View::escape($project['name']) ?>
        </a>

        <p class="project-stars" title="星标数">★ <?= number_format((int) $project['stars']) ?></p>

        <?php if (!empty($project['language'])): ?>
            <span class="pill"><?= View::escape($project['language']) ?></span>
        <?php endif; ?>

        <?php if (!empty($project['description'])): ?>
            <p class="project-desc"><?= View::escape($project['description']) ?></p>
        <?php else: ?>
            <p class="project-desc muted">该仓库没有填写简介。</p>
        <?php endif; ?>
    </li>
    <?php
};

/**
 * Render one ranking section.
 *
 * @param string $heading Section heading.
 * @param string $hint One line explaining what the ranking is.
 * @param list<array<string,mixed>> $projects Project rows.
 * @return void
 */
$renderRanking = static function (string $heading, string $hint, array $projects, int $cachedTotal) use ($renderProject): void {
    ?>
    <section class="panel">
        <h2><?= View::escape($heading) ?></h2>
        <p class="muted"><?= View::escape($hint) ?></p>

        <?php if ($projects === []): ?>
            <?php if ($cachedTotal === 0): ?>
                <div class="empty-state">
                    <p><strong>本地还没有任何仓库数据。</strong></p>
                    <p>
                        检索读的是本地缓存，不是实时调用 GitHub 接口——这样既不消耗接口额度，
                        没有配置令牌也能用。代价是缓存为空时这里就是空的。
                    </p>
                    <p>填充缓存：</p>
                    <pre><code>php bin/fetch-github.php</code></pre>
                    <p class="muted">
                        没有配置 <code>GITHUB_TOKEN</code> 也能运行，只是 GitHub 会严格限制未认证请求的频率。
                        在 <code>.env</code> 中填好令牌后，运行 <code>php tools/doctor.php</code> 可以确认配置无误。
                    </p>
                </div>
            <?php else: ?>
                <p class="muted">这个榜单暂时没有数据，但缓存中已有 <?= (int) $cachedTotal ?> 个仓库，可先使用检索。</p>
            <?php endif; ?>
        <?php else: ?>
            <ul class="project-grid">
                <?php foreach ($projects as $project): ?>
                    <?php $renderProject($project); ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php
};
?>
<section class="feed">
    <header class="feed-header">
        <h1>GitHub 开源猎手</h1>
        <p class="muted">
            两个榜单取自本地缓存；检索在缓存范围内按<strong>仓库名、简介与语言</strong>匹配。
            缓存中现有 <strong><?= (int) $cachedTotal ?></strong> 个仓库。
        </p>
    </header>

    <form method="get" action="/tools/github" class="panel search-form">
        <label class="sr-only" for="q">检索仓库</label>
        <input id="q" name="q" type="search" minlength="2" maxlength="60"
               value="<?= View::escape($term) ?>"
               placeholder="按仓库名、简介或语言检索，例如 python、rust…">
        <button type="submit" class="btn btn-primary">检索</button>
        <?php if ($term !== ''): ?>
            <a class="btn btn-ghost" href="/tools/github">清除</a>
        <?php endif; ?>
    </form>

    <?php if ($term !== ''): ?>
        <section class="panel">
            <h2>「<?= View::escape($term) ?>」的检索结果（<?= count($results) ?>）</h2>

            <?php if ($results === []): ?>
                <div class="empty-state">
                    <?php if ($cachedTotal === 0): ?>
                        <p><strong>检索没有结果，而且本地缓存是空的。</strong></p>
                        <p>
                            这两件事的区别很重要：检索本身在缓存范围内是正常的，
                            只是现在还没有任何数据可供匹配。先填充缓存：
                        </p>
                        <pre><code>php bin/fetch-github.php</code></pre>
                    <?php else: ?>
                        <p><strong>缓存中的 <?= (int) $cachedTotal ?> 个仓库里没有匹配「<?= View::escape($term) ?>」的条目。</strong></p>
                        <p>
                            这个检索只覆盖已抓取的榜单，不是全网搜索。可以试试更短的词、
                            编程语言名（如 python、typescript），或者直接看下面的两个榜单。
                        </p>
                    <?php endif; ?>

                    <p><a class="btn btn-ghost" href="/tools/github">返回榜单</a></p>
                </div>
            <?php else: ?>
                <ul class="project-grid">
                    <?php foreach ($results as $project): ?>
                        <?php $renderProject($project); ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <?php
        $renderRanking('七日新秀榜', '按创建时间筛选近七天内新建、并按星标排序的仓库。', $trending, $cachedTotal);
        $renderRanking('万星总榜', '星标数超过一万的仓库，按星标排序。', $allTime, $cachedTotal);
        ?>
    <?php endif; ?>
</section>
