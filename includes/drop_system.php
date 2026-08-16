<?php
/**
 * 虚空掉落系统。
 */

/**
 * 随机掉落逻辑（供点赞、发帖等操作触发）。
 *
 * @return array|null 掉落奖励；未触发返回 null。
 */
function trigger_void_drop($conn, $uid)
{
    $today = date('Y-m-d');

    $conn->query("INSERT IGNORE INTO user_daily_limits (user_id, date) VALUES ($uid, '$today')");
    $result = $conn->query("SELECT drop_count FROM user_daily_limits WHERE user_id = $uid AND date = '$today'");
    $limit = $result->fetch_assoc();

    if ($limit['drop_count'] >= 1) {
        return null;
    }

    // 每天最多触发 1 次，触发概率 5%。
    if (rand(1, 100) > 5) {
        return null;
    }

    $conn->query("UPDATE user_daily_limits SET drop_count = drop_count + 1 WHERE user_id = $uid AND date = '$today'");

    $amount = rand(5, 20);
    $conn->query("UPDATE users SET stardust = stardust + $amount WHERE id = $uid");

    return ['type' => 'stardust', 'val' => $amount, 'msg' => '🌌 虚空回响：你在探索中发现了微量星尘。'];
}
