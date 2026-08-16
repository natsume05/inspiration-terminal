<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

// 随机跃迁：跳到一篇随机日志。
if (isset($_GET['random'])) {
    $rand_res = $conn->query("SELECT id FROM blog_posts ORDER BY RAND() LIMIT 1");
    if ($rand_res && $rand_res->num_rows > 0) {
        $rand_row = $rand_res->fetch_assoc();
        redirect("view_post.php?id=" . (int) $rand_row['id']);
    }
}

// 处理博客评论提交。
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_blog_comment'])) {
    $post_id = post_int('post_id');
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '过客';
    $content = post_text('content');

    if ($post_id > 0 && $content !== '') {
        $stmt = $conn->prepare("INSERT INTO blog_comments (post_id, username, content) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $post_id, $username, $content);
        $stmt->execute();
        $stmt->close();
    }
    redirect("blog.php#post-$post_id");
}

$page_title = "深空日志";
$style = "blog";

include __DIR__ . '/includes/header.php';
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
    <button onclick="toggleMusic(this)" class="dream-btn small" style="width: auto; padding: 5px 15px; font-size: 0.8rem;">
        🎵 播放信号流
    </button>
</div>

<div class="container">
    <?php
    $sql = "SELECT * FROM blog_posts ORDER BY created_at DESC";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
            $pid = (int) $row['id'];
    ?>
            <div class="blog-card" id="post-<?php echo $pid; ?>">
                <?php if ($row['cover_image']): ?>
                    <a href="view_post.php?id=<?php echo $pid; ?>" style="display:block;">
                        <img src="<?php echo e($row['cover_image']); ?>" class="blog-cover" alt="Cover">
                    </a>
                <?php endif; ?>

                <div class="blog-body">
                    <h2 class="blog-title">
                        <a href="view_post.php?id=<?php echo $pid; ?>" style="text-decoration:none; color:inherit; transition: color 0.3s;">
                            <?php echo e($row['title']); ?>
                        </a>
                    </h2>

                    <div class="blog-meta-row">
                        <span class="meta-item">📅 <?php echo date('Y.m.d', strtotime($row['created_at'])); ?></span>
                        <span class="meta-item">👁️ <?php echo (int) $row['views']; ?> 阅读</span>

                        <?php if (!empty($row['tags'])):
                            $tags_arr = array_filter(array_map('trim', explode(',', $row['tags'])));
                            foreach ($tags_arr as $tag):
                        ?>
                            <span class="tag">#<?php echo e($tag); ?></span>
                        <?php endforeach; endif; ?>
                    </div>

                    <div class="blog-content summary" style="color: #aaa; font-size: 0.95rem; margin-top: 15px;">
                        <?php echo mb_substr(strip_tags($row['content']), 0, 120, 'utf-8') . '...'; ?>
                    </div>

                    <div style="margin-top: 25px; text-align: right;">
                        <a href="view_post.php?id=<?php echo $pid; ?>" class="dream-btn small" style="width: auto; display: inline-block; text-decoration: none; color: #cdd4eb;">
                            📖 阅读完整日志
                        </a>
                    </div>
                </div>

                <div class="blog-footer">
                    <div class="action-btn" onclick="toggleLike(<?php echo $pid; ?>, this)">❤ 点赞</div>
                    <div class="action-btn" onclick="sharePost(<?php echo $pid; ?>)">🔗 分享坐标</div>
                </div>

                <div class="comments-box" id="comments-<?php echo $pid; ?>">
                    <?php
                    $stmt = $conn->prepare("SELECT username, content FROM blog_comments WHERE post_id = ? ORDER BY created_at ASC");
                    $stmt->bind_param('i', $pid);
                    $stmt->execute();
                    $com_res = $stmt->get_result();
                    while ($c = $com_res->fetch_assoc()):
                    ?>
                        <div class="comment-item">
                            <span class="comment-user"><?php echo e($c['username']); ?>:</span>
                            <?php echo e($c['content']); ?>
                        </div>
                    <?php endwhile; $stmt->close(); ?>

                    <form class="comment-form" method="POST">
                        <input type="hidden" name="post_id" value="<?php echo $pid; ?>">
                        <input type="text" name="content" class="comment-input" placeholder="写下你的回响..." required>
                        <button type="submit" name="submit_blog_comment" class="comment-submit">发送</button>
                    </form>
                </div>
            </div>
        <?php
        endwhile;
    else:
        echo "<p style='text-align:center; color:#666;'>暂无日志，舰长正在休眠...</p>";
    endif;
    ?>
</div>

<script>
function copyLink(id) {
    const url = window.location.origin + window.location.pathname + "#post-" + id;
    navigator.clipboard.writeText(url).then(() => alert('链接已复制！'));
}

// 打字机特效
const typewriterText = "Admin的私人观测站。星际拓荒风格，记录思维的波形与宇宙的余晖。";
const typewriterElement = document.getElementById('typing-text');
let typewriterIndex = 0;

function typeWriter() {
    if (typewriterIndex < typewriterText.length) {
        typewriterElement.innerHTML += typewriterText.charAt(typewriterIndex);
        typewriterIndex++;
        setTimeout(typeWriter, 50);
    }
}
document.addEventListener('DOMContentLoaded', typeWriter);

// 音乐控制
function toggleMusic(btn) {
    const audio = document.getElementById("bgm");
    if (audio.paused) {
        audio.play();
        btn.innerHTML = "⏸️ 暂停信号";
        btn.style.background = "linear-gradient(135deg, #ff6b6b, #ffae42)";
    } else {
        audio.pause();
        btn.innerHTML = "🎵 播放信号流";
        btn.style.background = "";
    }
}

// 跃迁导航系统：读取 highlight 参数并平滑定位到对应日志。
document.addEventListener("DOMContentLoaded", function () {
    const targetId = new URLSearchParams(window.location.search).get('highlight');
    if (!targetId) return;

    const targetElement = document.getElementById('post-' + targetId);
    if (!targetElement) return;

    setTimeout(() => {
        targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        targetElement.classList.add('signal-locked');
        setTimeout(() => targetElement.classList.remove('signal-locked'), 3000);
    }, 300);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
