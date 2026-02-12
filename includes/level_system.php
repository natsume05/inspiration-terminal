<?php
// includes/level_system.php - 等级与经验核心

function get_rank_name($exp) {
    if ($exp < 100) return '🔰 旅行者';       // 0-99
    if ($exp < 500) return '🧭 探索者';       // 100-499
    if ($exp < 2000) return '🚀 领航员';      // 500-1999
    if ($exp < 5000) return '⭐ 星际领主';    // 2000-4999
    return '🌌 虚空主宰';                     // 5000+
}

function add_exp($conn, $user_id, $amount) {
    // 1. 增加经验
    $sql = "UPDATE users SET exp = exp + $amount WHERE id = $user_id";
    $conn->query($sql);
    
    // (进阶：这里可以判断是否升级并发送通知，暂时先略过)
}

function send_notification($conn, $receiver_id, $sender_id, $type, $target_id) {
    // 自己给自己点赞/评论不发通知
    if ($receiver_id == $sender_id) return;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, sender_id, type, target_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $receiver_id, $sender_id, $type, $target_id);
    $stmt->execute();
}
?>