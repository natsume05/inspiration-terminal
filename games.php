<?php
require 'includes/db.php';
$page_title = "娱乐终端";
$style = "community";
include 'includes/header.php';
?>

<div class="container" style="max-width: 800px; margin-top: 50px; text-align: center;">
    <h1 style="color: #f6d365; margin-bottom: 10px;">🎲 娱乐终端 (Alpha)</h1>
    <p style="color: #888;">虚空中的游乐场。探索未知，赢取星尘。</p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-top: 40px;">
        
        <div class="side-card" style="padding: 30px; opacity: 0.7;">
            <div style="font-size: 3rem; margin-bottom: 15px;">🃏</div>
            <h3>虚空牌局</h3>
            <p style="font-size: 0.8rem; color: #666;">德州扑克 / 昆特牌变体。</p>
            <button class="dream-btn small" disabled style="background:#333; color:#666; cursor:not-allowed; width:100%;">🚧 施工中</button>
        </div>

        <div class="side-card" style="padding: 30px; opacity: 0.7;">
            <div style="font-size: 3rem; margin-bottom: 15px;">🧩</div>
            <h3>知识解密</h3>
            <p style="font-size: 0.8rem; color: #666;">每日一道谜题，奖励丰厚。</p>
            <button class="dream-btn small" disabled style="background:#333; color:#666; cursor:not-allowed; width:100%;">🚧 施工中</button>
        </div>

    </div>

    <div style="margin-top: 50px;">
        <a href="community.php" class="btn-outline">🔙 返回大厅</a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>