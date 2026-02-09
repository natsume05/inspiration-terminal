<?php
// private_notes.php
session_start();
require 'includes/db.php';

// 必须登录
if (!isset($_SESSION['user_id'])) {
    die("请先 <a href='login.php'>登录</a>");
}

$page_title = "私密笔记";
$style = "community"; // 复用社区样式
include 'includes/header.php';
?>

<div class="container" style="max-width: 800px; margin-top: 50px; text-align: center;">
    <div class="post-card secret-corner" style="border-color: #ffae42; min-height: 400px; display:flex; flex-direction:column; justify-content:center; align-items:center;">
        
        <h2 style="color: #ffae42; margin-bottom: 20px;">🚧 施工现场？</h2>
        
        <p style="color: #aaa; line-height: 2;">
            “看起来这里正在装修，只有一堆砖头和路障。”<br>
            但在墙角的阴影里，似乎有一道暗门微微敞开。<br>
            <span style="font-size: 0.8rem; color: #666;">(只有持有密钥的舰长才能察觉)</span>
        </p>

        <div style="margin: 40px 0; font-size: 3rem; filter: grayscale(0.8);">🧱 🏗️ 🤫</div>
        
        <a href="secret_space.php" class="dream-btn" style="width: 200px; background: linear-gradient(135deg, #ffae42, #ff6b6b); color: white;">
            🖐️ 推开暗门
        </a>

        <br>
        <a href="community.php" style="color: #666; font-size: 0.8rem; text-decoration: none; margin-top: 20px;">← 没什么，回圣巢去</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>