<?php
session_start();
require '../includes/db.php';

// 权限验证
$allowed_user = 'MingMo'; // 你的用户名
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== $allowed_user) {
    die("⛔ 权限不足：这是舰长室，船员请回。 <a href='../index.php'>返回大厅</a>");
}

$message = "欢迎来到神禁领域";

// --- 逻辑 A: 添加工具 ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_tool'])) {
    // ... (保持你之前的添加工具逻辑，为了节省篇幅我略写，请保留你原来的代码) ...
    
    // 获取用户填写的“货物”
    $title = $conn->real_escape_string($_POST['title']);
    $url = $conn->real_escape_string($_POST['url']);
    $icon = $conn->real_escape_string($_POST['icon']);
    $desc = $conn->real_escape_string($_POST['description']);
    $category = $conn->real_escape_string($_POST['category']);

    // 准备 SQL 搬运工
    $sql = "INSERT INTO tools (title, url, icon, description, category) 
            VALUES ('$title', '$url', '$icon', '$desc', '$category')";

    // 执行搬运
    if ($conn->query($sql) === TRUE) {
        $message = "✅ 成功收录：$title";
    } else {
        $message = "❌ 收录失败：" . $conn->error;
    }

}

// --- 逻辑 B: 发布博客 (含图片上传) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['publish_blog'])) {
    $title = $conn->real_escape_string($_POST['blog_title']);
    $content = $conn->real_escape_string($_POST['blog_content']);
    $cover_path = NULL;

    // 处理图片上传
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
        $target_dir = "../assets/images/";
        // 给文件名加个时间戳防止重名
        $filename = time() . "_" . basename($_FILES["cover_image"]["name"]);
        $target_file = $target_dir . $filename;
        
        // 移动文件
        if (move_uploaded_file($_FILES["cover_image"]["tmp_name"], $target_file)) {
            $cover_path = "assets/images/" . $filename; // 存入数据库的相对路径
        } else {
            $message = "❌ 图片上传失败，请检查文件夹权限。";
        }
    }

    $sql = "INSERT INTO blog_posts (title, content, cover_image) VALUES ('$title', '$content', '$cover_path')";
    if ($conn->query($sql)) $message = "✅ 博客发布成功！";
    else $message = "❌ 发布失败：" . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>舰长控制台</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; }
        .admin-panel { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        h2 { text-align: center; color: #333; }
        
        /* Tab 切换按钮 */
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid #ddd; }
        .tab-btn { flex: 1; padding: 15px; text-align: center; cursor: pointer; background: none; border: none; font-size: 1rem; color: #666; }
        .tab-btn.active { border-bottom: 3px solid #333; font-weight: bold; color: #333; }
        
        .form-section { display: none; }
        .form-section.active { display: block; }

        input, textarea, select { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 15px; background: #333; color: white; border: none; border-radius: 5px; font-size: 1rem; }
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
    </div>

    <div id="form-tool" class="form-section active">
        <form method="POST">
            <input type="text" name="title" placeholder="工具名称" required>
            <input type="url" name="url" placeholder="链接 (https://)" required>
            <input type="text" name="icon" placeholder="图标 Emoji" required>
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
            <textarea name="blog_content" placeholder="正文内容 (支持 HTML，比如 <br> 换行)" style="height: 200px;" required></textarea>
            
            <label style="display:block; margin-bottom:5px; color:#666;">📸 封面图 (可选):</label>
            <input type="file" name="cover_image" accept="image/*">
            
            <button type="submit" name="publish_blog" style="background: #007bff;">发布日志</button>
        </form>
    </div>

    <div style="text-align:center; margin-top:20px;">
        <a href="../blog.php" target="_blank" style="text-decoration:none; color:#666;">查看效果 →</a>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.form-section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById('form-' + tab).classList.add('active');
    // 简单的根据点击位置切换 active 样式，这里偷懒用 event.target
    event.target.classList.add('active');
}
</script>

</body>
</html>