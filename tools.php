<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$page_title = "提瓦特百宝箱";
$page_description = "提瓦特百宝箱——实用工具导航聚合，GitHub 开源猎手与 Steam 指挥室。";
$page_keywords = "工具箱,效率工具,导航";
$style = "tools";

include __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 1000px; margin-top: 60px; text-align: center;">
    <h1>💎 提瓦特百宝箱</h1>
    <p style="color:#888; margin-bottom: 60px; font-style: italic;">
        “旅行者，请选择你要接入的终端模块。”
    </p>

    <div class="portal-grid">
        <a href="tools_github.php" class="portal-card" style="background: linear-gradient(135deg, #24292e, #1b1f23);">
            <div class="p-icon">🐙</div>
            <h3>GitHub 开源猎手</h3>
            <p>浏览全球热门趋势，发现技术宝藏。</p>
        </a>

        <a href="steam.php" class="portal-card" style="background: linear-gradient(135deg, #171a21, #0e1115);">
            <div class="p-icon">🎮</div>
            <h3>Steam 战略指挥室</h3>
            <p>史低价格监控，大促日历与口碑榜单。</p>
        </a>

        <a href="tools_links.php" class="portal-card" style="background: linear-gradient(135deg, #005c97, #363795);">
            <div class="p-icon">🛰️</div>
            <h3>星际导航终端</h3>
            <p>常用开发工具与生活站点索引。</p>
        </a>
    </div>
</div>

<style>
.portal-card {
    display: block; padding: 40px 30px; border-radius: 16px;
    color: #fff; text-decoration: none; transition: transform 0.3s, box-shadow 0.3s;
    border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.portal-card:hover { transform: translateY(-8px); box-shadow: 0 15px 40px rgba(0,0,0,0.2); }
.p-icon { font-size: 3.5rem; margin-bottom: 20px; }
.portal-card h3 { margin: 0 0 10px 0; font-size: 1.4rem; }
.portal-card p { margin: 0; color: rgba(255,255,255,0.7); line-height: 1.5; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
