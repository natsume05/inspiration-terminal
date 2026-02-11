<?php
// --- 🟢 新增：消音器 (禁止报错破坏 JSON) ---
error_reporting(0);
ini_set('display_errors', 0);

// api_checkin.php - 处理签到请求
header('Content-Type: application/json');
require 'includes/db.php';
session_start();

// 1. 检查是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => '⚠️ 请先接入终端 (登录)']);
    exit;
}

$uid = $_SESSION['user_id'];
$today = date('Y-m-d');

// 2. 查询用户上次签到时间
$sql = "SELECT last_checkin_date, stardust, streak_days FROM users WHERE id = $uid";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

// 3. 判断今天是否已签
if ($user['last_checkin_date'] == $today) {
    echo json_encode(['status' => 'error', 'msg' => '📅 今日补给已领取，明天再来吧！']);
    exit;
}

// 4. 计算奖励逻辑
$reward = rand(10, 30); // 基础奖励 10-30 星尘
$new_streak = 1;

// 检查是否是连续签到 (上次签到是昨天)
$yesterday = date('Y-m-d', strtotime('-1 day'));
if ($user['last_checkin_date'] == $yesterday) {
    $new_streak = $user['streak_days'] + 1;
    // 连签奖励：每多连签一天，多给 2 点，上限加 20 点
    $bonus = min(($new_streak - 1) * 2, 20);
    $reward += $bonus;
    $msg = "🎉 连续签到 $new_streak 天！获得 $reward 星尘 (含加成)";
} else {
    // 断签了，重置为 1 天
    $msg = "✅ 补给领取成功！获得 $reward 星尘";
}

// 5. 更新数据库
$update_sql = "UPDATE users SET 
               stardust = stardust + $reward, 
               last_checkin_date = '$today', 
               streak_days = $new_streak 
               WHERE id = $uid";

if ($conn->query($update_sql)) {
    // 更新 Session 里的数据，方便前端读取 (如果有存的话)
    // $_SESSION['stardust'] = ... (可选)
    
    echo json_encode([
        'status' => 'success', 
        'msg' => $msg, 
        'new_balance' => $user['stardust'] + $reward,
        'new_streak' => $new_streak
    ]);
} else {
    echo json_encode(['status' => 'error', 'msg' => '数据库写入失败']);
}
?>