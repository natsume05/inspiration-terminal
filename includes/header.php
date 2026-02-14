<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : '灵感传输终端'; ?></title>

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

    <link rel="stylesheet" href="assets/css/index.css?v=<?php echo time(); ?>">

    <?php if (isset($style) && $style == 'tools'): ?>
        <link rel="stylesheet" href="assets/css/tools.css?v=<?php echo time(); ?>">
    <?php elseif (isset($style) && $style == 'blog'): ?>
        <link rel="stylesheet" href="assets/css/blog.css?v=<?php echo time(); ?>">
    <?php elseif (isset($style) && $style == 'community'): ?>
        <link rel="stylesheet" href="assets/css/community.css?v=<?php echo time(); ?>">
    <?php elseif (isset($style) && $style == 'steam'): ?>
        <link rel="stylesheet" href="assets/css/steam.css?v=<?php echo time(); ?>">
    <?php elseif (isset($style) && $style == 'tools_sub'): ?>
        <link rel="stylesheet" href="assets/css/tools_sub.css?v=<?php echo time(); ?>">        
    <?php elseif (isset($style) && $style == 'lobby'): ?>
        <link rel="stylesheet" href="assets/css/community_lobby.css?v=<?php echo time(); ?>">    
    <?php elseif (isset($style) && $style == 'shop'): ?>
        <link rel="stylesheet" href="assets/css/shop.css?v=<?php echo time(); ?>">    
    <?php endif; ?>

    <link rel="stylesheet" href="assets/libs/highlight.css">

    <script src="assets/libs/marked.min.js"></script>
    
    <script src="assets/libs/highlight.min.js"></script>
    <script src="assets/libs/xml.min.js"></script>
    <script src="assets/libs/javascript.min.js"></script>
    <script src="assets/libs/languages/php.min.js"></script>
    <script src="assets/libs/css.min.js"></script>
    <script src="assets/libs/sql.min.js"></script>

    <script src="assets/libs/purify.min.js"></script>

    <script>
        // 初始化检查
        document.addEventListener('DOMContentLoaded', () => {
            if(typeof hljs !== 'undefined') hljs.highlightAll();
        });
    </script>
</head>
<body>
    <?php
    $has_unread = false;
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        $n_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $uid AND is_read = 0";
        $n_res = $conn->query($n_sql);
        if ($n_res && $n_res->fetch_assoc()['count'] > 0) {
            $has_unread = true;
        }
    }
    ?>

    <?php if (isset($show_nav) && $show_nav == true): ?>
        <div id="particles"></div> <header>
            <h1><?php echo $page_title; ?></h1>
            <p class="subtitle">“在此刻下你的思想，也许会有回响……”</p>
            
            <div class="user-bar" style="margin-top:10px;">
                <?php if(isset($_SESSION['user_id'])): 
                    // 获取头像 (这里做一个简单的 Session 缓存优化，实际最好查库，但为了性能先这样)
                    // 建议：登录时就把 avatar 存进 Session，或者这里简单查一下
                    // 为了简单，我们先用默认图占位，或者你需要去 login.php 把 avatar 也存进 $_SESSION
                    $u_avatar = isset($_SESSION['avatar']) ? $_SESSION['avatar'] : 'default.png';
                    $nav_avatar = ($u_avatar != 'default.png') ? "assets/uploads/avatars/$u_avatar" : "assets/images/default.png";
                ?>
                    <img src="<?php echo $nav_avatar; ?>" style="width:24px; height:24px; border-radius:50%; vertical-align:middle; margin-right:5px; border:1px solid #45a29e;">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="profile.php" class="nav-link" style="position:relative;">
                        个人中心
                        <?php if ($has_unread): ?>
                            <span style="position:absolute; top:5px; right:-5px; width:8px; height:8px; background:#ff4d4f; border-radius:50%; box-shadow:0 0 5px #ff4d4f;"></span>
                        <?php endif; ?>
                    </a>
                    <a href="community.php?action=logout">断开</a>
                <?php else: ?>
                    <a href="login.php">登录</a> | <a href="register.php">注册</a>
                <?php endif; ?>
            </div>
        </header>
    <?php endif; ?>