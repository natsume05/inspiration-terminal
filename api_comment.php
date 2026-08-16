<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$uid = current_user_id();
if ($uid === 0) {
    emit_json(['success' => false, 'msg' => '请先登录']);
}

$action = get_text('action');

// 提交评论
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = post_int('post_id');
    $content = post_text('content');

    if ($content === '') {
        emit_json(['success' => false, 'msg' => '内容不能为空']);
    }

    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $post_id, $uid, $content);

    if ($stmt->execute()) {
        $conn->query("UPDATE users SET stardust = stardust + 2 WHERE id = $uid");
        emit_json(['success' => true, 'msg' => '评论发布成功 (+2 ✨)']);
    }
    emit_json(['success' => false, 'msg' => '系统错误']);
}

// 获取评论列表
if ($action === 'list') {
    $post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;

    $stmt = $conn->prepare(
        "SELECT c.*, u.username, u.avatar, u.custom_title
         FROM comments c
         LEFT JOIN users u ON c.user_id = u.id
         WHERE c.post_id = ?
         ORDER BY c.created_at ASC"
    );
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $comments = [];
    while ($row = $res->fetch_assoc()) {
        $comments[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'avatar' => $row['avatar'] ? $row['avatar'] : 'default.png',
            'title' => $row['custom_title'],
            'content' => htmlspecialchars($row['content']),
            'time' => date('m-d H:i', strtotime($row['created_at'])),
        ];
    }

    emit_json(['success' => true, 'data' => $comments]);
}
