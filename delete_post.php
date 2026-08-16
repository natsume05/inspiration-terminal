<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        exit('🛑 删除失败：非法请求 (CSRF Error)');
    }
}

if (!is_admin()) {
    exit('❌ 权限不足：只有舰长可以执行此操作。');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = post_int('post_id');

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

redirect('community.php');
