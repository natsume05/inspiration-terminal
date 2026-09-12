<?php

/**
 * Blog index.
 *
 * @var list<array<string,mixed>> $entries
 * @var int $page
 * @var bool $hasNext
 * @var array<string,mixed>|null $announcement
 */

use App\Http\View;

$announcement = $announcement ?? null;
?>
<?php if ($announcement !== null): ?>
    <aside class="announcement" role="note">
        <strong>站内广播</strong>
        <p><?= nl2br(View::escape($announcement['content'])) ?></p>
    </aside>
<?php endif; ?>

<section class="feed">
    <header class="feed-header">
        <h1>深空日志</h1>
        <p class="muted">记录技术思考、项目复盘与灵感片段。</p>
    </header>

    <?php if ($entries === []): ?>
        <p class="muted">还没有发布任何日志。</p>
    <?php endif; ?>

    <?php foreach ($entries as $entry): ?>
        <?php
        // The cover column is always present. Emitting it only when an entry has
        // one left the list ragged, and a reader cannot tell a deliberate text-only
        // card from a cover image that failed to load.
        $cover = !empty($entry['cover_image']) ? (string) $entry['cover_image'] : '/assets/images/cover-missing.svg';
        ?>
        <article class="blog-card">
            <a class="blog-cover-link" href="/blog/<?= urlencode((string) $entry['slug']) ?>">
                <img class="blog-cover" src="<?= View::escape($cover) ?>"
                     alt="<?= View::escape($entry['title']) ?> 的封面" loading="lazy">
            </a>

            <div class="blog-body">
                <h2 class="blog-title">
                    <a href="/blog/<?= urlencode((string) $entry['slug']) ?>"><?= View::escape($entry['title']) ?></a>
                </h2>

                <p class="blog-meta">
                    <span><?= View::escape($entry['display_name'] ?? $entry['username']) ?></span>
                    <time datetime="<?= View::escape($entry['created_at']) ?>"><?= View::escape($entry['created_at']) ?></time>
                    <span><?= (int) $entry['comment_count'] ?> 条回响</span>
                </p>

                <?php if (!empty($entry['excerpt'])): ?>
                    <p class="blog-excerpt"><?= View::escape($entry['excerpt']) ?></p>
                <?php endif; ?>

                <a class="btn btn-ghost" href="/blog/<?= urlencode((string) $entry['slug']) ?>">阅读全文</a>
            </div>
        </article>
    <?php endforeach; ?>

    <nav class="pager" aria-label="分页">
        <?php if ($page > 1): ?>
            <a class="btn btn-ghost" href="/blog?page=<?= (int) $page - 1 ?>">上一页</a>
        <?php endif; ?>
        <?php if ($hasNext): ?>
            <a class="btn btn-ghost" href="/blog?page=<?= (int) $page + 1 ?>">下一页</a>
        <?php endif; ?>
    </nav>
</section>
