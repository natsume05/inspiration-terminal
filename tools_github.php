<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

/**
 * 渲染一张 GitHub 项目卡片。
 */
function renderGitHubCard($repo)
{
    $stars = number_format($repo['stars']);
    echo '
    <a href="' . e($repo['url']) . '" target="_blank" class="gh-card">
        <div>
            <h3 style="color:#0969da; margin:0 0 8px 0; font-size:1rem;">📚 ' . e($repo['name']) . '</h3>
            <p style="color:#57606a; font-size:0.85rem; height:4.5em; overflow:hidden;">' . e($repo['description']) . '</p>
        </div>
        <div style="font-size:0.75rem; color:#57606a; border-top:1px dashed #eee; padding-top:10px; display:flex; justify-content:space-between;">
            <span>🟡 ' . e($repo['language']) . '</span>
            <span>⭐ ' . $stars . '</span>
        </div>
    </a>';
}

$page_title = "GitHub 探索";
$style = "tools_sub";

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="gh-header">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <h2 style="margin:0; display:flex; align-items:center; font-size:1.5rem;">
                <span style="font-size:2rem; margin-right:10px;">🐙</span> 开源探索 / Explorer
            </h2>

            <div style="position: relative; flex: 1; max-width: 500px;">
                <input type="text" id="gh-search-input" placeholder="🔍 搜索开源项目..."
                       style="width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 20px; outline: none;">
                <button onclick="searchGitHub()" style="position: absolute; right: 5px; top: 3px; background: #24292e; color: #fff; border: none; padding: 7px 15px; border-radius: 15px; cursor: pointer;">
                    搜索
                </button>
            </div>
        </div>

        <div style="display: flex; gap: 15px;">
            <button onclick="showTab('trending')" id="btn-trending" class="gh-tab active-tab">🔥 本周热榜</button>
            <button onclick="showTab('all_time')" id="btn-all_time" class="gh-tab">🏆 殿堂总榜</button>
            <button onclick="showTab('search')" id="btn-search" class="gh-tab" style="display:none;">🔍 搜索结果</button>
        </div>
    </div>

    <div id="list-trending" class="gh-grid-container">
        <?php
        $res = $conn->query("SELECT * FROM github_projects WHERE list_type = 'trending' ORDER BY stars DESC LIMIT 12");
        if ($res && $res->num_rows > 0) {
            while ($repo = $res->fetch_assoc()) renderGitHubCard($repo);
        } else {
            echo "<p style='color:#999; grid-column:1/-1; text-align:center;'>📡 暂无数据...</p>";
        }
        ?>
    </div>

    <div id="list-all_time" class="gh-grid-container" style="display: none;">
        <?php
        $res = $conn->query("SELECT * FROM github_projects WHERE list_type = 'all_time' ORDER BY stars DESC LIMIT 12");
        if ($res && $res->num_rows > 0) {
            while ($repo = $res->fetch_assoc()) renderGitHubCard($repo);
        }
        ?>
    </div>

    <div id="list-search" class="gh-grid-container" style="display: none;"></div>
    <div id="gh-loading" style="display:none; text-align:center; padding: 40px; color: #666;">🌀 正在连接星际网络...</div>

    <div style="text-align:center; margin-top:40px;">
        <a href="tools.php" class="btn-outline">🔙 返回百宝箱</a>
    </div>
</div>

<script>
function showTab(tabName) {
    document.querySelectorAll('.gh-grid-container').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.gh-tab').forEach(el => el.classList.remove('active-tab'));

    document.getElementById('list-' + tabName).style.display = 'grid';
    if (tabName === 'search') {
        const btn = document.getElementById('btn-search');
        btn.style.display = 'block';
        btn.classList.add('active-tab');
    } else {
        document.getElementById('btn-' + tabName).classList.add('active-tab');
    }
}

function searchGitHub() {
    const query = document.getElementById('gh-search-input').value.trim();
    if (!query) return;

    showTab('search');
    document.getElementById('list-search').innerHTML = '';
    document.getElementById('gh-loading').style.display = 'block';

    fetch('api_search_github.php?q=' + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            document.getElementById('gh-loading').style.display = 'none';
            const grid = document.getElementById('list-search');

            if (data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    grid.innerHTML += `
                        <a href="${item.html_url}" target="_blank" class="gh-card">
                            <div>
                                <h3 style="color:#0969da; margin:0 0 5px 0;">📚 ${item.full_name}</h3>
                                <p style="color:#666; font-size:0.85rem;">${item.description || '暂无描述'}</p>
                            </div>
                            <div style="margin-top:10px; font-size:0.8rem; color:#888;">
                                🟡 ${item.language || 'N/A'} &nbsp; ⭐ ${item.stargazers_count}
                            </div>
                        </a>`;
                });
            } else {
                grid.innerHTML = '<p style="text-align:center; width:100%;">未找到相关项目。</p>';
            }
        });
}

document.getElementById('gh-search-input').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') searchGitHub();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
