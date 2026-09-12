<?php

/**
 * Blog entry.
 *
 * @var array<string,mixed> $entry
 * @var list<array<string,mixed>> $comments
 * @var int $likeCount
 * @var bool $hasLiked
 * @var array<string,mixed>|null $next
 * @var string $csrfField
 * @var string $csrfToken
 * @var array{id:int,name:string,role:string}|null $currentUser
 */

use App\Http\View;

$next = $next ?? null;
$currentUser = $currentUser ?? null;
?>
<article class="blog-entry">
    <header class="blog-entry-header">
        <h1><?= View::escape($entry['title']) ?></h1>
        <p class="blog-meta">
            <span><?= View::escape($entry['display_name'] ?? $entry['username']) ?></span>
            <time datetime="<?= View::escape($entry['created_at']) ?>"><?= View::escape($entry['created_at']) ?></time>
        </p>
    </header>

    <?php if (!empty($entry['cover_image'])): ?>
        <img class="blog-cover" src="<?= View::escape($entry['cover_image']) ?>"
             alt="<?= View::escape($entry['title']) ?> 的封面">
    <?php endif; ?>

    <?php
    // Both libraries are UMD bundles, so they are loaded as classic scripts
    // before the module that uses them. They are served from this origin because
    // the Content-Security-Policy restricts scripts to 'self'. Loading them here
    // rather than from the module keeps the article from rendering empty for the
    // length of a network round trip.
    ?>
    <script src="/assets/js/vendor/marked.min.js"></script>
    <script src="/assets/js/vendor/purify.min.js"></script>

    <?php
    // The raw Markdown travels as a JSON string inside an attribute, so a
    // closing script tag in the content cannot escape the element. The browser
    // module renders it through marked and then DOMPurify, and replaces the
    // escaped source below — which stays visible if that never happens.
    ?>
    <div class="markdown-body"
         data-markdown-source="<?= View::escape(json_encode((string) $entry['content'], JSON_UNESCAPED_UNICODE)) ?>">
        <pre class="markdown-fallback"><?= View::escape($entry['content']) ?></pre>
    </div>

    <footer class="blog-entry-footer">
        <button type="button" class="action-btn <?= $hasLiked ? 'is-active' : '' ?>"
                data-blog-like
                data-slug="<?= View::escape($entry['slug']) ?>"
                aria-pressed="<?= $hasLiked ? 'true' : 'false' ?>">
            ♥ <span data-like-count><?= (int) $likeCount ?></span>
        </button>

        <?php if ($next !== null): ?>
            <a class="btn btn-ghost" href="/blog/<?= urlencode((string) $next['slug']) ?>">
                下一篇：<?= View::escape(mb_strimwidth((string) $next['title'], 0, 24, '…')) ?>
            </a>
        <?php endif; ?>
    </footer>
</article>

<section class="panel comment-panel">
    <h2>回响（<?= count($comments) ?>）</h2>

    <?php if ($comments === []): ?>
        <p class="muted">还没有人回应。</p>
    <?php endif; ?>

    <ul class="comment-list" data-blog-comments>
        <?php foreach ($comments as $comment): ?>
            <li class="comment-item">
                <span class="comment-author"><?= View::escape($comment['display_name']) ?></span>
                <span class="comment-time"><?= View::escape($comment['created_at']) ?></span>
                <p><?= nl2br(View::escape($comment['content'])) ?></p>
            </li>
        <?php endforeach; ?>
    </ul>

    <form class="comment-form" data-blog-comment-form data-slug="<?= View::escape($entry['slug']) ?>">
        <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

        <?php if ($currentUser === null): ?>
            <label class="sr-only" for="guest-name">你的称呼</label>
            <input id="guest-name" name="display_name" type="text" maxlength="32" placeholder="称呼（可留空）">
        <?php endif; ?>

        <label class="sr-only" for="blog-comment">评论内容</label>
        <input id="blog-comment" name="content" type="text" required minlength="2" maxlength="1000"
               placeholder="写下你的回响…">

        <button type="submit" class="btn btn-primary">发送</button>
    </form>

    <p class="form-status" data-form-status role="status" aria-live="polite"></p>
</section>
