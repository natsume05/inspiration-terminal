<?php
session_start();
require 'includes/db.php';

// 严格安检
if (!isset($_SESSION['user_id'])) {
    die("⛔ <a href='login.php'>登录</a>");
}

// 处理提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_note'])) {
    $uid = $_SESSION['user_id'];
    $content = $conn->real_escape_string($_POST['note_content']);
    $conn->query("INSERT INTO private_notes (user_id, content) VALUES ($uid, '$content')");
    // 刷新页面防止重复提交
    header("Location: secret_space.php"); exit();
}

// 删除笔记
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $uid = $_SESSION['user_id'];
    $conn->query("DELETE FROM private_notes WHERE id=$id AND user_id=$uid");
    header("Location: secret_space.php"); exit();
}

$page_title = "思维殿堂";
$style = "community"; 
include 'includes/header.php'; 
?>

<style>
    /* 里世界特供样式 */
    body { background-color: #050505; } /* 更黑的背景 */
    .void-container { max-width: 700px; margin: 0 auto; padding-top: 40px; }
    
    .void-input {
        width: 100%;
        background: transparent;
        border: none;
        border-bottom: 1px solid #333;
        color: #ddd;
        font-family: 'Courier New', monospace;
        font-size: 1.1rem;
        padding: 20px;
        min-height: 150px;
        outline: none;
        transition: border-color 0.5s;
    }
    .void-input:focus { border-bottom-color: #66fcf1; }
    
    .timeline { border-left: 2px solid #333; margin-top: 50px; padding-left: 30px; }
    .note-item { position: relative; margin-bottom: 40px; }
    .note-item::before {
        content: ''; position: absolute; left: -36px; top: 5px;
        width: 10px; height: 10px; background: #66fcf1; border-radius: 50%;
        box-shadow: 0 0 10px #66fcf1;
    }
    .note-date { font-size: 0.8rem; color: #666; margin-bottom: 5px; }
    .note-content { color: #aaa; line-height: 1.6; white-space: pre-wrap; }
    .del-note { float: right; color: #333; text-decoration: none; font-size: 0.8rem; }
    .del-note:hover { color: #ff4242; }
</style>

<div class="void-container">
    
    <div style="text-align: center; margin-bottom: 40px; opacity: 0.7;">
        <h2 style="color: #66fcf1; font-weight: 300;">VOID / 思维殿堂</h2>
        <p style="font-size: 0.8rem; color: #666;">这里的声音，只有你听得见。</p>
    </div>

    <form method="POST">
        <textarea name="note_content" class="void-input" placeholder="在此刻下那些无法对人言说的..." required></textarea>
        <div style="text-align: right; margin-top: 20px;">
            <button type="submit" name="save_note" class="dream-btn small" style="width: auto; display: inline-block;">封存记忆</button>
        </div>
    </form>

    <div class="timeline">
        <?php
        $uid = $_SESSION['user_id'];
        $sql = "SELECT * FROM private_notes WHERE user_id = $uid ORDER BY created_at DESC";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0):
            while($row = $result->fetch_assoc()):
        ?>
            <div class="note-item">
                <a href="?del=<?php echo $row['id']; ?>" class="del-note" onclick="return confirm('要遗忘这段记忆吗？')">×</a>
                <div class="note-date"><?php echo $row['created_at']; ?></div>
                <div class="note-content"><?php echo htmlspecialchars($row['content']); ?></div>
            </div>
        <?php 
            endwhile;
        else:
            echo "<p style='color:#333; font-style:italic;'>虚空之中暂无回响...</p>";
        endif;
        ?>
    </div>

    <div style="text-align: center; margin-top: 50px;">
        <a href="private_notes.php" style="color: #444; text-decoration: none; font-size: 0.8rem;">▲ 上浮至表层</a>
    </div>

</div>

<?php include 'includes/footer.php'; ?>