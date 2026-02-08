<?php
require 'includes/db.php';

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


<?php
$page_title = "虚空梦语 2.0";
$style = "community"; 
$show_nav = true; 

include 'includes/header.php'; 
?>
    <div class="container">
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

<?php include 'includes/footer.php'; ?>