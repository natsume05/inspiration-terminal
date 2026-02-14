<?php
// api_comment.php - 评论系统后端
require 'includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => '请先登录']); exit;
}

$uid = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 1. 提交评论
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = intval($_POST['post_id']);
    $content = trim($conn->real_escape_string($_POST['content']));
    
    if (empty($content)) { echo json_encode(['success' => false, 'msg' => '内容不能为空']); exit; }
    
    $sql = "INSERT INTO comments (post_id, user_id, content) VALUES ($post_id, $uid, '$content')";
    if ($conn->query($sql)) {
        // 评论奖励 2 星尘
        $conn->query("UPDATE users SET stardust = stardust + 2 WHERE id = $uid");
        echo json_encode(['success' => true, 'msg' => '评论发布成功 (+2 ✨)']);
    } else {
        echo json_encode(['success' => false, 'msg' => '系统错误']);
    }
}

// 2. 获取评论列表
elseif ($action === 'list') {
    $post_id = intval($_GET['post_id']);
    $sql = "SELECT c.*, u.username, u.avatar, u.custom_title 
            FROM comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.post_id = $post_id 
            ORDER BY c.created_at ASC";
    
    $res = $conn->query($sql);
    $comments = [];
    while($row = $res->fetch_assoc()) {
        $comments[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'avatar' => $row['avatar'] ? $row['avatar'] : 'default.png',
            'title' => $row['custom_title'],
            'content' => htmlspecialchars($row['content']), // 安全过滤
            'time' => date('m-d H:i', strtotime($row['created_at']))
        ];
    }
    echo json_encode(['success' => true, 'data' => $comments]);
}
?>