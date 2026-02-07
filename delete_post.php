<?php
require 'db.php';

// 只有管理员能执行
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("❌ 权限不足：你不是圣巢的管理者。");
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // 执行删除
    $conn->query("DELETE FROM posts WHERE id=$id");
}

// 删完回首页
header("Location: community.php");
?>