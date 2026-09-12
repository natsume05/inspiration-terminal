<?php

/**
 * Main layout.
 *
 * @var string $siteName
 * @var array{id:int,name:string,role:string}|null $currentUser
 * @var string $csrfToken
 * @var array<string,string> $flashes
 * @var string $pageTitle
 * @var string $content
 */

use App\Http\View;

$pageTitle = $pageTitle ?? '';
$currentUser = $currentUser ?? null;
$flashes = $flashes ?? [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::escape($pageTitle !== '' ? $pageTitle . ' · ' . $siteName : $siteName) ?></title>
    <meta name="description" content="<?= View::escape($siteName) ?>——集博客、工具箱与匿名社区于一体的个人门户网站。">
    <meta name="color-scheme" content="dark">
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="manifest" href="/manifest.json">
</head>
<body>
<a class="skip-link" href="#main">跳到主要内容</a>

<header class="site-header">
    <a class="brand" href="/"><?= View::escape($siteName) ?></a>

    <nav class="site-nav" aria-label="主导航">
        <a href="/">首页</a>
        <?php if ($currentUser !== null): ?>
            <a href="/community">社区</a>
        <?php endif; ?>
    </nav>

    <div class="site-account">
        <?php if ($currentUser !== null): ?>
            <span class="account-name"><?= View::escape($currentUser['name']) ?></span>
            <form method="post" action="/logout" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= View::escape($csrfToken) ?>">
                <button type="submit" class="btn btn-ghost">登出</button>
            </form>
        <?php else: ?>
            <a class="btn btn-ghost" href="/login">登录</a>
            <a class="btn" href="/register">注册</a>
        <?php endif; ?>
    </div>
</header>

<?php if ($flashes !== []): ?>
    <div class="flash-stack" role="status">
        <?php foreach ($flashes as $type => $message): ?>
            <p class="flash flash-<?= View::escape($type) ?>"><?= View::escape($message) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main id="main" class="site-main">
    <?= $content ?>
</main>

<footer class="site-footer">
    <p><?= View::escape($siteName) ?> · 原生 PHP 构建，无框架、无构建步骤。</p>
</footer>

<script type="module" src="/assets/js/app.js"></script>
</body>
</html>
