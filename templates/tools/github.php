<?php

/**
 * GitHub rankings and search.
 *
 * @var string $term
 * @var list<array<string,mixed>> $results
 * @var list<array<string,mixed>> $trending
 * @var list<array<string,mixed>> $allTime
 */

use App\Http\View;

/**
 * Render one ranking table.
 *
 * @param string $heading Section heading.
 * @param list<array<string,mixed>> $projects Project rows.
 * @return void
 */
$renderRanking = static function (string $heading, array $projects): void {
    ?>
    <section class="panel">
        <h2><?= View::escape($heading) ?></h2>

        <?php if ($projects === []): ?>
            <p class="muted">
                暂无缓存数据。配置 <code>GITHUB_TOKEN</code> 并运行
                <code>php bin/fetch-github.php</code> 可抓取榜单。
            </p>
        <?php else: ?>
            <ol class="project-list">
                <?php foreach ($projects as $project): ?>
                    <li class="project-item">
                        <a href="<?= View::escape($project['url']) ?>" target="_blank" rel="noopener noreferrer">
                            <?= View::escape($project['name']) ?>
                        </a>
                        <span class="project-stars">★ <?= number_format((int) $project['stars']) ?></span>
                        <?php if (!empty($project['language'])): ?>
                            <span class="pill"><?= View::escape($project['language']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($project['description'])): ?>
                            <p class="project-desc"><?= View::escape($project['description']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
    <?php
};
?>
<section class="feed">
    <header class="feed-header">
        <h1>GitHub 开源猎手</h1>
    </header>

    <form method="get" action="/tools/github" class="panel search-form">
        <label class="sr-only" for="q">搜索仓库</label>
        <input id="q" name="q" type="search" minlength="2" maxlength="60"
               value="<?= View::escape($term) ?>" placeholder="按名称或描述检索已缓存的仓库…">
        <button type="submit" class="btn btn-primary">检索</button>
    </form>

    <?php if ($term !== ''): ?>
        <section class="panel">
            <h2>「<?= View::escape($term) ?>」的检索结果（<?= count($results) ?>）</h2>

            <?php if ($results === []): ?>
                <p class="muted">没有匹配的仓库。检索范围是已经抓取到本地的榜单。</p>
            <?php else: ?>
                <ol class="project-list">
                    <?php foreach ($results as $project): ?>
                        <li class="project-item">
                            <a href="<?= View::escape($project['url']) ?>" target="_blank" rel="noopener noreferrer">
                                <?= View::escape($project['name']) ?>
                            </a>
                            <span class="project-stars">★ <?= number_format((int) $project['stars']) ?></span>
                            <?php if (!empty($project['description'])): ?>
                                <p class="project-desc"><?= View::escape($project['description']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <?php
        $renderRanking('七日新秀榜', $trending);
        $renderRanking('万星总榜', $allTime);
        ?>
    <?php endif; ?>
</section>
