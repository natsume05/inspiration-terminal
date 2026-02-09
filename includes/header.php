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

    <?php if (isset($style) && $style == 'index'): ?>
        <link rel="stylesheet" href="assets/css/index.css">

    <?php elseif (isset($style) && $style == 'blog'): ?>
        <link rel="stylesheet" href="assets/css/blog.css">
    
    <?php elseif (isset($style) && $style == 'community'): ?>
        <link rel="stylesheet" href="assets/css/community.css">
    
    <?php elseif (isset($style) && $style == 'tools'): ?>
        <link rel="stylesheet" href="assets/css/tools.css">
    <?php endif; ?>

</head>
<body>
    
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
                    <a href="profile.php" style="color: #66fcf1;">[个人中心]</a> 
                    <a href="community.php?action=logout">断开</a>
                <?php else: ?>
                    <a href="login.php">登录</a> | <a href="register.php">注册</a>
                <?php endif; ?>
            </div>
        </header>
    <?php endif; ?>