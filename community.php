<?php
require 'includes/db.php';
require_once 'includes/image_helper.php';

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

    // 🟢 新增：图片处理
    $image_path = NULL;
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
        // 定义基础文件名 (不要后缀)
        $base_name = "post_" . time() . "_" . rand(100,999);
        // 定义目标文件夹路径 (不带文件名)
        $target_dir = "assets/uploads/community/";
        
        // 🔥 调用加工厂！
        // 参数：临时文件, 目标路径+文件名(无后缀), 最大宽度1000px, 质量75
        $processed_name = upload_and_compress_webp(
            $_FILES['post_image']['tmp_name'], 
            $target_dir . $base_name, 
            1000, 
            75
        );

        if ($processed_name) {
            $image_path = $processed_name; // 数据库里存的是 xxx.webp
        }
    }

    // 🟢 修改 SQL：插入 image 字段
    $sql = "INSERT INTO posts (author, content, image) VALUES ('$author', '$content', '$image_path')";
    
    if ($conn->query($sql) === TRUE) {
        header("Location: community.php"); exit();
    } else {
        echo "Error: " . $conn->error;
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
    <div class="container community-layout">
    <div class="main-column">
        
        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="input-card">
                <form action="community.php" method="POST" enctype="multipart/form-data">
                    <textarea name="content" placeholder="在这里挥动梦之钉，留下你的低语..." required></textarea>
                    
                    <div style="margin-top: 10px;">
                        <label style="cursor: pointer; color: #66fcf1; font-size: 0.9rem;">
                            📷 添加图片
                            <input type="file" name="post_image" accept="image/*" style="display:none;" onchange="document.getElementById('file-name').innerText = this.files[0].name">
                        </label>
                        <span id="file-name" style="color: #666; font-size: 0.8rem; margin-left: 10px;"></span>
                    </div>

                    <div class="input-actions">
                        <span style="font-size:0.8rem; color:#666;">支持 Markdown 语法</span>
                        <button type="submit" name="submit_post" class="dream-btn">✨ 刻录石碑</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div id="post-list">
            <?php
            // 获取帖子逻辑保持不变...
            $current_uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $current_uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

            $sql = "SELECT p.*, u.username, u.avatar, 
                    (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
                    (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = $current_uid) as is_liked
                    FROM posts p 
                    LEFT JOIN users u ON p.author = u.username 
                    ORDER BY p.created_at DESC";
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $pid = $row['id'];
                    $liked_class = ($row['is_liked'] > 0) ? 'liked' : '';
                    
                    // 👑 辐光身份判定 (如果是 natsume05，显示金标)
                    $author_badge = '';
                    if ($row['author'] == 'MingMo') { // 换成你的用户名
                        $author_badge = '<span class="admin-badge" title="站长">🛡️ 辐光</span>';
                    }
            ?>
                <div class="post-card" id="post-<?php echo $pid; ?>">
                    <div class="post-header">
                        <div class="author-info">

                            <?php 
                                $auth_avatar = ($row['avatar'] && $row['avatar'] != 'default.png') 
                                            ? "assets/uploads/avatars/".$row['avatar'] 
                                            : "assets/images/default.png";
                            ?>
                            <div class="avatar-circle" style="background:none; border:none; overflow:hidden;">
                                <img src="<?php echo $auth_avatar; ?>" style="width:100%; height:100%; object-fit:cover;">
                            </div>

                            <span class="author-name"><?php echo htmlspecialchars($row['author']); ?></span>
                            <?php echo $author_badge; ?>
                            <span class="post-time"><?php echo time_ago($row['created_at']); ?></span>
                        </div>
                        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                            <a href="delete_post.php?id=<?php echo $pid; ?>" class="delete-btn" onclick="return confirm('确定删除？')">×</a>
                        <?php endif; ?>
                    </div>

                    <div class="post-content"><?php echo nl2br(htmlspecialchars($row["content"])); ?></div>
                    <?php if (!empty($row['image'])): ?>
                        <div class="post-image" style="margin-top: 10px; margin-bottom: 15px;">
                            <img src="assets/uploads/community/<?php echo $row['image']; ?>" style="max-width: 100%; border-radius: 8px; border: 1px solid #333; max-height: 400px; object-fit: contain;">
                        </div>
                    <?php endif; ?>

                    <div class="post-actions">
                        <div class="action-item <?php echo $liked_class; ?>" onclick="toggleLike(<?php echo $pid; ?>, this)">
                            <span class="icon">❤</span> <span class="count"><?php echo $row['like_count']; ?></span>
                        </div>
                        <div class="action-item" onclick="toggleComments(<?php echo $pid; ?>)">
                            <span class="icon">💬</span> 评论
                        </div>
                        <div class="action-item" onclick="sharePost(<?php echo $pid; ?>)">
                            <span class="icon">🔗</span> 分享
                        </div>
                    </div>

                    <div class="comments-section" id="comments-<?php echo $pid; ?>">
                        <?php 
                        // ... 这里保留你原来的评论区 PHP 代码 ...
                        // 为了节省篇幅，请把你原来的评论区 while 循环逻辑填回这里
                        // 提示：include 评论查询逻辑
                        $c_sql = "SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE post_id = $pid ORDER BY c.created_at ASC";
                        $c_res = $conn->query($c_sql);
                        if($c_res->num_rows > 0) {
                            while($c = $c_res->fetch_assoc()) {
                                echo '<div class="comment-row">';
                                echo '  <span class="comment-user">'.$c['username'].':</span>';
                                echo    htmlspecialchars($c['content']);
                                echo '</div>';
                            }
                        } else { echo '<div style="color:#666;font-size:0.8rem;">暂无回响...</div>'; }
                        
                        if(isset($_SESSION['user_id'])) {
                             echo '<form class="comment-form" method="POST" action="community.php">';
                             echo '  <input type="hidden" name="post_id" value="'.$pid.'">';
                             echo '  <input type="text" name="comment_content" class="comment-input" placeholder="回应..." required>';
                             echo '  <button type="submit" name="submit_comment" class="comment-submit">发送</button>';
                             echo '</form>';
                        }
                        ?>
                    </div>
                </div>
            <?php 
                }
            } else {
                echo "<p style='text-align:center; margin-top:50px; color:#666;'>虚空之中一片寂静...</p>";
            } 
            ?>
        </div>
    </div>

    <div class="side-column">
        
        <div class="side-card user-card">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="user-welcome">
                    <h3>欢迎回到圣巢</h3>
                    <p class="username">🎭 <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
                <div class="user-links">
                    <a href="profile.php" class="side-btn">📂 个人档案</a>
                    <a href="community.php?action=logout" class="side-btn logout">断开连接</a>
                </div>
            <?php else: ?>
                <p>旅人，请先表明身份。</p>
                <a href="login.php" class="side-btn full">登录 / 注册</a>
            <?php endif; ?>
        </div>

        <div class="side-card secret-corner">
            <h4>🤫 树洞 / 私密笔记</h4>
            <p>有些话，只想说给自己听...</p>
            <a href="private_notes.php" class="dream-btn small">进入树洞</a>
            </div>

        <div class="side-card notice-corner">
            <h4>📢 虚空广播</h4>
                
                <?php
                // 查询最新一条活跃公告
                $notice_sql = "SELECT * FROM announcements WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
                $notice_res = $conn->query($notice_sql);
                
                if ($notice_res && $notice_res->num_rows > 0):
                    $notice = $notice_res->fetch_assoc();
                ?>
                    <div style="font-size: 0.95rem; color: #ddd; line-height: 1.6; margin-bottom: 10px;">
                        <?php echo $notice['content']; ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #666; text-align: right;">
                        发布于: <?php echo date('m-d H:i', strtotime($notice['created_at'])); ?>
                    </div>
                <?php else: ?>
                    <p style="font-size:0.85rem; color:#888;">当前频段一片寂静...</p>
                <?php endif; ?>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>

<div id="lightbox" onclick="closeLightbox()" style="
    display: none; 
    position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
    background: rgba(0,0,0,0.9); z-index: 10000; 
    justify-content: center; align-items: center; 
    cursor: zoom-out;">
    <img id="lightbox-img" src="" style="max-width: 90%; max-height: 90%; border-radius: 5px; box-shadow: 0 0 20px rgba(0,0,0,0.5);">
</div>

<script>
// 1. 给所有帖子里的图片加点击事件
document.addEventListener("DOMContentLoaded", function() {
    let postImages = document.querySelectorAll('.post-image img');
    postImages.forEach(img => {
        img.style.cursor = 'zoom-in'; // 鼠标变成放大镜
        img.onclick = function() {
            openLightbox(this.src);
        };
    });
});

function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').style.display = 'flex';
}

function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
}
</script>