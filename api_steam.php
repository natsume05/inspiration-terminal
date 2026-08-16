<?php
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

$action = get_text('action', 'deals');
$api_base = "https://www.cheapshark.com/api/1.0";

if ($action === 'calendar') {
    $events = [
        ['name' => '🌸 春季特卖', 'date' => '2026-03-14', 'icon' => '🌱', 'desc' => '万物复苏，独立游戏的主场'],
        ['name' => '🏗️ 建造节',   'date' => '2026-04-20', 'icon' => '🔨', 'desc' => '模拟经营类游戏爱好者的狂欢'],
        ['name' => '⚔️ 体育节',   'date' => '2026-05-15', 'icon' => '⚽', 'desc' => '运动与竞技类游戏专场'],
        ['name' => '🌞 夏日大促', 'date' => '2026-06-25', 'icon' => '🔥', 'desc' => '全年力度最大，准备好剁手'],
        ['name' => '👻 万圣节',   'date' => '2026-10-26', 'icon' => '🎃', 'desc' => '恐怖游戏与灵异题材'],
        ['name' => '🍂 秋季特卖', 'date' => '2026-11-22', 'icon' => '🍁', 'desc' => 'Steam大奖提名开启'],
        ['name' => '❄️ 冬季特卖', 'date' => '2026-12-21', 'icon' => '🎄', 'desc' => '年终清算，清空愿望单'],
    ];
    emit_json($events);
}

if ($action === 'trending') {
    $url = "$api_base/deals?storeID=1&steamRating=85&metacritic=80&sortBy=Metacritic&pageSize=8";
} elseif ($action === 'search') {
    $title = urlencode(get_text('title'));
    $url = "$api_base/deals?storeID=1&title=$title&pageSize=12";
} else {
    $url = "$api_base/deals?storeID=1&onSale=1&metacritic=75&pageSize=12&sortBy=Savings";
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
