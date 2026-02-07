<?php
require 'db.php'; 

// 1. 登出逻辑
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: community.php");
    exit();
}

// 2. 处理发帖
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_post'])) {
    if (!isset($_SESSION['user_id'])) die("请先登录！");
    $author = $_SESSION['username']; 
    $content = $conn->real_escape_string($_POST['content']);
    $sql = "INSERT INTO posts (author, content) VALUES ('$author', '$content')";
    if ($conn->query($sql)) {
        header("Location: community.php"); exit();
    }
}

// 3. 处理发表评论
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_comment'])) {
    if (!isset($_SESSION['user_id'])) die("请先登录！");
    $post_id = intval($_POST['post_id']);
    $user_id = $_SESSION['user_id'];
    $comment_content = $conn->real_escape_string($_POST['comment_content']);
    
    $sql = "INSERT INTO comments (post_id, user_id, content) VALUES ($post_id, $user_id, '$comment_content')";
    if ($conn->query($sql)) {
        header("Location: community.php#post-" . $post_id); 
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>虚空梦语 | 圣巢遗迹</title>
    <style>
        /* --- 基础样式 --- */
        :root { --void-bg: #0b0c10; --pale-text: #c5c6c7; --soul-blue: #66fcf1; --infection-orange: #ffae42; --stone-border: #45a29e; }
        body { margin: 0; padding: 0; background-color: var(--void-bg); color: var(--pale-text); font-family: 'Georgia', 'Songti SC', serif; min-height: 100vh; display: flex; flex-direction: column; align-items: center; overflow-x: hidden; }
        #particles { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; background: radial-gradient(circle at center, #1f2833 0%, #0b0c10 100%); }
        header { margin-top: 50px; text-align: center; border-bottom: 2px solid var(--stone-border); padding-bottom: 20px; width: 80%; max-width: 800px; }
        h1 { font-size: 2.5rem; letter-spacing: 5px; color: var(--pale-text); text-shadow: 0 0 10px rgba(102, 252, 241, 0.3); }
        .container { width: 80%; max-width: 800px; margin-top: 30px; padding-bottom: 80px; }
        
        /* 登录栏与输入框 */
        .user-bar { text-align: right; margin-bottom: 20px; font-size: 0.9rem; }
        .user-bar a { color: #ffae42; margin-left: 10px; text-decoration: none; }
        .input-area { background: rgba(11, 12, 16, 0.8); border: 1px solid var(--stone-border); padding: 20px; border-radius: 5px; margin-bottom: 40px; }
        textarea { width: 100%; background: transparent; border: none; border-bottom: 1px solid #45a29e; color: var(--pale-text); font-family: inherit; font-size: 1.1rem; padding: 10px; resize: vertical; min-height: 80px; outline: none; box-sizing: border-box; }
        .dream-nail-btn { background: transparent; border: 1px solid var(--pale-text); color: var(--pale-text); padding: 8px 25px; cursor: pointer; float: right; margin-top: 10px; transition: all 0.3s; }
        .dream-nail-btn:hover { border-color: var(--soul-blue); color: var(--soul-blue); box-shadow: 0 0 10px var(--soul-blue); }

        /* --- 帖子卡片 --- */
        .post-card { background: rgba(31, 40, 51, 0.4); border-left: 3px solid var(--stone-border); margin-bottom: 25px; padding: 20px; position: relative; scroll-margin-top: 20px; }
        .post-header { font-size: 0.8rem; color: #66fcf1; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .post-content { font-size: 1.1rem; line-height: 1.6; white-space: pre-wrap; word-break: break-all; margin-bottom: 15px; }
        .delete-btn { color: #ff4242; font-size: 0.8rem; text-decoration: none; border: 1px solid #ff4242; padding: 2px 8px; border-radius: 4px; }
        
        /* --- 互动栏 --- */
        .post-actions { border-top: 1px dashed rgba(69, 162, 158, 0.3); padding-top: 10px; display: flex; gap: 20px; font-size: 0.9rem; color: #888; }
        .action-item { cursor: pointer; display: flex; align-items: center; gap: 5px; transition: color 0.3s; }
        .action-item:hover { color: var(--soul-blue); }
        .action-item.liked { color: #ff4242; }
        .action-item.liked span { animation: heartBeat 0.5s; }

        /* --- 评论区 (修改点：默认为 block 显示) --- */
        .comments-section { margin-top: 15px; padding-top: 10px; background: rgba(0,0,0,0.2); padding: 10px; display: block; }
        .comment-row { font-size: 0.9rem; border-bottom: 1px solid rgba(255,255,255,0.05); padding: 8px 0; }
        .comment-user { color: var(--soul-blue); font-weight: bold; margin-right: 5px; }
        .comment-time { float: right; font-size: 0.8em; color: #666; }
        .comment-form { margin-top: 15px; display: flex; gap: 10px; }
        .comment-input { flex: 1; background: rgba(0,0,0,0.3); border: 1px solid #444; color: #ccc; padding: 5px; }
        .comment-submit { background: #333; color: #ccc; border: 1px solid #555; cursor: pointer; padding: 5px 15px; }

        /* 返回按钮 */
        .home-fab { position: fixed; bottom: 30px; right: 30px; width: 50px; height: 50px; background: rgba(69, 162, 158, 0.8); color: #fff; border-radius: 50%; display: flex; justify-content: center; align-items: center; text-decoration: none; font-size: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); z-index: 999; }
        @keyframes heartBeat { 0%{transform:scale(1);} 50%{transform:scale(1.3);} 100%{transform:scale(1);} }
    </style>
</head>
<body>

    <div id="particles"></div>
    
    <header>
        <h1>虚空梦语 2.0</h1>
        <p class="subtitle">“在此刻下你的思想，也许会有回响……”</p>
    </header>

    <div class="container">
        <div class="user-bar">
            <?php if(isset($_SESSION['user_id'])): ?>
                <span>🎭 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="profile.php" style="color: #66fcf1;">[个人中心]</a> 
                <a href="community.php?action=logout">断开</a>
            <?php else: ?>
                <a href="login.php">登录</a> | <a href="register.php">注册</a>
            <?php endif; ?>
        </div>

        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="input-area">
                <form action="community.php" method="POST">
                    <textarea name="content" placeholder="在这里挥动梦之钉，留下你的低语..." required></textarea>
                    <button type="submit" name="submit_post" class="dream-nail-btn">刻录石碑</button>
                    <div style="clear:both;"></div>
                </form>
            </div>
        <?php endif; ?>

        <div id="post-list">
            <?php
            // 获取帖子
            $current_uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $sql = "SELECT 
                        p.*, 
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = $current_uid) as is_liked
                    FROM posts p 
                    ORDER BY p.created_at DESC";
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $pid = $row['id'];
                    $liked_class = ($row['is_liked'] > 0) ? 'liked' : '';
                    
                    echo '<div class="post-card" id="post-'.$pid.'">';
                    // Header
                    echo '  <div class="post-header">';
                    echo '    <div>🔮 '.htmlspecialchars($row["author"]).' <span style="color:#666; margin-left:10px;">'.time_ago($row["created_at"]).'</span></div>';
                    if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
                        echo '    <a href="delete_post.php?id='.$pid.'" class="delete-btn" onclick="return confirm(\'确定删除？\')">删除</a>';
                    }
                    echo '  </div>';
                    
                    // Content
                    echo '  <div class="post-content">'.nl2br(htmlspecialchars($row["content"])).'</div>';
                    
                    // Actions
                    echo '  <div class="post-actions">';
                    echo '    <div class="action-item '.$liked_class.'" onclick="toggleLike('.$pid.', this)">';
                    echo '      <span>❤</span> <b class="count">'.$row['like_count'].'</b>';
                    echo '    </div>';
                    echo '    <div class="action-item" onclick="toggleComments('.$pid.')">💬 评论</div>';
                    echo '    <div class="action-item" onclick="sharePost('.$pid.')">🔗 分享</div>';
                    echo '  </div>';

                    // Comments Section (默认显示)
                    echo '  <div class="comments-section" id="comments-'.$pid.'">';
                    $c_sql = "SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE post_id = $pid ORDER BY c.created_at ASC";
                    $c_res = $conn->query($c_sql);
                    
                    if($c_res->num_rows > 0) {
                        while($c = $c_res->fetch_assoc()) {
                            echo '<div class="comment-row">';
                            echo '  <span class="comment-user">'.$c['username'].':</span>';
                            echo    htmlspecialchars($c['content']);
                            echo '  <span class="comment-time">'.time_ago($c['created_at']).'</span>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div style="color:#666; font-size:0.8rem; padding:5px;">暂无回响...</div>';
                    }

                    if(isset($_SESSION['user_id'])) {
                        echo '<form class="comment-form" method="POST" action="community.php">';
                        echo '  <input type="hidden" name="post_id" value="'.$pid.'">';
                        echo '  <input type="text" name="comment_content" class="comment-input" placeholder="回应这则梦语..." required>';
                        echo '  <button type="submit" name="submit_comment" class="comment-submit">发送</button>';
                        echo '</form>';
                    }
                    echo '  </div>'; 
                    echo '</div>'; 
                }
            } else {
                echo "<p style='text-align:center; margin-top:50px;'>虚空之中一片寂静...</p>";
            }
            ?>
        </div>
    </div>
    
    <a href="index.html" class="home-fab" title="返回">↩</a>

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