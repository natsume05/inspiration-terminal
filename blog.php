<?php
require 'includes/db.php';

// 处理随机跃迁
// 随机跃迁逻辑升级
if (isset($_GET['random'])) {
    $rand_sql = "SELECT id FROM blog_posts ORDER BY RAND() LIMIT 1";
    $rand_res = $conn->query($rand_sql);
    if ($rand_res && $rand_res->num_rows > 0) {
        $rand_row = $rand_res->fetch_assoc();
        // 🚀 直接飞向那篇文章的独立页面
        header("Location: view_post.php?id=" . $rand_row['id']);
        exit();
    }
}

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
    <p id="typing-text"></p>
    <a href="blog.php?random=1" class="dream-btn small" style="background: linear-gradient(135deg, #6a11cb, #2575fc); margin-left: 10px;">
    🌀 随机跃迁
</a>
</div>

<div class="music-player" style="margin-top: 15px;">
    <audio id="bgm" loop>
        <source src="assets/audio/travelers.mp3" type="audio/mpeg">
    </audio>
    <button onclick="toggleMusic()" class="dream-btn small" style="width: auto; padding: 5px 15px; font-size: 0.8rem;">
        🎵 播放信号流
    </button>
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
            <div class="blog-card" id="post-<?php echo $pid; ?>">
                    
                    <?php if($row['cover_image']): ?>
                        <a href="view_post.php?id=<?php echo $pid; ?>" style="display:block;">
                            <img src="<?php echo htmlspecialchars($row['cover_image']); ?>" class="blog-cover" alt="Cover">
                        </a>
                    <?php endif; ?>

            <div class="blog-body">
                        
                <h2 class="blog-title">
                    <a href="view_post.php?id=<?php echo $pid; ?>" style="text-decoration:none; color:inherit; transition: color 0.3s;">
                        <?php echo htmlspecialchars($row['title']); ?>
                    </a>
                </h2>
                
                <div class="blog-meta-row">
                        <span class="meta-item">📅 <?php echo date('Y.m.d', strtotime($row['created_at'])); ?></span>
                        <span class="meta-item">👁️ <?php echo $row['views']; ?> 阅读</span>
                                
                        <?php if(!empty($row['tags'])): 
                            $tags_arr = explode(',', $row['tags']);
                            foreach($tags_arr as $tag): 
                                $tag = trim($tag);
                                if($tag == '') continue;
                        ?>
                            <span class="tag">#<?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; endif; ?>
                    </div>
                        
                <div class="blog-content summary" style="color: #aaa; font-size: 0.95rem; margin-top: 15px;">
                                <?php 
                                    // 提取纯文本摘要
                                    $clean_text = strip_tags($row['content']);
                                    echo mb_substr($clean_text, 0, 120, 'utf-8') . '...'; 
                                ?>
                            </div>
                            
                            <div style="margin-top: 25px; text-align: right;">
                                <a href="view_post.php?id=<?php echo $pid; ?>" class="dream-btn small" style="width: auto; display: inline-block; text-decoration: none; color: #cdd4eb;">
                                    📖 阅读完整日志
                                </a>
                            </div>

                        </div>

                <div class="blog-footer">
                            <div class="action-btn" onclick="toggleLike(<?php echo $pid; ?>, this)">
                                ❤ 点赞
                            </div>
                            <div class="action-btn" onclick="sharePost(<?php echo $pid; ?>)">
                                🔗 分享坐标
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
    // // 1. 创建一个观察者（修改为阅读全文再计数，因此注释掉）
    // let observer = new IntersectionObserver((entries) => {
    //     entries.forEach(entry => {
    //         // 如果帖子出现在屏幕中 (可见比例超过 50%)
    //         if (entry.isIntersecting) {
    //             let postId = entry.target.id.replace('post-', '');
                
    //             // 为了防止重复计数，检查是否已经记过
    //             if (!sessionStorage.getItem('viewed-' + postId)) {
    //                 // 发送请求给后台
    //                 fetch('update_view.php', {
    //                     method: 'POST',
    //                     headers: { 'Content-Type': 'application/json' },
    //                     body: JSON.stringify({ id: postId })
    //                 });
                    
    //                 // 标记为本次会话已读
    //                 sessionStorage.setItem('viewed-' + postId, 'true');
                    
    //                 // (可选) 让界面上的数字也跳动一下 +1
    //                 let viewSpan = document.getElementById('view-count-' + postId); // 确保你的 span id 叫这个
    //                 if(viewSpan) viewSpan.innerText = parseInt(viewSpan.innerText) + 1;
    //             }
    //         }
    //     });
    // }, { threshold: 0.5 }); // 阈值：露出 50% 就算看

    // 2. 开始观察所有博客卡片
    document.querySelectorAll('.blog-card').forEach(card => {
        observer.observe(card);
    });
});

// --- ⌨️ 打字机特效 ---
const text = "Admin的私人观测站。星际拓荒风格，记录思维的波形与宇宙的余晖。";
const typeWriterElement = document.getElementById('typing-text');
let i = 0;

function typeWriter() {
    if (i < text.length) {
        typeWriterElement.innerHTML += text.charAt(i);
        i++;
        setTimeout(typeWriter, 50); // 打字速度
    }
}
// 页面加载后启动
window.onload = typeWriter;

// --- 🎵 音乐控制 ---
function toggleMusic() {
    var audio = document.getElementById("bgm");
    var btn = event.target; // 获取按钮
    if (audio.paused) {
        audio.play();
        btn.innerHTML = "⏸️ 暂停信号";
        btn.style.background = "linear-gradient(135deg, #ff6b6b, #ffae42)"; // 变色
    } else {
        audio.pause();
        btn.innerHTML = "🎵 播放信号流";
        btn.style.background = ""; // 恢复原色
    }
}

// 🚀 跃迁导航系统
document.addEventListener("DOMContentLoaded", function() {
    // 1. 获取 URL 中的 highlight 参数
    const urlParams = new URLSearchParams(window.location.search);
    const targetId = urlParams.get('highlight');

    // 2. 如果有目标 ID
    if (targetId) {
        const targetElement = document.getElementById('post-' + targetId);
        
        if (targetElement) {
            // 延迟一点点执行，等待页面布局稳定
            setTimeout(() => {
                // A. 平滑滚动到屏幕中央
                targetElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });

                // B. 添加高亮特效 (CSS 类)
                targetElement.classList.add('signal-locked');
                
                // C. 3秒后移除特效，让它恢复正常
                setTimeout(() => {
                    targetElement.classList.remove('signal-locked');
                }, 3000);
            }, 300);
        }
    }
});

</script>

<?php include 'includes/footer.php'; ?>