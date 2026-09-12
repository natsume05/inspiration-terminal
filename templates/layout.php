<?php

/**
 * Main layout.
 *
 * @var string $siteName
 * @var array{id:int,name:string,role:string}|null $currentUser
 * @var string $csrfToken
 * @var array<string,string> $flashes
 * @var bool $isModerator
 * @var int $unreadNotifications
 * @var string $pageTitle
 * @var string $content
 */

use App\Http\View;

$pageTitle = $pageTitle ?? '';
$currentUser = $currentUser ?? null;
$flashes = $flashes ?? [];
$isModerator = $isModerator ?? false;
$unreadNotifications = $unreadNotifications ?? 0;

/**
 * Mark the current navigation item so the reader can see where they are.
 *
 * @param string $path Target path.
 * @return string Either `is-current` or an empty string.
 */
$navState = static function (string $path): string {
    $current = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $current = is_string($current) ? rtrim($current, '/') : '/';

    return $current === rtrim($path, '/') ? 'is-current' : '';
};
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
        <a class="<?= $navState('/') ?>" href="/">首页</a>
        <a class="<?= $navState('/blog') ?>" href="/blog">日志</a>
        <?php if ($currentUser !== null): ?>
            <a class="<?= $navState('/community') ?>" href="/community">社区</a>
            <a class="<?= $navState('/tools') ?>" href="/tools">百宝箱</a>
            <a class="<?= $navState('/notes') ?>" href="/notes">思维殿堂</a>
        <?php endif; ?>
        <?php if ($isModerator): ?>
            <a class="<?= $navState('/admin') ?>" href="/admin">控制台</a>
        <?php endif; ?>
    </nav>

    <div class="site-account">
        <?php if ($currentUser !== null): ?>
            <a class="account-link" href="/notifications">
                信号
                <?php if ($unreadNotifications > 0): ?>
                    <span class="badge-count" aria-label="<?= (int) $unreadNotifications ?> 条未读"><?= (int) $unreadNotifications ?></span>
                <?php endif; ?>
            </a>
            <a class="account-name" href="/profile"><?= View::escape($currentUser['name']) ?></a>
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
    <?php if ($currentUser !== null): ?>
        <p class="footer-links">
            <a href="/feedback">信号塔</a> ·
            <a href="/tools">百宝箱</a> ·
            <a href="/notes">思维殿堂</a>
        </p>
    <?php endif; ?>
</footer>

<script type="module" src="/assets/js/app.js"></script>
</body>
</html>
