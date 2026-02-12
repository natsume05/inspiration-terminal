<div class="mobile-footer-nav" style="margin-top: 40px; padding: 20px; text-align: center; border-top: 1px solid #30363d;">
    <a href="index.php" class="dream-btn" style="display: block; width: 100%; max-width: 300px; margin: 0 auto; text-align: center;">
        🏠 返回星际枢纽 (Home)
    </a>
</div>

<footer style="margin-top: 50px; padding: 40px 20px; background: #0b0c10; border-top: 1px solid #1f2937; text-align: center; color: #6b7280; font-size: 0.85rem;">
    
    <div style="max-width: 800px; margin: 0 auto;">
        
        <p id="copyright-text" style="cursor: pointer; user-select: none; transition: color 0.2s;">
            &copy; <?php echo date("Y"); ?> 提瓦特百宝箱 (Teyvat Box). All rights reserved.
        </p>
        
        <p style="margin: 10px 0;">
            <a href="terms.php" style="color: #6b7280; text-decoration: none; margin: 0 10px;">用户协议</a> | 
            
            <a href="privacy.php" style="color: #6b7280; text-decoration: none; margin: 0 10px;">隐私政策</a> | 
            
            <a href="mailto:contact@367588.xyz?subject=侵权投诉&body=尊敬的管理员，我发现以下内容涉嫌侵权..." style="color: #6b7280; text-decoration: none; margin: 0 10px;">侵权投诉 / 联系舰长</a>
        </p>

        <p style="font-size: 0.75rem; opacity: 0.7; line-height: 1.5;">
            免责声明：本站大部分内容由 GitHub API 自动抓取或用户生成。
            本站不存储任何 GitHub 项目源码，所有链接均指向官方仓库。
            若发现内容侵犯了您的权益，请发送邮件至 contact@367588.xyz，我们将于 24 小时内处理。
        </p>
    </div>

</footer>

<script>
    (function() {
        let clickCount = 0;
        let clickTimer;
        const target = document.getElementById('copyright-text');

        if(target) {
            target.addEventListener('click', function() {
                clickCount++;
                
                // 视觉反馈：点击变色
                this.style.color = '#66fcf1'; // 变亮青色
                setTimeout(() => this.style.color = '#6b7280', 150); // 变回去

                // 第 5 次提示
                if (clickCount === 5) {
                    // 使用 console.log 防止弹窗太烦人，或者你可以做一个小的 toast 提示
                    console.log('🔒 检测到异常敲击... 再敲 5 次试试？');
                }
                
                // 第 10 次触发
                if (clickCount >= 10) {
                    if(confirm('🚀 身份确认：舰长。正在前往开发者密室...')) {
                        window.location.href = 'blog.php'; // 传送门：去你的博客
                    }
                    clickCount = 0; // 重置
                }
                
                // 2秒不点就重置，防止误触
                clearTimeout(clickTimer);
                clickTimer = setTimeout(() => { clickCount = 0; }, 2000);
            });
        }
    })();
</script>

</body>
</html>
