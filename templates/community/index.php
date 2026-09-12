<?php

/**
 * Community feed.
 *
 * @var string $csrfField
 * @var string $csrfToken
 * @var string|null $activeCategory
 * @var list<array<string,mixed>> $posts
 * @var list<array<string,mixed>> $categories
 */

use App\Http\View;

$activeCategory = $activeCategory ?? null;
?>
<?php
// Both libraries are UMD bundles and are loaded before the module that uses
// them, so posts render at first paint rather than after a second round trip.
// The Content-Security-Policy allows scripts from this origin only.
?>
<script src="/assets/js/vendor/marked.min.js"></script>
<script src="/assets/js/vendor/purify.min.js"></script>

<section class="feed">
    <header class="feed-header">
        <h1>虚空枢纽</h1>
        <nav class="category-tabs" aria-label="频道筛选">
            <a class="tab <?= $activeCategory === null ? 'tab-active' : '' ?>" href="/community">全部</a>
            <?php foreach ($categories ?? [] as $category): ?>
                <a class="tab <?= $activeCategory === $category['slug'] ? 'tab-active' : '' ?>"
                   href="/community?category=<?= urlencode((string) $category['slug']) ?>">
                    <?= View::escape($category['icon'] . ' ' . $category['name']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <form class="panel compose" method="post" action="/community/posts" enctype="multipart/form-data"
          data-compose-form>
        <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

        <label class="sr-only" for="post-content">帖子内容</label>
        <textarea id="post-content" name="content" rows="4" required maxlength="5000"
                  placeholder="在此刻下你的思想…（最长 5000 字）"></textarea>

        <div class="compose-row">
            <label class="sr-only" for="post-category">频道</label>
            <select id="post-category" name="category">
                <?php foreach ($categories ?? [] as $category): ?>
                    <option value="<?= View::escape($category['slug']) ?>">
                        <?= View::escape($category['icon'] . ' ' . $category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <span class="emoji-picker" data-emoji-picker>
                <button type="button" class="tool-btn" data-emoji-toggle aria-expanded="false">😊 表情</button>
                <span class="emoji-panel" data-emoji-panel hidden></span>
            </span>

            <label class="sr-only" for="post-image">配图</label>
            <input id="post-image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp">

            <button type="submit" class="btn btn-primary">发布</button>
        </div>

        <p class="compose-hint muted">支持 Markdown（标题、列表、引用、代码块）与 <code>[s:name]</code> 表情。</p>

        <p class="form-status" data-form-status role="status" aria-live="polite"></p>
    </form>

    <?php if ($posts === []): ?>
        <p class="muted">虚空之中暂无回响，来发第一条吧。</p>
    <?php endif; ?>

    <?php foreach ($posts as $post): ?>
        <article class="post-card" data-post-id="<?= (int) $post['id'] ?>">
            <header class="post-header">
                <span class="post-author"><?= View::escape($post['display_name'] ?? $post['username']) ?></span>
                <?php if (!empty($post['custom_title'])): ?>
                    <span class="post-title-badge"><?= View::escape($post['custom_title']) ?></span>
                <?php endif; ?>
                <time class="post-time" datetime="<?= View::escape($post['created_at']) ?>">
                    <?= View::escape($post['created_at']) ?>
                </time>
            </header>

            <?php
            // The body is rendered as Markdown by assets/js/markdown.js. The
            // escaped source is emitted inside the element first, so a browser
            // without JavaScript — or a failed bundle request — shows the text
            // instead of an empty post.
            ?>
            <div class="post-body markdown-body"
                 data-markdown-source="<?= View::escape(json_encode((string) $post['content'], JSON_UNESCAPED_UNICODE)) ?>">
                <p class="post-text"><?= nl2br(View::escape($post['content'])) ?></p>
            </div>

            <?php if (!empty($post['image_path'])): ?>
                <img class="post-image" src="<?= View::escape($post['image_path']) ?>" alt="帖子配图" loading="lazy">
            <?php endif; ?>

            <footer class="post-actions">
                <button type="button" class="action-btn <?= ((int) $post['liked_by_viewer']) === 1 ? 'is-active' : '' ?>"
                        data-like
                        data-post-id="<?= (int) $post['id'] ?>"
                        aria-pressed="<?= ((int) $post['liked_by_viewer']) === 1 ? 'true' : 'false' ?>">
                    ♥ <span data-like-count><?= (int) $post['like_count'] ?></span>
                </button>

                <button type="button" class="action-btn" data-toggle-comments
                        data-post-id="<?= (int) $post['id'] ?>"
                        aria-expanded="false">
                    💬 <span data-comment-count><?= (int) $post['comment_count'] ?></span>
                </button>
            </footer>

            <div class="comment-region" data-comment-region hidden>
                <ul class="comment-list" data-comment-list></ul>

                <form class="comment-form" data-comment-form data-post-id="<?= (int) $post['id'] ?>">
                    <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">
                    <label class="sr-only" for="comment-<?= (int) $post['id'] ?>">评论内容</label>
                    <input id="comment-<?= (int) $post['id'] ?>" name="content" type="text"
                           required maxlength="1000" placeholder="写下你的回响…">
                    <button type="submit" class="btn btn-ghost">发送</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</section>
