<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : '灵感传输终端'; ?></title>

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
                <?php if(isset($_SESSION['user_id'])): ?>
                    <span>🎭 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="profile.php" style="color: #66fcf1;">[个人中心]</a> 
                    <a href="community.php?action=logout">断开</a>
                <?php else: ?>
                    <a href="login.php">登录</a> | <a href="register.php">注册</a>
                <?php endif; ?>
            </div>
        </header>
    <?php endif; ?>