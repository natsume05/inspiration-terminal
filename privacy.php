<?php 
// privacy.php
$page_title = "隐私政策";
require 'includes/header.php'; // 假设你的 header 里还没 require db.php，这行主要是为了样式
?>

<div class="container" style="max-width: 800px; margin-top: 40px; color: #ccc; line-height: 1.8;">
    <div style="background: #161b22; padding: 40px; border-radius: 12px; border: 1px solid #30363d;">
        <h2 style="color: #66fcf1; border-bottom: 1px solid #333; padding-bottom: 15px;">🛡️ 隐私政策 (Privacy Policy)</h2>
        
        <h3>1. 信息收集</h3>
        <p>当您注册“虚空梦语”时，我们会收集您的用户名、邮箱地址（用于找回密码）以及您主动发布的帖子内容。</p>

        <h3>2. Cookie 的使用</h3>
        <p>本站使用 Cookie 来维持您的登录状态。我们不会使用 Cookie 跟踪您的跨站行为。</p>

        <h3>3. 数据安全</h3>
        <p>您的密码经过哈希加密存储，即使是管理员也无法查看您的原始密码。我们会采取合理的安全措施保护您的数据。</p>
        
        <h3>4. 第三方服务</h3>
        <p>本站的“GitHub 猎手”功能会调用 GitHub API，相关数据交互遵循 GitHub 的隐私协议。</p>

        <br>
        <a href="index.php" class="dream-btn small">返回首页</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>