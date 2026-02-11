<?php
// community.php - 虚空梦语 3.0 (分区+表情版)
require 'includes/db.php';
require_once 'includes/image_helper.php'; // 引入图片处理工厂

// 页面配置
$page_title = "虚空梦语";
$style = "community";
include 'includes/header.php'; 

// --- 1. 处理发帖逻辑 ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_post'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php"); exit();
    }
    
    $content = $conn->real_escape_string($_POST['content']);
    $author = $_SESSION['username'];
    $tag = isset($_POST['tag']) ? $conn->real_escape_string($_POST['tag']) : 'daily'; // 获取标签
    
    // 图片上传处理 (复用之前的逻辑)
    $image_path = NULL;
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
        $base_name = "post_" . time() . "_" . rand(100,999);
        $target_dir = "assets/uploads/community/";
        // 调用工厂压缩图片
        $processed_name = upload_and_compress_webp($_FILES['post_image']['tmp_name'], $target_dir . $base_name, 1000, 75);
        if ($processed_name) {
            $image_path = $processed_name;
        }
    }

    $sql = "INSERT INTO posts (author, content, image, tag) VALUES ('$author', '$content', '$image_path', '$tag')";
    
    if ($conn->query($sql) === TRUE) {
        header("Location: community.php"); exit();
    } else {
        $error = "发布失败: " . $conn->error;
    }
}

// --- 2. 处理筛选逻辑 (查看特定分区的帖子) ---
$current_filter = isset($_GET['tag']) ? $_GET['tag'] : 'all'; // 默认看全部

// 构建查询 SQL
$sql = "SELECT p.*, u.username, u.avatar, u.custom_title, 
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0) . ") as is_liked
        FROM posts p 
        LEFT JOIN users u ON p.author = u.username ";

// 如果选了特定分区，加 WHERE 条件
if ($current_filter != 'all') {
    $safe_tag = $conn->real_escape_string($current_filter);
    $sql .= " WHERE p.tag = '$safe_tag' ";
}

$sql .= " ORDER BY p.created_at DESC";
$result = $conn->query($sql);

// --- 3. 定义分区配置 (图标+名称) ---
$channels = [
    'all'   => ['icon' => '🌎', 'name' => '全频段'],
    'daily' => ['icon' => '☕', 'name' => '日常吐槽'],
    'game'  => ['icon' => '🎮', 'name' => '游戏圣堂'],
    'tech'  => ['icon' => '💻', 'name' => '代码深空'],
    'void'  => ['icon' => '🕳️', 'name' => '虚空回响']
];

// --- 4. 表情包解析函数 ---
function parseEmojis($text) {
    // 简单的映射表 (你可以根据需要无限添加)
    $emojis = [
        '[s:smile]' => '🙂', '[s:joy]' => '😂', '[s:lol]' => '🤣', '[s:love]' => '😍',
        '[s:cool]' => '😎', '[s:cry]' => '😭', '[s:scared]' => '😱', '[s:angry]' => '😡',
        '[s:thinking]' => '🤔', '[s:shhh]' => '🤫', '[s:vomit]' => '🤮', '[s:clown]' => '🤡',
        '[s:thumbsup]' => '👍', '[s:ok]' => '👌', '[s:heart]' => '❤️', '[s:broken]' => '💔',
        '[s:fire]' => '🔥', '[s:star]' => '✨', '[s:poop]' => '💩', '[s:alien]' => '👽',
        '[s:ghost]' => '👻', '[s:robot]' => '🤖', '[s:cat]' => '🐱', '[s:dog]' => '🐶'
    ];
    return str_replace(array_keys($emojis), array_values($emojis), $text);
}
?>

<div class="container community-layout">
    
    <aside class="sidebar-left">
        <div class="side-card nav-card">
            <h4>📡 频道导航</h4>
            <nav class="channel-nav">
                <?php foreach($channels as $key => $info): 
                    $active = ($current_filter == $key) ? 'active' : '';
                ?>
                <a href="community.php?tag=<?php echo $key; ?>" class="channel-item <?php echo $active; ?>">
                    <span class="c-icon"><?php echo $info['icon']; ?></span>
                    <span class="c-name"><?php echo $info['name']; ?></span>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
        
        <?php if(isset($_SESSION['username'])): ?>
        <div class="side-card user-card">
            <div class="user-info-mini">
                <img src="assets/uploads/avatars/<?php echo $_SESSION['avatar'] ? $_SESSION['avatar'] : 'default.png'; ?>" class="avatar-circle">
                <div>
                    <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                    <div style="font-size:0.8rem; color:#666;">信号稳定</div>
                </div>
            </div>
            <div class="user-actions">
                <a href="profile.php" class="btn-outline">📂 档案</a>
                <a href="logout.php" class="btn-outline red">断开</a>
            </div>
        </div>
        <?php endif; ?>
    </aside>

    <main class="feed-stream">
        
        <div class="post-box">
            <?php if(isset($_SESSION['username'])): ?>
                <form action="community.php" method="POST" enctype="multipart/form-data">
                    
                    <textarea id="post-content" name="content" placeholder="在此刻刻下你的思想..." required></textarea>
                    
                    <div class="post-toolbar">
                        <div class="tools-left">
                            <select name="tag" class="channel-select">
                                <option value="daily">☕ 日常</option>
                                <option value="game">🎮 游戏</option>
                                <option value="tech">💻 技术</option>
                                <option value="void">🕳️ 树洞</option>
                            </select>

                            <button type="button" class="tool-btn" onclick="toggleEmojiPanel()">😊 表情</button>
                            
                            <label class="tool-btn">
                                📷 图片
                                <input type="file" name="post_image" accept="image/*" style="display:none;" onchange="showFileName(this)">
                            </label>
                            <span id="file-name" style="font-size:0.8rem; color:#666; margin-left:5px;"></span>
                        </div>
                        
                        <button type="submit" name="submit_post" class="dream-btn small">✨ 发送</button>
                    </div>

                    <div id="emoji-panel" class="emoji-panel" style="display:none;">
                        <span onclick="insertEmoji('[s:smile]')">🙂</span>
                        <span onclick="insertEmoji('[s:joy]')">😂</span>
                        <span onclick="insertEmoji('[s:lol]')">🤣</span>
                        <span onclick="insertEmoji('[s:love]')">😍</span>
                        <span onclick="insertEmoji('[s:cool]')">😎</span>
                        <span onclick="insertEmoji('[s:thinking]')">🤔</span>
                        <span onclick="insertEmoji('[s:cry]')">😭</span>
                        <span onclick="insertEmoji('[s:scared]')">😱</span>
                        <span onclick="insertEmoji('[s:angry]')">😡</span>
                        <span onclick="insertEmoji('[s:clown]')">🤡</span>
                        <span onclick="insertEmoji('[s:thumbsup]')">👍</span>
                        <span onclick="insertEmoji('[s:ok]')">👌</span>
                        <span onclick="insertEmoji('[s:heart]')">❤️</span>
                        <span onclick="insertEmoji('[s:fire]')">🔥</span>
                        <span onclick="insertEmoji('[s:star]')">✨</span>
                        <span onclick="insertEmoji('[s:ghost]')">👻</span>
                    </div>

                </form>
            <?php else: ?>
                <div style="text-align:center; padding:20px; color:#666;">
                    <p>检测到未授权的访客信号...</p>
                    <a href="login.php" class="dream-btn small">🔑 接入终端</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="posts-list">
            <?php 
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    // 解析表情
                    $final_content = nl2br(htmlspecialchars($row["content"]));
                    $final_content = parseEmojis($final_content);
                    
                    // 获取分区信息
                    $post_tag = isset($row['tag']) && isset($channels[$row['tag']]) ? $channels[$row['tag']] : $channels['daily'];
            ?>
                <div class="post-card fade-in">
                    <div class="post-header">
                        <div class="author-box">
                            <img src="assets/uploads/avatars/<?php echo $row['avatar'] ? $row['avatar'] : 'default.png'; ?>" class="avatar-small">
                            <div class="author-info">
                                <span class="username"><?php echo htmlspecialchars($row['username']); ?></span>
                                
                                <?php if (!empty($row['custom_title'])): ?>
                                    <span class="custom-title-badge"><?php echo htmlspecialchars($row['custom_title']); ?></span>
                                <?php endif; ?>

                                <span class="tag-badge" style="opacity:0.7;"><?php echo $post_tag['icon'] . ' ' . $post_tag['name']; ?></span>
                            </div>
                        </div>
                        <span class="post-time"><?php echo date('m-d H:i', strtotime($row['created_at'])); ?></span>
                    </div>

                    <div class="post-content">
                        <?php echo $final_content; ?>
                    </div>

                    <?php if (!empty($row['image'])): ?>
                        <div class="post-image">
                            <img src="assets/uploads/community/<?php echo $row['image']; ?>" onclick="openLightbox(this.src)">
                        </div>
                    <?php endif; ?>

                    <div class="post-footer">
                        <span class="action-btn" onclick="toggleLike(<?php echo $row['id']; ?>, this)">
                            <?php echo ($row['is_liked'] > 0) ? '❤️' : '🤍'; ?> 
                            <span class="count"><?php echo $row['like_count']; ?></span>
                        </span>
                        <span class="action-btn">💬 评论</span>
                        <span class="action-btn">🔗 分享</span>
                    </div>
                </div>
            <?php 
                }
            } else {
                echo "<div style='text-align:center; padding:50px; color:#666;'>这里是一片虚无...</div>";
            }
            ?>
        </div>
    </main>

    <aside class="sidebar-right">
        <div class="side-card hole-card">
            <h4>🤫 树洞 / 私密笔记</h4>
            <p style="font-size:0.85rem; color:#aaa; margin-bottom:15px;">有些话，只想说给自己听...</p>
            <a href="private_notes.php" class="dream-btn full-width" style="background: linear-gradient(135deg, #43cea2, #185a9d);">进入树洞</a>
        </div>

        <div class="side-card notice-corner">
            <h4>📢 虚空广播</h4>
            <?php
            $notice_sql = "SELECT * FROM announcements WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
            $notice_res = $conn->query($notice_sql);
            if ($notice_res && $notice_res->num_rows > 0):
                $notice = $notice_res->fetch_assoc();
            ?>
                <div style="font-size: 0.9rem; color: #ddd; line-height: 1.5; margin-bottom: 10px;">
                    <?php echo $notice['content']; ?>
                </div>
                <div style="font-size: 0.75rem; color: #666; text-align: right;">
                    <?php echo date('m-d H:i', strtotime($notice['created_at'])); ?>
                </div>
            <?php else: ?>
                <p style="font-size:0.85rem; color:#888;">当前频段一片寂静...</p>
            <?php endif; ?>
        </div>
    </aside>

</div>

<div id="lightbox" onclick="closeLightbox()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:10000; justify-content:center; align-items:center; cursor:zoom-out;">
    <img id="lightbox-img" src="" style="max-width:90%; max-height:90%; border-radius:5px;">
</div>

<script>
function showFileName(input) {
    document.getElementById('file-name').innerText = input.files[0] ? input.files[0].name : '';
}

function toggleEmojiPanel() {
    var panel = document.getElementById('emoji-panel');
    panel.style.display = (panel.style.display === 'none') ? 'grid' : 'none';
}

function insertEmoji(code) {
    var textarea = document.getElementById('post-content');
    textarea.value += code; // 在最后插入
    toggleEmojiPanel(); // 关闭面板
    textarea.focus();
}

// 灯箱功能
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').style.display = 'flex';
}
function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>