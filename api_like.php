<?php
// api_like.php - 点赞处理器
require 'includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['post_id'])) {
    echo json_encode(['success' => false, 'message' => '未授权或参数缺失']);
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = intval($_GET['post_id']);

// 检查是否已经点赞
$check = $conn->query("SELECT id FROM likes WHERE user_id = $user_id AND post_id = $post_id");

if ($check->num_rows > 0) {
    // 已赞 -> 取消点赞
    $conn->query("DELETE FROM likes WHERE user_id = $user_id AND post_id = $post_id");
    echo json_encode(['success' => true, 'action' => 'unliked']);
} else {
    // 未赞 -> 点赞
    $conn->query("INSERT INTO likes (user_id, post_id) VALUES ($user_id, $post_id)");
    echo json_encode(['success' => true, 'action' => 'liked']);
}

// 🎲 触发掉落检查
require_once 'api_shop.php'; // 引入商店逻辑
$drop = trigger_void_drop($conn, $user_id);

// 返回结果时带上 drop 信息
echo json_encode([
    'success' => true, 
    'action' => ($check->num_rows > 0) ? 'unliked' : 'liked',
    'drop' => $drop // 如果有掉落，这里会有数据
]);
?>

