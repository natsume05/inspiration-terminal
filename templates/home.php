<?php

/**
 * Home page.
 *
 * @var string $siteName
 * @var array{id:int,name:string,role:string}|null $currentUser
 * @var list<array<string,mixed>> $categories
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

<section class="hero">
    <h1><?= View::escape($siteName) ?></h1>
    <p class="hero-sub">一个集博客、工具箱与匿名社区于一体的个人门户。</p>

    <div class="hero-actions">
        <?php if ($currentUser !== null): ?>
            <a class="btn btn-primary" href="/community">进入社区</a>
        <?php else: ?>
            <a class="btn btn-primary" href="/register">创建账号</a>
            <a class="btn btn-ghost" href="/login">已有账号，登录</a>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <h2>频道</h2>
    <?php if ($categories === []): ?>
        <p class="muted">尚未配置任何频道。</p>
    <?php else: ?>
        <ul class="category-list">
            <?php foreach ($categories as $category): ?>
                <li class="category-item">
                    <span class="category-icon" aria-hidden="true"><?= View::escape($category['icon']) ?></span>
                    <span class="category-name"><?= View::escape($category['name']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>这个项目</h2>
    <ul class="feature-list">
        <li>原生 PHP + MySQL，无框架、无构建步骤。</li>
        <li>CSRF 校验由路由层统一强制，接口无法遗漏。</li>
        <li>星尘经济的所有奖励都按天上限，无法刷取。</li>
        <li>私密笔记使用 AES-256-GCM 加密，密钥不落库。</li>
    </ul>
</section>
