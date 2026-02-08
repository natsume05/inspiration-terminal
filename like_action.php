<?php
require 'includes/db.php';
header('Content-Type: application/json'); // 告诉浏览器返回的是JSON数据

// 必须登录才能点赞
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => '请先登录']);
    exit();
}

// 获取发送来的数据
$data = json_decode(file_get_contents('php://input'), true);
$post_id = intval($data['post_id']);
$user_id = $_SESSION['user_id'];

// 检查是“点赞”还是“取消点赞”
$check = $conn->query("SELECT id FROM likes WHERE post_id=$post_id AND user_id=$user_id");

if ($check->num_rows > 0) {
    // 如果赞过，就取消 (删除)
    $conn->query("DELETE FROM likes WHERE post_id=$post_id AND user_id=$user_id");
    $action = 'unlike';
} else {
    // 没赞过，就添加
    $conn->query("INSERT INTO likes (post_id, user_id) VALUES ($post_id, $user_id)");
    $action = 'like';
}

// 获取最新点赞数
$count_res = $conn->query("SELECT COUNT(*) as c FROM likes WHERE post_id=$post_id");
$count = $count_res->fetch_assoc()['c'];

echo json_encode(['success' => true, 'action' => $action, 'count' => $count]);
?>