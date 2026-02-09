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
                
                <div class="blog-meta-row">
                    <span class="meta-item">📅 <?php echo date('Y.m.d', strtotime($row['created_at'])); ?></span>
                    <span class="meta-item">👁️ <span id="view-count-<?php echo $pid; ?>"><?php echo $row['views']; ?></span> 阅读</span>
                    
                    <?php 
                        $text_content = strip_tags($row['content']); // 去掉 HTML 标签只算纯文字
                        $word_count = mb_strlen($text_content, 'UTF-8');
                        $read_time = ceil($word_count / 300); 
                    ?>
                    <span class="meta-item">⏳ 约 <?php echo $read_time; ?> 分钟</span>
                </div>

                <?php if(!empty($row['tags'])): ?>
                    <div class="blog-tags">
                        <?php 
                        // 把 "生活,游戏" 炸开成数组，循环显示
                        $tags_arr = explode(',', $row['tags']);
                        foreach($tags_arr as $tag): 
                            $tag = trim($tag);
                            if($tag == '') continue;
                        ?>
                            <span class="tag">#<?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
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

// --- 👁️ 真实阅读量统计 (Intersection Observer) ---
document.addEventListener("DOMContentLoaded", function() {
    // 1. 创建一个观察者
    let observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            // 如果帖子出现在屏幕中 (可见比例超过 50%)
            if (entry.isIntersecting) {
                let postId = entry.target.id.replace('post-', '');
                
                // 为了防止重复计数，检查是否已经记过
                if (!sessionStorage.getItem('viewed-' + postId)) {
                    // 发送请求给后台
                    fetch('update_view.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: postId })
                    });
                    
                    // 标记为本次会话已读
                    sessionStorage.setItem('viewed-' + postId, 'true');
                    
                    // (可选) 让界面上的数字也跳动一下 +1
                    let viewSpan = document.getElementById('view-count-' + postId); // 确保你的 span id 叫这个
                    if(viewSpan) viewSpan.innerText = parseInt(viewSpan.innerText) + 1;
                }
            }
        });
    }, { threshold: 0.5 }); // 阈值：露出 50% 就算看

    // 2. 开始观察所有博客卡片
    document.querySelectorAll('.blog-card').forEach(card => {
        observer.observe(card);
    });
});
</script>

<?php include 'includes/footer.php'; ?>