<?php
require 'includes/db.php';
// 必须登录才能看
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// --- 处理：更新个人信息 ---
if (isset($_POST['update_profile'])) {
    $age = intval($_POST['age']);
    $bio = $conn->real_escape_string($_POST['bio']);
    $email = $conn->real_escape_string($_POST['email']);
    
    // SQL 更新语句
    $sql = "UPDATE users SET age=$age, bio='$bio', email='$email' WHERE id=$user_id";
    if ($conn->query($sql)) {
        $msg = "✅ 档案已更新";
    } else {
        $msg = "更新失败: " . $conn->error;
    }
}

// --- 处理：添加私人笔记 ---
if (isset($_POST['add_note'])) {
    $note = $conn->real_escape_string($_POST['note_content']);
    if (!empty($note)) {
        $conn->query("INSERT INTO private_notes (user_id, content) VALUES ($user_id, '$note')");
        $msg = "🔒 笔记已加密封存";
    }
}

// --- 读取：获取用户信息 ---
$user_sql = "SELECT * FROM users WHERE id=$user_id";
$user_info = $conn->query($user_sql)->fetch_assoc();

// --- 读取：获取笔记列表 ---
$notes_sql = "SELECT * FROM private_notes WHERE user_id=$user_id ORDER BY created_at DESC";
$notes = $conn->query($notes_sql);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>个人档案 | 虚空终端</title>
    <style>
        /* 复用之前的 CSS 变量 */
        :root { --void-bg: #0b0c10; --pale-text: #c5c6c7; --soul-blue: #66fcf1; --stone-border: #45a29e; }
        body { background: var(--void-bg); color: var(--pale-text); font-family: 'Georgia', serif; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; display: flex; gap: 20px; }
        
        /* 左侧：档案卡片 */
        .profile-card { flex: 1; background: rgba(31,40,51,0.5); padding: 20px; border: 1px solid var(--stone-border); border-radius: 8px; }
        /* 右侧：笔记区域 */
        .notes-area { flex: 2; }
        
        input, textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--stone-border); color: var(--soul-blue); padding: 10px; margin-bottom: 10px; box-sizing: border-box; }
        button { background: var(--stone-border); color: #000; border: none; padding: 8px 15px; cursor: pointer; }
        button:hover { background: var(--soul-blue); }
        
        .note-item { background: rgba(255,255,255,0.05); padding: 15px; margin-bottom: 10px; border-left: 3px solid var(--soul-blue); }
        .timestamp { font-size: 0.8rem; color: #666; display: block; margin-top: 5px; }
        
        h2 { border-bottom: 1px dashed var(--stone-border); padding-bottom: 10px; }
        .msg { color: #ffae42; margin-bottom: 10px; }
    </style>
</head>
<body>

    <a href="community.php" style="position:fixed; top:20px; right:20px; color:var(--soul-blue); text-decoration:none;">↩ 返回社区</a>

    <div class="msg"><?php echo $msg; ?></div>

    <div class="container">
        <div class="profile-card">
            <h2>👤 容器档案</h2>
            <form method="POST">
                <label>代号</label>
                <input type="text" value="<?php echo htmlspecialchars($user_info['username']); ?>" disabled style="opacity:0.5">
                
                <label>邮箱</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user_info['email']); ?>" placeholder="Email">
                
                <label>存在时长 (Age)</label>
                <input type="number" name="age" value="<?php echo $user_info['age']; ?>">
                
                <label>个性签名</label>
                <textarea name="bio" rows="4"><?php echo htmlspecialchars($user_info['bio']); ?></textarea>
                
                <button type="submit" name="update_profile">更新档案</button>
            </form>
        </div>

        <div class="notes-area">
            <h2>📓 虚空笔记 (仅自己可见)</h2>
            <form method="POST" style="margin-bottom: 20px;">
                <textarea name="note_content" placeholder="记录下只有你知道的秘密..." required></textarea>
                <button type="submit" name="add_note">加密保存</button>
            </form>

            <div class="notes-list">
                <?php while($note = $notes->fetch_assoc()): ?>
                    <div class="note-item">
                        <?php echo nl2br(htmlspecialchars($note['content'])); ?>
                        <span class="timestamp">记录于: <?php echo time_ago($note['created_at']); ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

</body>
</html>