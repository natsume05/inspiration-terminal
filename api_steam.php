<?php
// api_steam.php - 史低指挥部 (Ver 3.0)
header('Content-Type: application/json');
error_reporting(0);

$action = isset($_GET['action']) ? $_GET['action'] : 'deals';
$api_base = "https://www.cheapshark.com/api/1.0";

// 1. 热门大作/口碑榜 (Trending/High Rated)
if ($action == 'trending') {
    // 逻辑：Steam平台(1)，好评率>85%，Metacritic>80%，按Metacritic排序
    // 这能筛选出类似《艾尔登法环》《博德之门3》这样的顶级大作
    $url = "$api_base/deals?storeID=1&steamRating=85&metacritic=80&sortBy=Metacritic&pageSize=8";
} 

// 2. 史低折扣 (Deals Radar)
else if ($action == 'deals') {
    // 逻辑：正在打折，Metacritic>75，按折扣力度排序
    $url = "$api_base/deals?storeID=1&onSale=1&metacritic=75&pageSize=12&sortBy=Savings";
}

// 3. 搜索功能 (Search)
else if ($action == 'search') {
    $title = urlencode($_GET['title']);
    // 搜索结果
    $url = "$api_base/deals?storeID=1&title=$title&pageSize=12";
}

// 4. 促销年历 (Calendar Data)
else if ($action == 'calendar') {
    // 静态数据：2026年 Steam 预估大促时间表
    $events = [
        ['name' => '🌸 春季特卖', 'date' => '2026-03-14', 'icon' => '🌱', 'desc' => '万物复苏，独立游戏的主场'],
        ['name' => '🏗️ 建造节',   'date' => '2026-04-20', 'icon' => '🔨', 'desc' => '模拟经营类游戏爱好者的狂欢'],
        ['name' => '⚔️ 体育节',   'date' => '2026-05-15', 'icon' => '⚽', 'desc' => '运动与竞技类游戏专场'],
        ['name' => '🌞 夏日大促', 'date' => '2026-06-25', 'icon' => '🔥', 'desc' => '全年力度最大，准备好剁手'],
        ['name' => '👻 万圣节',   'date' => '2026-10-26', 'icon' => '🎃', 'desc' => '恐怖游戏与灵异题材'],
        ['name' => '🍂 秋季特卖', 'date' => '2026-11-22', 'icon' => '🍁', 'desc' => 'Steam大奖提名开启'],
        ['name' => '❄️ 冬季特卖', 'date' => '2026-12-21', 'icon' => '🎄', 'desc' => '年终清算，清空愿望单']
    ];
    echo json_encode($events);
    exit;
}

// 执行请求
if (isset($url)) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    echo $response;
}
?>