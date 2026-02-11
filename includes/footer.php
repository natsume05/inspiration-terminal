<div class="mobile-footer-nav" style="margin-top: 40px; padding: 20px; text-align: center; border-top: 1px solid #30363d;">
    <a href="index.php" class="dream-btn" style="display: block; width: 100%; max-width: 300px; margin: 0 auto; text-align: center;">
        🏠 返回星际枢纽 (Home)
    </a>
</div>

<footer style="margin-top: 50px; padding: 40px 20px; background: #0b0c10; border-top: 1px solid #1f2937; text-align: center; color: #6b7280; font-size: 0.85rem;">
    
    <div style="max-width: 800px; margin: 0 auto;">
        <p>&copy; <?php echo date("Y"); ?> 提瓦特百宝箱 (Teyvat Box). All rights reserved.</p>
        
        <p style="margin: 10px 0;">
            <a href="terms.php" style="color: #6b7280; text-decoration: none; margin: 0 10px;">用户协议</a> | 
            <a href="privacy.php" style="color: #6b7280; text-decoration: none; margin: 0 10px;">隐私政策</a> | 
            <a href="mailto:contact@367588.xyz" style="color: #6b7280; text-decoration: none; margin: 0 10px;">侵权投诉 / 联系舰长</a>
        </p>

        <p style="font-size: 0.75rem; opacity: 0.7; line-height: 1.5;">
            免责声明：本站大部分内容由 GitHub API 自动抓取或用户生成。
            本站不存储任何 GitHub 项目源码，所有链接均指向官方仓库。
            若发现内容侵犯了您的权益，请发送邮件至 contact@367588.xyz，我们将于 24 小时内处理。
        </p>
    </div>

</footer>

<script>
        // 1. 修改后的切换逻辑：默认是显示的，所以点击是“隐藏”
        function toggleComments(id) {
            var el = document.getElementById('comments-' + id);
            // 如果已经是隐藏的，则显示；否则隐藏
            if (el.style.display === 'none') {
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
            }
        }

        // 2. 分享功能
        function sharePost(id) {
            var url = window.location.origin + window.location.pathname + "#post-" + id;
            navigator.clipboard.writeText(url).then(function() {
                alert('链接已复制到剪贴板！');
            });
        }

        // 3. 点赞功能
        function toggleLike(postId, btn) {
            fetch('like_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.querySelector('.count').innerText = data.count;
                    if (data.action === 'like') {
                        btn.classList.add('liked');
                    } else {
                        btn.classList.remove('liked');
                    }
                } else {
                    if(data.msg === '请先登录') window.location.href = 'login.php';
                    else alert(data.msg);
                }
            });
        }
    </script>
</body>
</html>

