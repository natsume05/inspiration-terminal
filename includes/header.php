<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php page_meta(); ?>

    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0b0c10">
    <link rel="apple-touch-icon" href="assets/images/app-icon.png">

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js')
                    .then(reg => console.log('PWA Service Worker 注册成功:', reg.scope))
                    .catch(err => console.log('PWA 注册失败:', err));
            });
        }
    </script>

    <link rel="stylesheet" href="assets/css/base.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/index.css?v=<?php echo time(); ?>">

    <?php
    // 页面风格 -> 对应样式表映射，避免冗长的 if/elseif 链。
    $style_map = [
        'tools'     => 'assets/css/tools.css',
        'blog'      => 'assets/css/blog.css',
        'community' => 'assets/css/community.css',
        'steam'     => 'assets/css/steam.css',
        'tools_sub' => 'assets/css/tools_sub.css',
        'lobby'     => 'assets/css/community_lobby.css',
        'shop'      => 'assets/css/shop.css',
    ];
    if (!empty($style) && isset($style_map[$style])) {
        echo '<link rel="stylesheet" href="' . $style_map[$style] . '?v=' . time() . '">';
    }
    ?>

    <link rel="stylesheet" href="assets/libs/highlight.css">

    <script src="assets/libs/marked.min.js"></script>
    <script src="assets/libs/highlight.min.js"></script>
    <script src="assets/libs/xml.min.js"></script>
    <script src="assets/libs/javascript.min.js"></script>
    <script src="assets/libs/css.min.js"></script>
    <script src="assets/libs/sql.min.js"></script>
    <script src="assets/libs/purify.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof hljs !== 'undefined') hljs.highlightAll();
        });
    </script>
</head>
<body>
    <a class="skip-link" href="#main-content">跳到主内容</a>

    <?php
    $nav_uid = current_user_id();
    $has_unread = false;
    if ($nav_uid > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param('i', $nav_uid);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->fetch_assoc()['count'] > 0) {
            $has_unread = true;
        }
        $stmt->close();
    }
    ?>

    <?php if (!empty($show_nav)): ?>
        <div id="particles"></div>
        <header class="site-header">
            <h1><?php echo e($page_title); ?></h1>
            <p class="subtitle">“在此刻下你的思想，也许会有回响……”</p>

            <nav class="user-bar" aria-label="用户导航">
                <?php if ($nav_uid > 0): ?>
                    <img src="<?php echo e(get_avatar_url(isset($_SESSION['avatar']) ? $_SESSION['avatar'] : '')); ?>" alt="头像" style="width:24px; height:24px; border-radius:50%; vertical-align:middle; margin-right:5px; border:1px solid #45a29e;">
                    <span><?php echo e($_SESSION['username']); ?></span>
                    <a href="profile.php" class="nav-link" title="查看个人档案" style="position:relative;">
                        个人中心
                        <?php if ($has_unread): ?>
                            <span style="position:absolute; top:5px; right:-5px; width:8px; height:8px; background:#ff4d4f; border-radius:50%; box-shadow:0 0 5px #ff4d4f;"></span>
                        <?php endif; ?>
                    </a>
                    <a href="community.php?action=logout" title="退出登录">断开</a>
                <?php else: ?>
                    <a href="login.php" title="登录账号">登录</a> | <a href="register.php" title="注册新账号">注册</a>
                <?php endif; ?>
            </nav>
        </header>
    <?php endif; ?>

    <main id="main-content">
