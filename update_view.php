<?php
require 'includes/db.php';

// 接收 JSON 数据
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['id'])) {
    $id = intval($data['id']);
    // 数据库阅读量 + 1
    $conn->query("UPDATE blog_posts SET views = views + 1 WHERE id = $id");
    echo json_encode(["success" => true]);
}
?>