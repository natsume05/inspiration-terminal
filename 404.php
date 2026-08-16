<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

http_response_code(404);

$page_title = "页面未找到";
$page_description = "您访问的页面不存在或已被移除，请返回首页继续浏览。";
$page_keywords = "404,页面未找到";
$show_nav = false;

include __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 640px; margin: 80px auto; text-align: center;">
    <div style="font-size: 4.5rem;" aria-hidden="true">🚀</div>
    <h1 style="font-size: 2.2rem; margin: 20px 0 10px;">404 · 信号丢失</h1>
    <p style="color: #5f6368; line-height: 1.8;">
        你访问的页面不存在，或已被移动到其他坐标。
    </p>

    <div style="margin: 30px 0; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <a href="index.php" class="dream-btn small" title="返回首页">🏠 返回首页</a>
        <a href="blog.php" class="dream-btn small" style="background: linear-gradient(135deg, #6a11cb, #2575fc);" title="浏览博客">深空日志</a>
        <a href="community.php" class="dream-btn small" title="进入社区">虚空梦语</a>
    </div>

    <p style="font-size: 0.85rem; color: #9aa0a6;">
        将在 <span id="countdown">5</span> 秒后自动返回首页。
    </p>
</div>

<script>
    let secondsLeft = 5;
    const countdown = document.getElementById('countdown');
    setInterval(() => {
        secondsLeft--;
        countdown.textContent = secondsLeft;
        if (secondsLeft <= 0) {
            window.location.href = 'index.php';
        }
    }, 1000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
