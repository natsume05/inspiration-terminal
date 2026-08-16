<?php
/**
 * 等级与经验系统。
 */

/**
 * 根据经验值返回对应等级称号。
 */
function get_rank_name($exp)
{
    if ($exp < 100) {
        return '🔰 旅行者';
    }
    if ($exp < 500) {
        return '🧭 探索者';
    }
    if ($exp < 2000) {
        return '🚀 领航员';
    }
    if ($exp < 5000) {
        return '⭐ 星际领主';
    }
    return '🌌 虚空主宰';
}

/**
 * 给指定用户增加经验值。
 */
function add_exp($conn, $user_id, $amount)
{
    $stmt = $conn->prepare("UPDATE users SET exp = exp + ? WHERE id = ?");
    $stmt->bind_param('ii', $amount, $user_id);
    $stmt->execute();
    $stmt->close();
}
