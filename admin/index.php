<?php
// admin/index.php - 完整修复版
session_start();
require '../includes/db.php';

$allowed_user = 'MingMo'; // 记得确认这里是你的用户名
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== $allowed_user) {
    die("⛔ 权限不足 <a href='../login.php'>登录</a>");
}

$message = "欢迎来到神禁领域";

// --- 逻辑 A: 添加工具 ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_tool'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $url = $conn->real_escape_string($_POST['url']);
    $category = $conn->real_escape_string($_POST['category']);
    $desc = $conn->real_escape_string($_POST['description']);
    // 图标现在不用填了，前台会自动抓取，这里存空或者默认值
    $sql = "INSERT INTO tools (title, url, icon, description, category) VALUES ('$title', '$url', '', '$desc', '$category')";
    if ($conn->query($sql)) $message = "✅ 工具添加成功！";
    else $message = "❌ 失败：" . $conn->error;
}

// --- 逻辑 B: 发布博客 ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['publish_blog'])) {
    $title = $conn->real_escape_string($_POST['blog_title']);
    $content = $conn->real_escape_string($_POST['blog_content']);
    
    // 🟢 修复点：这里增加了对 blog_tags 的检查，防止报错
    $tags = isset($_POST['blog_tags']) ? $conn->real_escape_string(str_replace('，', ',', $_POST['blog_tags'])) : '';
    
    $cover_path = NULL;

    // 图片上传逻辑
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
        $target_dir = "../assets/images/";
        // 确保文件名安全
        $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES["cover_image"]["name"]));
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES["cover_image"]["tmp_name"], $target_file)) {
            $cover_path = "assets/images/" . $filename;
        }
    }

    $sql = "INSERT INTO blog_posts (title, content, cover_image, tags) VALUES ('$title', '$content', '$cover_path', '$tags')";
    if ($conn->query($sql)) $message = "✅ 博客发布成功！";
    else $message = "❌ 发布失败：" . $conn->error;

    // --- 逻辑 C: 发布/管理公告 ---
    // 1. 发布新公告
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['publish_notice'])) {
        $content = $conn->real_escape_string($_POST['notice_content']);
        // 先把旧的都停掉 (保证同一时间只有一个活跃广播)
        $conn->query("UPDATE announcements SET is_active = 0");
        // 插入新的
        $sql = "INSERT INTO announcements (content, is_active) VALUES ('$content', 1)";
        if ($conn->query($sql)) $message = "✅ 全域广播已发射！";
        else $message = "❌ 发射失败：" . $conn->error;
    }

    // 2. 停止所有广播
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['stop_notice'])) {
        $conn->query("UPDATE announcements SET is_active = 0");
        $message = "🛑 广播信号已切断，静默模式开启。";
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
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; }
        .admin-panel { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        h2 { text-align: center; color: #333; }
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid #ddd; }
        .tab-btn { flex: 1; padding: 15px; text-align: center; cursor: pointer; background: none; border: none; font-size: 1rem; color: #666; }
        .tab-btn.active { border-bottom: 3px solid #333; font-weight: bold; color: #333; }
        .form-section { display: none; }
        .form-section.active { display: block; }
        input, textarea, select { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 15px; background: #333; color: white; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; }
        .msg { padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px; text-align: center; }
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
    </div>

    <div id="form-tool" class="form-section active">
        <form method="POST">
            <input type="text" name="title" placeholder="工具名称" required>
            <input type="url" name="url" placeholder="链接 (https://)" required>
            <select name="category">
                <option value="tools">🛠️ 工具</option>
                <option value="game">🎮 游戏</option>
                <option value="life">🍵 生活</option>
                <option value="impression">🌌 印象</option>
            </select>
            <textarea name="description" placeholder="一句话描述"></textarea>
            <button type="submit" name="add_tool">归档工具</button>
        </form>
    </div>

    <div id="form-blog" class="form-section">
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="blog_title" placeholder="日志标题" required>
            
            <input type="text" name="blog_tags" placeholder="标签 (用逗号分隔，例如：生活, 星际拓荒)">
            
            <textarea name="blog_content" placeholder="正文内容..." style="height: 200px;" required></textarea>
            
            <label style="display:block; margin-bottom:5px; color:#666;">📸 封面图 (可选):</label>
            <input type="file" name="cover_image" accept="image/*">
            
            <button type="submit" name="publish_blog" style="background: #007bff;">发布日志</button>
        </form>
    </div>

    <div id="form-notice" class="form-section">
        <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-size: 0.9rem;">
            💡 提示：新公告发布后，所有访问主页的用户都会看到弹窗。用户点击“收到”后，该版本公告不再弹出。
        </div>

        <form method="POST">
            <label style="display:block; margin-bottom:5px; color:#666;">广播内容 (支持 HTML):</label>
            <textarea name="notice_content" placeholder="例如：本站已更新 2.0 版本，新增了树洞功能..." style="height: 150px;" required></textarea>
            
            <button type="submit" name="publish_notice" style="background: #e67e22;">📡 发射信号</button>
        </form>

        <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

        <form method="POST" onsubmit="return confirm('确定要关闭当前正在播放的公告吗？');">
            <button type="submit" name="stop_notice" style="background: #666;">🔕 停止所有广播</button>
        </form>
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