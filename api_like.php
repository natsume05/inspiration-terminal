<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/drop_system.php';

$user_id = current_user_id();
$post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;

if ($user_id === 0 || $post_id <= 0) {
    emit_json(['success' => false, 'message' => '未授权或参数缺失']);
}

$check = $conn->query("SELECT id FROM likes WHERE user_id = $user_id AND post_id = $post_id");
$is_liked = ($check && $check->num_rows > 0);

if ($is_liked) {
    $conn->query("DELETE FROM likes WHERE user_id = $user_id AND post_id = $post_id");
} else {
    $conn->query("INSERT INTO likes (user_id, post_id) VALUES ($user_id, $post_id)");
}

$drop = trigger_void_drop($conn, $user_id);

emit_json([
    'success' => true,
    'action' => $is_liked ? 'unliked' : 'liked',
    'drop' => $drop,
]);
