<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/drop_system.php';

$uid = current_user_id();
if ($uid === 0) {
    emit_json(['status' => 'error', 'msg' => '未连接到虚空终端']);
}

$action = get_text('action');
$today = date('Y-m-d');

// --- 购买商品 ---
if ($action === 'buy') {
    $item_id = post_int('item_id');

    $result = $conn->query("SELECT * FROM shop_items WHERE id = $item_id AND is_forsale = 1");
    $item = $result ? $result->fetch_assoc() : null;
    if (!$item) {
        emit_json(['status' => 'error', 'msg' => '商品已下架或不存在']);
    }

    $check = $conn->query("SELECT id FROM user_inventory WHERE user_id = $uid AND item_id = $item_id");
    if ($check && $check->num_rows > 0) {
        emit_json(['status' => 'error', 'msg' => '你已经拥有此遗物了']);
    }

    $user = $conn->query("SELECT stardust FROM users WHERE id = $uid")->fetch_assoc();
    if ($user['stardust'] < $item['price']) {
        emit_json(['status' => 'error', 'msg' => '星尘不足，去探索虚空吧']);
    }

    $conn->begin_transaction();
    try {
        $conn->query("UPDATE users SET stardust = stardust - {$item['price']} WHERE id = $uid");
        $conn->query("INSERT INTO user_inventory (user_id, item_id) VALUES ($uid, $item_id)");
        $conn->commit();
        emit_json(['status' => 'success', 'msg' => '交易完成，遗物已归档', 'new_balance' => $user['stardust'] - $item['price']]);
    } catch (Exception $e) {
        $conn->rollback();
        emit_json(['status' => 'error', 'msg' => '交易失败，请稍后再试']);
    }
}

// --- 每日抽奖 ---
if ($action === 'gacha') {
    $conn->query("INSERT IGNORE INTO user_daily_limits (user_id, date) VALUES ($uid, '$today')");
    $limit = $conn->query("SELECT gacha_count FROM user_daily_limits WHERE user_id = $uid AND date = '$today'")->fetch_assoc();

    if ($limit['gacha_count'] >= 1) {
        emit_json(['status' => 'error', 'msg' => '今日虚空共鸣次数已用尽']);
    }

    $conn->query("UPDATE user_daily_limits SET gacha_count = gacha_count + 1 WHERE user_id = $uid AND date = '$today'");

    $roll = rand(1, 100);

    if ($roll <= 60) {
        $amount = rand(10, 50);
        $conn->query("UPDATE users SET stardust = stardust + $amount WHERE id = $uid");
        emit_json(['status' => 'success', 'reward' => ['type' => 'stardust', 'val' => $amount, 'name' => '星尘碎片', 'rarity' => 'common']]);
    }

    $rarity = 'common';
    if ($roll > 85 && $roll <= 95) $rarity = 'rare';
    if ($roll > 95 && $roll <= 99) $rarity = 'epic';
    if ($roll === 100) $rarity = 'legendary';

    $item_res = $conn->query("SELECT * FROM shop_items WHERE rarity = '$rarity' AND id NOT IN (SELECT item_id FROM user_inventory WHERE user_id = $uid) ORDER BY RAND() LIMIT 1");

    if ($item_res && $item_res->num_rows > 0) {
        $item = $item_res->fetch_assoc();
        $conn->query("INSERT INTO user_inventory (user_id, item_id) VALUES ($uid, {$item['id']})");
        emit_json(['status' => 'success', 'reward' => ['type' => 'item', 'name' => $item['name'], 'icon' => $item['icon'], 'rarity' => $item['rarity']]]);
    }

    $amount = ($roll > 95) ? 500 : 100;
    $conn->query("UPDATE users SET stardust = stardust + $amount WHERE id = $uid");
    emit_json(['status' => 'success', 'reward' => ['type' => 'stardust', 'val' => $amount, 'name' => '高纯度星尘结晶', 'rarity' => 'epic']]);
}

// --- 装备/卸下物品 ---
if ($action === 'toggle_equip') {
    $item_id = post_int('item_id');

    $check = $conn->query(
        "SELECT ui.id, s.type
         FROM user_inventory ui
         JOIN shop_items s ON ui.item_id = s.id
         WHERE ui.user_id = $uid AND ui.item_id = $item_id"
    );

    if (!$check || $check->num_rows === 0) {
        emit_json(['status' => 'error', 'msg' => '你还没有拥有该物品']);
    }

    $type = $check->fetch_assoc()['type'];
    $current = $conn->query("SELECT is_equipped FROM user_inventory WHERE user_id = $uid AND item_id = $item_id")->fetch_assoc();
    $is_equipped = $current['is_equipped'];

    $conn->begin_transaction();
    try {
        if ($is_equipped) {
            $conn->query("UPDATE user_inventory SET is_equipped = 0 WHERE user_id = $uid AND item_id = $item_id");
            $msg = "已卸下装备";
            $new_state = 0;
        } else {
            $conn->query(
                "UPDATE user_inventory ui
                 JOIN shop_items s ON ui.item_id = s.id
                 SET ui.is_equipped = 0
                 WHERE ui.user_id = $uid AND s.type = '$type'"
            );
            $conn->query("UPDATE user_inventory SET is_equipped = 1 WHERE user_id = $uid AND item_id = $item_id");
            $msg = "装备已激活";
            $new_state = 1;
        }
        $conn->commit();
        emit_json(['status' => 'success', 'msg' => $msg, 'is_equipped' => $new_state]);
    } catch (Exception $e) {
        $conn->rollback();
        emit_json(['status' => 'error', 'msg' => '系统故障']);
    }
}
