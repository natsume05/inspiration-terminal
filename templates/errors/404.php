<?php

/**
 * Not-found page.
 *
 * Rendered for an unmatched route and for a blog slug that does not exist, so
 * the response is a real 404 rather than a redirect to the home page.
 */

use App\Http\View;
?>
<section class="panel panel-narrow">
    <h1>404 · 信号丢失</h1>
    <p class="muted">这里没有你寻找的信号。它可能已被删除，或者从未存在过。</p>

    <div class="hero-actions">
        <a class="btn btn-primary" href="/">返回首页</a>
        <a class="btn btn-ghost" href="/blog">浏览日志</a>
    </div>
</section>
