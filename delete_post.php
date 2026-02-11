<?php
// delete_post.php - 修复版
require 'includes/db.php';
require 'includes/csrf.php'; // 1. 引入安全卫士
session_start(); 

// --- 2. 安全检查 ---

// A. 检查 CSRF 暗号 (防止黑客攻击)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("🛑 删除失败：非法请求 (CSRF Error)");
    }
}

// B. 检查管理员权限 (假设 ID 1 是舰长)
// 注意：如果你后来改了管理员ID，请在这里修改数字
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    die("❌ 权限不足：只有舰长可以执行此操作。");
}

// --- 3. 执行删除 ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 🟢 修复点：使用 $_POST 接收，且变量名改为 post_id (对应前端 input name)
    $id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if ($id > 0) {
        // 1. 删除帖子
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // 2. 删除相关的点赞记录 (保持数据库干净)
        $stmt2 = $conn->prepare("DELETE FROM likes WHERE post_id = ?");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $stmt2->close();
    }
}

// 删完回社区首页
header("Location: community.php");
exit();
?>