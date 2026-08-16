    </main>

<nav class="mobile-footer-nav" aria-label="快捷导航">
    <a href="index.php" class="dream-btn" title="返回首页">
        🏠 返回星际枢纽 (Home)
    </a>
</nav>

<footer class="site-footer">
    <div class="site-footer-inner">
        <nav class="footer-links" aria-label="页脚导航">
            <a href="index.php" title="返回首页">首页</a>
            <a href="blog.php" title="浏览博客">深空日志</a>
            <a href="tools.php" title="打开工具箱">百宝箱</a>
            <a href="community.php" title="进入社区">虚空梦语</a>
        </nav>

        <p id="copyright-text">
            &copy; <?php echo date("Y"); ?> 提瓦特百宝箱 (Teyvat Box). All rights reserved.
        </p>

        <nav class="footer-policy" aria-label="政策链接">
            <a href="terms.php" title="查看用户协议">用户协议</a>
            <span aria-hidden="true">|</span>
            <a href="privacy.php" title="查看隐私政策">隐私政策</a>
            <span aria-hidden="true">|</span>
            <a href="mailto:contact@367588.xyz?subject=侵权投诉&body=尊敬的管理员，我发现以下内容涉嫌侵权..." title="邮件联系站长">侵权投诉 / 联系舰长</a>
        </nav>

        <p class="footer-disclaimer">
            免责声明：本站大部分内容由 GitHub API 自动抓取或用户生成。
            本站不存储任何 GitHub 项目源码，所有链接均指向官方仓库。
            若发现内容侵犯了您的权益，请发送邮件至 contact@367588.xyz，我们将于 24 小时内处理。
        </p>
    </div>
</footer>

<button class="back-to-top" id="back-to-top" aria-label="返回顶部" title="返回顶部">↑</button>

<script>
    // 返回顶部：滚动超过一屏后显示按钮。
    (function () {
        const button = document.getElementById('back-to-top');
        if (!button) return;

        const toggleVisibility = () => {
            button.classList.toggle('visible', window.scrollY > window.innerHeight);
        };

        window.addEventListener('scroll', toggleVisibility, { passive: true });
        button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        toggleVisibility();
    })();
</script>

<script>
    (function () {
        const CLICK_HINT_THRESHOLD = 5;
        const CLICK_TRIGGER_THRESHOLD = 10;
        const RESET_DELAY = 2000;

        let clickCount = 0;
        let clickTimer;
        const target = document.getElementById('copyright-text');

        if (!target) {
            return;
        }

        target.addEventListener('click', function () {
            clickCount++;

            this.style.color = '#66fcf1';
            setTimeout(() => { this.style.color = '#6b7280'; }, 150);

            if (clickCount === CLICK_HINT_THRESHOLD) {
                console.log('🔒 检测到异常敲击... 再敲 5 次试试？');
            }

            if (clickCount >= CLICK_TRIGGER_THRESHOLD) {
                if (confirm('🚀 身份确认：舰长。正在前往开发者密室...')) {
                    window.location.href = 'blog.php';
                }
                clickCount = 0;
            }

            clearTimeout(clickTimer);
            clickTimer = setTimeout(() => { clickCount = 0; }, RESET_DELAY);
        });
    })();
</script>

</body>
</html>
