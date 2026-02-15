<?php
// api_checkin.php - 每日补给接口 (修复版)
require 'includes/db.php';
require_once 'includes/level_system.php'; // 确保引入经验系统
header('Content-Type: application/json');

// 1. 登录检查
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error', 'msg'=>'未连接到虚空终端']); exit;
}

$uid = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
    // 2. 初始化今日记录
    // 如果今天还没抽过奖也没签到过，这里会创建一行新记录
    $conn->query("INSERT IGNORE INTO user_daily_limits (user_id, date) VALUES ($uid, '$today')");

    // 3. 检查状态
    $res = $conn->query("SELECT checkin_status FROM user_daily_limits WHERE user_id=$uid AND date='$today'");
    $row = $res->fetch_assoc();

    if ($row['checkin_status'] == 1) {
        echo json_encode(['status'=>'error', 'msg'=>'今日补给已领取，明天再来吧！']); exit;
    }

    // 4. 发放奖励 (开启事务)
    $conn->begin_transaction();

    $stardust_reward = rand(20, 50); // 随机星尘
    $exp_reward = 20; // 固定经验

    // 更新用户余额
    $conn->query("UPDATE users SET stardust = stardust + $stardust_reward, exp = exp + $exp_reward WHERE id=$uid");
    
    // 标记已签到
    $conn->query("UPDATE user_daily_limits SET checkin_status = 1 WHERE user_id=$uid AND date='$today'");

    $conn->commit();
    
    // 5. 返回最新数据
    $new_data = $conn->query("SELECT stardust FROM users WHERE id=$uid")->fetch_assoc();
    
    echo json_encode([
        'status'=>'success', 
        'msg'=>"签到成功！\n获得：{$stardust_reward} 星尘, {$exp_reward} 经验",
        'new_balance' => $new_data['stardust'] // 注意：前端这里可能叫 new_stardust，要对应
    ]);

} catch (Exception $e) {
    $conn->rollback();
    // 调试模式：把具体错误发回去（生产环境通常不这么做）
    echo json_encode(['status'=>'error', 'msg'=>'系统故障: ' . $e->getMessage()]);
}
?>