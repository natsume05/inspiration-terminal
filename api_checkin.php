<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$uid = current_user_id();
if ($uid === 0) {
    emit_json(['status' => 'error', 'msg' => '未连接到虚空终端']);
}

$today = date('Y-m-d');

try {
    $conn->query("INSERT IGNORE INTO user_daily_limits (user_id, date) VALUES ($uid, '$today')");

    $result = $conn->query("SELECT checkin_status FROM user_daily_limits WHERE user_id = $uid AND date = '$today'");
    $row = $result->fetch_assoc();

    if ($row['checkin_status'] == 1) {
        emit_json(['status' => 'error', 'msg' => '今日补给已领取，明天再来吧！']);
    }

    $stardust_reward = rand(20, 50);
    $exp_reward = 20;

    $conn->begin_transaction();
    $conn->query("UPDATE users SET stardust = stardust + $stardust_reward, exp = exp + $exp_reward WHERE id = $uid");
    $conn->query("UPDATE user_daily_limits SET checkin_status = 1 WHERE user_id = $uid AND date = '$today'");
    $conn->commit();

    $new_data = $conn->query("SELECT stardust FROM users WHERE id = $uid")->fetch_assoc();

    emit_json([
        'status' => 'success',
        'msg' => "签到成功！\n获得：{$stardust_reward} 星尘, {$exp_reward} 经验",
        'new_balance' => $new_data['stardust'],
    ]);
} catch (Exception $e) {
    $conn->rollback();
    emit_json(['status' => 'error', 'msg' => '系统故障，请稍后再试']);
}
