<a href="index.php" class="home-fab" title="返回">↩</a>
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

