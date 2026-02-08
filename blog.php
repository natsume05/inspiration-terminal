<?php
require 'includes/db.php';

// 处理博客评论提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_blog_comment'])) {
    $pid = intval($_POST['post_id']);
    $user = isset($_SESSION['username']) ? $_SESSION['username'] : '过客'; // 没登录就叫过客
    $content = $conn->real_escape_string($_POST['content']);
    $conn->query("INSERT INTO blog_comments (post_id, username, content) VALUES ($pid, '$user', '$content')");
    // 刷新页面防止重复提交
    header("Location: blog.php#post-$pid"); exit();
}

$page_title = "深空日志";
$style = "blog"; 
include 'includes/header.php'; 
?>

<div class="blog-header">
    <h1>🚀 深空日志</h1>
    <p>Admin的私人观测站。星际拓荒风格，记录思维的波形与宇宙的余晖。</p>
</div>

<div class="container">

    <?php
    $sql = "SELECT * FROM blog_posts ORDER BY created_at DESC";
    $result = $conn->query($sql);

    if ($result->num_rows > 0):
        while($row = $result->fetch_assoc()):
            $pid = $row['id'];
            // 获取评论数
            $c_res = $conn->query("SELECT COUNT(*) as c FROM blog_comments WHERE post_id = $pid");
            $c_count = $c_res->fetch_assoc()['c'];
    ?>
        <article class="blog-card" id="post-<?php echo $pid; ?>">
            <?php if(!empty($row['cover_image'])): ?>
                <img src="<?php echo htmlspecialchars($row['cover_image']); ?>" class="blog-cover" alt="cover">
            <?php endif; ?>

            <div class="blog-body">
                <h2 class="blog-title"><?php echo htmlspecialchars($row['title']); ?></h2>
                <div class="blog-meta">
                    发布于：<?php echo date('Y-m-d', strtotime($row['created_at'])); ?> 
                    | 阅读：<?php echo $row['views']; ?>
                </div>
                
                <div class="blog-content">
                    <?php echo $row['content']; ?>
                </div>

                <div class="blog-footer">
                    <div class="actions">
                        <span class="action-btn" onclick="alert('博主已收到你的心意 ❤')"> 
                            ❤ 点赞
                        </span>
                        <span class="action-btn" onclick="toggleComments(<?php echo $pid; ?>)"> 
                            💬 评论 (<?php echo $c_count; ?>)
                        </span>
                        <span class="action-btn" onclick="copyLink(<?php echo $pid; ?>)"> 
                            🔗 分享
                        </span>
                    </div>
                </div>

                <div class="comments-box" id="comments-<?php echo $pid; ?>">
                    <?php
                    $com_sql = "SELECT * FROM blog_comments WHERE post_id = $pid ORDER BY created_at ASC";
                    $com_res = $conn->query($com_sql);
                    while($c = $com_res->fetch_assoc()):
                    ?>
                        <div class="comment-item">
                            <span class="comment-user"><?php echo htmlspecialchars($c['username']); ?>:</span>
                            <?php echo htmlspecialchars($c['content']); ?>
                        </div>
                    <?php endwhile; ?>

                    <form class="comment-form" method="POST">
                        <input type="hidden" name="post_id" value="<?php echo $pid; ?>">
                        <input type="text" name="content" class="comment-input" placeholder="写下你的回响..." required>
                        <button type="submit" name="submit_blog_comment" class="comment-submit">发送</button>
                    </form>
                </div>
            </div>
        </article>
    <?php 
        endwhile;
    else:
        echo "<p style='text-align:center; color:#666;'>暂无日志，舰长正在休眠...</p>";
    endif; 
    ?>

</div>

<script>
function toggleComments(id) {
    var el = document.getElementById('comments-' + id);
    el.style.display = (el.style.display === 'block') ? 'none' : 'block';
}
function copyLink(id) {
    var url = window.location.origin + window.location.pathname + "#post-" + id;
    navigator.clipboard.writeText(url).then(() => alert('链接已复制！'));
}
</script>

<?php include 'includes/footer.php'; ?>