<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$page_title = "Steam 战略指挥室";
$page_description = "Steam 战略指挥室——史低价格监控、大促日历与口碑榜单。";
$page_keywords = "Steam,游戏,折扣,史低";
$style = "steam";

include __DIR__ . '/includes/header.php';
?>

<div class="container steam-layout">
    <div class="section-title">📅 2026 战术时间表 (Sale Calendar)</div>
    <div class="calendar-wrapper">
        <div class="calendar-track" id="calendar-track">
            <div class="loading">加载时间流...</div>
        </div>
    </div>

    <div class="search-module fade-in">
        <div class="search-content">
            <h2>🔍 目标检索</h2>
            <p>输入代号（游戏英文名），检索全网最低价格情报。</p>
            <div class="big-search-box">
                <input type="text" id="game-search" placeholder="例如: Cyberpunk 2077..." onkeypress="handleEnter(event)">
                <button onclick="searchGames()" class="dream-btn">🚀 扫描</button>
            </div>
        </div>
    </div>

    <div id="search-result-area" style="display:none; margin-bottom: 50px;">
        <h3 class="result-title">🎯 扫描结果</h3>
        <div id="search-grid" class="game-grid"></div>
    </div>

    <div class="section-title">🏆 殿堂级·口碑佳作 (Top Rated)</div>
    <p class="section-desc">收录 Metacritic 评分 > 80 的必玩神作。</p>
    <div id="trending-grid" class="game-grid trending-mode">
        <div class="loading">正在连接 Steam 核心数据库...</div>
    </div>

    <div class="section-title" style="margin-top: 50px;">📉 史低探测雷达 (Deep Discounts)</div>
    <p class="section-desc">折扣力度优先，兼顾评分，拒绝 4399。</p>
    <div id="deals-grid" class="game-grid">
        <div class="loading">正在扫描低价信号...</div>
    </div>

    <div style="text-align:center; margin-top:60px; margin-bottom: 40px;">
        <a href="tools.php" class="btn-outline">🔙 返回百宝箱</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadCalendar();
    loadTrending();
    loadDeals();
});

function handleEnter(e) {
    if (e.key === 'Enter') searchGames();
}

function loadCalendar() {
    fetch('api_steam.php?action=calendar')
        .then(res => res.json())
        .then(events => {
            const track = document.getElementById('calendar-track');
            track.innerHTML = '';

            const today = new Date();

            events.forEach(event => {
                const eventDate = new Date(event.date);
                const diffDays = Math.ceil((eventDate - today) / (1000 * 60 * 60 * 24));

                let statusClass = 'future';
                let statusText = `${diffDays} 天后`;

                if (diffDays < 0 && diffDays > -14) { statusClass = 'active'; statusText = '🔥 进行中'; }
                else if (diffDays < 0) { statusClass = 'past'; statusText = '已结束'; }
                else if (diffDays <= 30) { statusClass = 'near'; statusText = `⚠️ 仅 ${diffDays} 天`; }

                const card = document.createElement('div');
                card.className = `calendar-card ${statusClass}`;
                card.innerHTML = `
                    <div class="cal-icon">${event.icon}</div>
                    <div class="cal-name">${event.name}</div>
                    <div class="cal-date">${event.date}</div>
                    <div class="cal-status">${statusText}</div>
                `;
                track.appendChild(card);
            });
        });
}

function loadTrending() {
    fetch('api_steam.php?action=trending')
        .then(res => res.json())
        .then(data => renderGames(data, document.getElementById('trending-grid')));
}

function loadDeals() {
    fetch('api_steam.php?action=deals')
        .then(res => res.json())
        .then(data => renderGames(data, document.getElementById('deals-grid')));
}

function searchGames() {
    const title = document.getElementById('game-search').value.trim();
    if (!title) return;

    document.getElementById('search-result-area').style.display = 'block';
    const grid = document.getElementById('search-grid');
    grid.innerHTML = '<div class="loading">🔍 全网检索中...</div>';

    fetch(`api_steam.php?action=search&title=${encodeURIComponent(title)}`)
        .then(res => res.json())
        .then(data => renderGames(data, grid));
}

function renderGames(games, container) {
    container.innerHTML = '';
    if (!games || games.length === 0) {
        container.innerHTML = '<p style="color:#666;">未探测到相关信号。</p>';
        return;
    }

    games.forEach(game => {
        const savings = Math.round(game.savings);
        const metaScore = game.metacriticScore > 0 ? `<span class="tag meta">M ${game.metacriticScore}</span>` : '';
        const steamRate = game.steamRatingPercent > 0 ? `<span class="tag steam">👍 ${game.steamRatingPercent}%</span>` : '';
        const imgUrl = game.thumb.replace('capsule_sm_120.jpg', 'header.jpg');

        const card = document.createElement('div');
        card.className = 'game-card fade-in';
        card.onclick = () => window.open(`https://store.steampowered.com/app/${game.steamAppID}`, '_blank');
        card.innerHTML = `
            <div class="card-cover">
                <img src="${imgUrl}" onerror="this.src='${game.thumb}'" loading="lazy">
                ${savings > 0 ? `<div class="discount-badge">-${savings}%</div>` : ''}
            </div>
            <div class="card-body">
                <h4 title="${game.title}">${game.title}</h4>
                <div class="tags-row">${metaScore} ${steamRate}</div>
                <div class="price-row">
                    <span class="old">$${game.normalPrice}</span>
                    <span class="new">$${game.salePrice}</span>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
