<?php
// admin/index.php - 舰长控制台 (完整版：含反馈系统)
session_start();
require '../includes/db.php';
require_once '../includes/image_helper.php';

// 🛡️ 权限检查
$allowed_user = 'MingMo'; 
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== $allowed_user) {
    die("⛔ 权限不足 <a href='../login.php'>登录</a>");
}

$message = "";

// --- 逻辑 A: 添加工具 ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_tool'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $url = $conn->real_escape_string($_POST['url']);
    $category = $conn->real_escape_string($_POST['category']);
    $desc = $conn->real_escape_string($_POST['description']);
    $sql = "INSERT INTO tools (title, url, icon, description, category) VALUES ('$title', '$url', '', '$desc', '$category')";
    if ($conn->query($sql)) $message = "✅ 工具添加成功！";
    else $message = "❌ 失败：" . $conn->error;
}

// --- 逻辑 B: 发布博客 ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['publish_blog'])) {
    $title = $conn->real_escape_string($_POST['blog_title']);
    $content = $conn->real_escape_string($_POST['blog_content']);
    $tags = isset($_POST['blog_tags']) ? $conn->real_escape_string(str_replace('，', ',', $_POST['blog_tags'])) : '';
    $cover_path = NULL;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
        $base_name = time() . "_blog";
        $processed = upload_and_compress_webp($_FILES["cover_image"]["tmp_name"], "../assets/images/" . $base_name, 1200, 80);
        if ($processed) $cover_path = "assets/images/" . $processed;
    }
    $sql = "INSERT INTO blog_posts (title, content, cover_image, tags) VALUES ('$title', '$content', '$cover_path', '$tags')";
    if ($conn->query($sql)) $message = "✅ 博客发布成功！";
    else $message = "❌ 失败：" . $conn->error;
}

// --- 逻辑 C: 广播管理 ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['publish_notice'])) {
    $content = $conn->real_escape_string($_POST['notice_content']);
    $conn->query("UPDATE announcements SET is_active = 0");
    $conn->query("INSERT INTO announcements (content, is_active) VALUES ('$content', 1)");
    $message = "✅ 广播已发射！";
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['stop_notice'])) {
    $conn->query("UPDATE announcements SET is_active = 0");
    $message = "🛑 广播已切断。";
}

// --- 逻辑 D: 称号管理 ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['grant_title'])) {
    $target = $conn->real_escape_string($_POST['target_username']);
    $new_title = $conn->real_escape_string($_POST['title_text']);
    if (empty($new_title)) {
        $sql = "UPDATE users SET custom_title = NULL WHERE username = '$target'";
        $msg = "🗑️ 已撤销 [$target] 的称号。";
    } else {
        $sql = "UPDATE users SET custom_title = '$new_title' WHERE username = '$target'";
        $msg = "🎖️ 已授予 [$target] 称号: $new_title";
    }
    if ($conn->query($sql)) echo "<script>alert('$msg');</script>";
}

// --- 🟢 逻辑 E: 回复反馈 (新增) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reply_feedback'])) {
    $fid = intval($_POST['feedback_id']);
    $reply = $conn->real_escape_string($_POST['reply_content']);
    
    $sql = "UPDATE feedback SET admin_reply = '$reply', status = 'replied' WHERE id = $fid";
    if ($conn->query($sql)) {
        $message = "✅ 已回复该信号！";
        // 可选：给用户发通知系统消息 (如果有通知系统的话)
    } else {
        $message = "❌ 回复失败：" . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>舰长控制台</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; color: #333; }
        .admin-panel { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }
        h2 { text-align: center; color: #333; }
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid #ddd; overflow-x: auto; }
        .tab-btn { flex: 1; padding: 15px; text-align: center; cursor: pointer; background: none; border: none; font-size: 1rem; color: #666; white-space: nowrap; }
        .tab-btn.active { border-bottom: 3px solid #333; font-weight: bold; color: #333; }
        .form-section { display: none; }
        .form-section.active { display: block; }
        input, textarea, select { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 15px; background: #333; color: white; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; }
        .msg { padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px; text-align: center; }
        
        /* 反馈列表样式 */
        .feedback-item { border: 1px solid #eee; padding: 15px; margin-bottom: 15px; border-radius: 8px; background: #fafafa; }
        .fb-header { display: flex; justify-content: space-between; font-size: 0.85rem; color: #666; margin-bottom: 10px; }
        .fb-content { font-size: 1rem; margin-bottom: 15px; white-space: pre-wrap; color: #333; }
        .fb-tag { padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; text-transform: uppercase; }
        .tag-bug { background: #fee2e2; color: #991b1b; }
        .tag-feature { background: #fef3c7; color: #92400e; }
        .tag-help { background: #dbeafe; color: #1e40af; }
        .reply-box { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ddd; }
    </style>
</head>
<body>

<div class="admin-panel">
    <h2>🚀 舰长控制台</h2>
    <?php if ($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('tool')">🔧 加工具</button>
        <button class="tab-btn" onclick="switchTab('blog')">📝 写日志</button>
        <button class="tab-btn" onclick="switchTab('notice')">📢 发广播</button>
        <button class="tab-btn" onclick="switchTab('users')">👥 人员</button>
        <button class="tab-btn" onclick="switchTab('feedback')">📶 反馈</button>
    </div>

    <div id="form-tool" class="form-section active">
        <form method="POST">
            <input type="text" name="title" placeholder="工具名称" required>
            <input type="url" name="url" placeholder="链接 (https://)" required>
            <select name="category">
                <option value="tools">🛠️ 工具</option><option value="game">🎮 游戏</option>
                <option value="life">🍵 生活</option><option value="impression">🌌 印象</option>
            </select>
            <textarea name="description" placeholder="一句话描述"></textarea>
            <button type="submit" name="add_tool">归档工具</button>
        </form>
    </div>

    <div id="form-blog" class="form-section">
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="blog_title" placeholder="日志标题" required>
            <input type="text" name="blog_tags" placeholder="标签 (逗号分隔)">
            <textarea name="blog_content" placeholder="正文..." style="height: 200px;" required></textarea>
            <label>📸 封面图:</label><input type="file" name="cover_image" accept="image/*">
            <button type="submit" name="publish_blog" style="background: #007bff;">发布日志</button>
        </form>
    </div>

    <div id="form-notice" class="form-section">
        <form method="POST">
            <textarea name="notice_content" placeholder="广播内容..." style="height: 150px;" required></textarea>          
            <button type="submit" name="publish_notice" style="background: #e67e22;">📡 发射信号</button>
        </form>
        <form method="POST" style="margin-top:20px;" onsubmit="return confirm('关闭当前广播？');">
            <button type="submit" name="stop_notice" style="background: #666;">🔕 停止广播</button>
        </form>
    </div>

    <div id="form-users" class="form-section">
        <form method="POST" style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
            <input type="text" name="target_username" placeholder="目标用户名" required>
            <input type="text" name="title_text" placeholder="授予称号 (留空撤销)">
            <button type="submit" name="grant_title" style="background: linear-gradient(135deg, #f6d365, #fda085); color:#333;">🎖️ 执行</button>
        </form>
    </div>

    <div id="form-feedback" class="form-section">
        <h3>📶 信号接收塔</h3>
        <?php
        // 只显示未处理或最近的反馈
        $f_sql = "SELECT f.*, u.username FROM feedback f JOIN users u ON f.user_id = u.id ORDER BY f.status ASC, f.created_at DESC LIMIT 20";
        $f_res = $conn->query($f_sql);
        
        if ($f_res && $f_res->num_rows > 0):
            while($item = $f_res->fetch_assoc()):
                $tagClass = 'tag-help';
                if($item['type']=='bug') $tagClass = 'tag-bug';
                if($item['type']=='feature') $tagClass = 'tag-feature';
        ?>
            <div class="feedback-item">
                <div class="fb-header">
                    <span>
                        <span class="fb-tag <?php echo $tagClass; ?>"><?php echo strtoupper($item['type']); ?></span>
                        <strong><?php echo htmlspecialchars($item['username']); ?></strong>
                    </span>
                    <span><?php echo date('m-d H:i', strtotime($item['created_at'])); ?></span>
                </div>
                
                <div class="fb-content"><?php echo htmlspecialchars($item['content']); ?></div>
                
                <div class="reply-box">
                    <?php if($item['status'] == 'replied'): ?>
                        <div style="color:green; font-size:0.9rem;">✅ 已回复：<?php echo htmlspecialchars($item['admin_reply']); ?></div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="feedback_id" value="<?php echo $item['id']; ?>">
                            <input type="text" name="reply_content" placeholder="输入回复内容..." required style="margin-bottom:10px;">
                            <button type="submit" name="reply_feedback" style="padding:10px; background:#28a745;">📨 发送回复</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php 
            endwhile; 
        else:
            echo "<p style='text-align:center; color:#999;'>暂无新信号。</p>";
        endif; 
        ?>
    </div>

</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.form-section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('form-' + tab).classList.add('active');
    event.target.classList.add('active');
}
</script>
</body>
</html>