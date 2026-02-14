<?php
require 'includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error', 'msg'=>'未连接到虚空终端']); exit;
}

$uid = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$today = date('Y-m-d');

// --- 1. 购买商品 ---
if ($action == 'buy') {
    $item_id = intval($_POST['item_id']);
    
    // 检查商品
    $item = $conn->query("SELECT * FROM shop_items WHERE id=$item_id AND is_forsale=1")->fetch_assoc();
    if (!$item) { echo json_encode(['status'=>'error', 'msg'=>'商品已下架或不存在']); exit; }

    // 检查是否已拥有
    $check = $conn->query("SELECT id FROM user_inventory WHERE user_id=$uid AND item_id=$item_id");
    if ($check->num_rows > 0) { echo json_encode(['status'=>'error', 'msg'=>'你已经拥有此遗物了']); exit; }

    // 检查余额
    $user = $conn->query("SELECT stardust FROM users WHERE id=$uid")->fetch_assoc();
    if ($user['stardust'] < $item['price']) {
        echo json_encode(['status'=>'error', 'msg'=>'星尘不足，去探索虚空吧']); exit;
    }

    // 交易执行
    $conn->begin_transaction();
    try {
        $conn->query("UPDATE users SET stardust = stardust - {$item['price']} WHERE id=$uid");
        $conn->query("INSERT INTO user_inventory (user_id, item_id) VALUES ($uid, $item_id)");
        $conn->commit();
        echo json_encode(['status'=>'success', 'msg'=>'交易完成，遗物已归档', 'new_balance' => $user['stardust'] - $item['price']]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error', 'msg'=>'交易失败: '.$conn->error]);
    }
}

// --- 2. 每日抽奖 (Gacha) ---
else if ($action == 'gacha') {
    // 检查每日限制
    $conn->query("INSERT IGNORE INTO user_daily_limits (user_id, date) VALUES ($uid, '$today')");
    $limit = $conn->query("SELECT gacha_count FROM user_daily_limits WHERE user_id=$uid AND date='$today'")->fetch_assoc();
    
    if ($limit['gacha_count'] >= 1) {
        echo json_encode(['status'=>'error', 'msg'=>'今日虚空共鸣次数已用尽']); exit;
    }

    // 更新次数
    $conn->query("UPDATE user_daily_limits SET gacha_count = gacha_count + 1 WHERE user_id=$uid AND date='$today'");

    // === 概率算法 ===
    // 1-60: 少量星尘 (保底)
    // 61-85: 普通商品
    // 86-95: 稀有商品
    // 96-99: 史诗商品
    // 100:   传说商品 (欧皇)
    $roll = rand(1, 100);
    $reward = [];

    if ($roll <= 60) {
        // 只有星尘
        $amount = rand(10, 50);
        $conn->query("UPDATE users SET stardust = stardust + $amount WHERE id=$uid");
        $reward = ['type'=>'stardust', 'val'=>$amount, 'name'=>'星尘碎片', 'rarity'=>'common'];
    } else {
        // 抽商品
        $rarity = 'common';
        if ($roll > 85 && $roll <= 95) $rarity = 'rare';
        if ($roll > 95 && $roll <= 99) $rarity = 'epic';
        if ($roll == 100) $rarity = 'legendary';

        // 随机取一个该稀有度的商品（排除已拥有的）
        $sql = "SELECT * FROM shop_items WHERE rarity='$rarity' AND id NOT IN (SELECT item_id FROM user_inventory WHERE user_id=$uid) ORDER BY RAND() LIMIT 1";
        $item_res = $conn->query($sql);

        if ($item_res->num_rows > 0) {
            $item = $item_res->fetch_assoc();
            $conn->query("INSERT INTO user_inventory (user_id, item_id) VALUES ($uid, {$item['id']})");
            $reward = ['type'=>'item', 'name'=>$item['name'], 'icon'=>$item['icon'], 'rarity'=>$item['rarity']];
        } else {
            // 如果该稀有度商品全齐了，给大量星尘补偿
            $amount = ($roll > 95) ? 500 : 100;
            $conn->query("UPDATE users SET stardust = stardust + $amount WHERE id=$uid");
            $reward = ['type'=>'stardust', 'val'=>$amount, 'name'=>'高纯度星尘结晶', 'rarity'=>'epic'];
        }
    }

    echo json_encode(['status'=>'success', 'reward'=>$reward]);
}

// ... (接在 gacha 逻辑后面)

// --- 3. 装备/卸下物品 ---
else if ($action == 'toggle_equip') {
    $item_id = intval($_POST['item_id']);
    
    // 1. 确认用户拥有该物品，并获取类型
    $check = $conn->query("
        SELECT ui.id, s.type 
        FROM user_inventory ui 
        JOIN shop_items s ON ui.item_id = s.id 
        WHERE ui.user_id = $uid AND ui.item_id = $item_id
    ");
    
    if ($check->num_rows == 0) { echo json_encode(['status'=>'error', 'msg'=>'你还没有拥有该物品']); exit; }
    
    $data = $check->fetch_assoc();
    $type = $data['type'];

    // 2. 检查当前状态
    $current = $conn->query("SELECT is_equipped FROM user_inventory WHERE user_id=$uid AND item_id=$item_id")->fetch_assoc();
    $is_equipped = $current['is_equipped'];

    $conn->begin_transaction();
    try {
        if ($is_equipped) {
            // 如果已装备 -> 卸下
            $conn->query("UPDATE user_inventory SET is_equipped = 0 WHERE user_id=$uid AND item_id=$item_id");
            $msg = "已卸下装备";
            $new_state = 0;
        } else {
            // 如果未装备 -> 
            // A. 先把同类型的所有装备都卸下 (互斥逻辑)
            // 需要先找到该用户所有该类型的 item_id，太麻烦，直接联合更新
            $conn->query("
                UPDATE user_inventory ui
                JOIN shop_items s ON ui.item_id = s.id
                SET ui.is_equipped = 0
                WHERE ui.user_id = $uid AND s.type = '$type'
            ");
            
            // B. 装备当前这个
            $conn->query("UPDATE user_inventory SET is_equipped = 1 WHERE user_id=$uid AND item_id=$item_id");
            $msg = "装备已激活";
            $new_state = 1;
        }
        $conn->commit();
        echo json_encode(['status'=>'success', 'msg'=>$msg, 'is_equipped'=>$new_state]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error', 'msg'=>'系统故障']);
    }
}


// --- 4. 随机掉落逻辑 (供其他 PHP 调用，不是直接 HTTP 请求) ---
function trigger_void_drop($conn, $uid) {
    $today = date('Y-m-d');
    // 检查每日掉落限制
    $conn->query("INSERT IGNORE INTO user_daily_limits (user_id, date) VALUES ($uid, '$today')");
    $limit = $conn->query("SELECT drop_count FROM user_daily_limits WHERE user_id=$uid AND date='$today'")->fetch_assoc();
    
    // 每天最多触发 1 次掉落
    if ($limit['drop_count'] >= 1) return null;

    // 触发概率：5%
    if (rand(1, 100) <= 5) {
        $conn->query("UPDATE user_daily_limits SET drop_count = drop_count + 1 WHERE user_id=$uid AND date='$today'");
        
        // 掉落奖励：大概率是星尘，极小概率是稀有道具
        $amount = rand(5, 20);
        $conn->query("UPDATE users SET stardust = stardust + $amount WHERE id=$uid");
        return ['type'=>'stardust', 'val'=>$amount, 'msg'=>'🌌 虚空回响：你在探索中发现了微量星尘。'];
    }
    return null;
}
?>