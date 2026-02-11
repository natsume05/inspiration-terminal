<?php
require 'includes/db.php';

// 页面配置
$page_title = "提瓦特百宝箱";
$style = "tools";
$show_nav = true;

include 'includes/header.php'; 

// --- 1. 获取所有普通工具并按分类整理 ---
$tools_by_category = [
    'game' => [],
    'tools' => [],
    'life' => [],
    'impression' => []
];

$sql = "SELECT * FROM tools ORDER BY id DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $cat = $row['category'];
        if(isset($tools_by_category[$cat])) {
            $tools_by_category[$cat][] = $row;
        } else {
            $tools_by_category['tools'][] = $row;
        }
    }
}
?>

<div class="container" style="max-width: 1200px; margin-top: 30px;">
    
    <header style="text-align:center; border:none; margin-top:0;">
        <h1 style="margin-bottom:10px;">💎 提瓦特百宝箱</h1>
        <p class="intro-text">
            “旅行者，这里收录了来自异世界的智慧结晶。无论是修改法则的禁忌之术，还是记录万象的虚空终端，都已为你整理归档。”
        </p>
    </header>

    <div class="container" style="max-width: 1200px; margin: 40px auto 100px auto; padding: 0 20px;">
        
        <div style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 30px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h2 style="color: #333; font-weight: 700; margin: 0; display: flex; align-items: center;">
                    <span style="background: #24292e; color: #fff; padding: 5px 10px; border-radius: 6px; margin-right: 10px; font-size: 1.2rem;">
                        🐙 GitHub
                    </span>
                    开源探索 / Explorer
                </h2>
                
                <div style="position: relative; flex: 1; max-width: 500px;">
                    <input type="text" id="gh-search-input" placeholder="🔍 搜索开源项目 (如: deepseek, adb, c盘清理...)" 
                           style="width: 100%; padding: 12px 20px; border: 2px solid #eee; border-radius: 25px; outline: none; transition: 0.3s; font-size: 0.95rem;">
                    <button onclick="searchGitHub()" style="position: absolute; right: 5px; top: 5px; background: #24292e; color: #fff; border: none; padding: 8px 20px; border-radius: 20px; cursor: pointer;">
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
            // 如果你的数据库还没加 list_type 字段，这里的 WHERE 可能要去掉或调整
            // 假设你已经按之前的教程加了 list_type='trending'
            $sql_trend = "SELECT * FROM github_projects WHERE list_type='trending' ORDER BY stars DESC LIMIT 8";
            $res_trend = $conn->query($sql_trend);
            if ($res_trend && $res_trend->num_rows > 0) {
                while ($repo = $res_trend->fetch_assoc()) {
                    renderGitHubCard($repo); // 调用底部的函数生成卡片
                }
            } else {
                echo "<p style='color:#999; grid-column:1/-1; text-align:center;'>📡 暂无热榜数据，请运行 fetch_github.php 更新...</p>";
            }
            ?>
        </div>

        <div id="list-all_time" class="gh-grid-container" style="display: none;">
            <?php
            $sql_all = "SELECT * FROM github_projects WHERE list_type='all_time' ORDER BY stars DESC LIMIT 8";
            $res_all = $conn->query($sql_all);
            if ($res_all && $res_all->num_rows > 0) {
                while ($repo = $res_all->fetch_assoc()) {
                    renderGitHubCard($repo);
                }
            } else {
                echo "<p style='color:#999; grid-column:1/-1; text-align:center;'>📡 暂无总榜数据，请运行 fetch_github.php 更新...</p>";
            }
            ?>
        </div>

        <div id="list-search" class="gh-grid-container" style="display: none;">
            </div>

        <div id="gh-loading" style="display:none; text-align:center; padding: 40px; color: #666;">
            🌀 正在连接 GitHub 星际网络...
        </div>

    </div>
    <nav class="nav-bar">
        <button class="nav-btn active" onclick="showSection('game', this)">🎮 游戏 (Game)</button>
        <button class="nav-btn" onclick="showSection('tools', this)">🛠️ 工具 (Tools)</button>
        <button class="nav-btn" onclick="showSection('life', this)">🍵 生活 (Life)</button>
        <button class="nav-btn" onclick="showSection('impression', this)">🌌 印象 (Impression)</button>
    </nav>

    <div style="text-align: center; margin: 20px 0;">
        <input type="text" id="elemental-sight" placeholder="👁️ 开启元素视野 (搜索工具...)" 
            style="padding: 10px 20px; width: 60%; border-radius: 25px; border: 2px solid #ddd; outline: none; transition: 0.3s;">
    </div>

    <?php 
    $sections = ['game', 'tools', 'life', 'impression'];
    foreach($sections as $sec): 
        $activeClass = ($sec == 'game') ? 'active' : '';
    ?>
        <div id="<?php echo $sec; ?>" class="section <?php echo $activeClass; ?>">
            <?php 
            if (!empty($tools_by_category[$sec])) {
                foreach($tools_by_category[$sec] as $item): 
            ?>
                <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" class="tool-card">
                    <img src="https://www.google.com/s2/favicons?domain=<?php echo parse_url($item['url'], PHP_URL_HOST); ?>&sz=128" class="tool-icon-img" alt="icon" onerror="this.src='assets/images/default_icon.png'">
                    <div class="tool-info">
                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p><?php echo htmlspecialchars($item['description']); ?></p>
                    </div>
                </a>
            <?php 
                endforeach; 
            } else {
                echo "<p style='color:#999'>暂无收录...</p>";
            }
            ?>
        </div>
    <?php endforeach; ?>

</div>

<style>
    .gh-tab { background: none; border: none; font-weight: 600; color: #666; cursor: pointer; padding: 5px 10px; border-bottom: 2px solid transparent; }
    .gh-tab:hover { color: #0969da; }
    .active-tab { color: #0969da; border-bottom: 2px solid #0969da; }
    
    /* 统一的网格样式 */
    .gh-grid-container {
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
        gap: 20px;
    }
</style>

<script>
// --- 1. GitHub Tab 切换功能 (修复点) ---
function showTab(tabName) {
    // A. 隐藏所有列表
    document.getElementById('list-trending').style.display = 'none';
    document.getElementById('list-all_time').style.display = 'none';
    document.getElementById('list-search').style.display = 'none';

    // B. 移除所有按钮激活状态
    document.getElementById('btn-trending').classList.remove('active-tab');
    document.getElementById('btn-all_time').classList.remove('active-tab');
    if(document.getElementById('btn-search')) {
        document.getElementById('btn-search').classList.remove('active-tab');
    }

    // C. 显示选中的列表和激活按钮
    if (tabName === 'trending') {
        document.getElementById('list-trending').style.display = 'grid';
        document.getElementById('btn-trending').classList.add('active-tab');
        document.getElementById('btn-search').style.display = 'none'; // 隐藏搜索Tab按钮
    } else if (tabName === 'all_time') {
        document.getElementById('list-all_time').style.display = 'grid';
        document.getElementById('btn-all_time').classList.add('active-tab');
        document.getElementById('btn-search').style.display = 'none'; // 隐藏搜索Tab按钮
    } else if (tabName === 'search') {
        document.getElementById('list-search').style.display = 'grid';
        document.getElementById('btn-search').style.display = 'block';
        document.getElementById('btn-search').classList.add('active-tab');
    }
}

// --- 2. GitHub 搜索功能 ---
function searchGitHub() {
    const query = document.getElementById('gh-search-input').value;
    if(!query) return;

    // 切换到搜索 Tab
    showTab('search'); 
    
    // 显示 Loading，清空旧结果
    document.getElementById('list-search').innerHTML = ''; 
    document.getElementById('gh-loading').style.display = 'block';

    fetch('api_search_github.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            document.getElementById('gh-loading').style.display = 'none';
            const grid = document.getElementById('list-search');
            
            if(data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    // 生成卡片 HTML
                    const card = `
                        <a href="${item.html_url}" target="_blank" style="text-decoration: none; background: #fff; border: 1px solid #d0d7de; border-radius: 6px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; height: 100%; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 15px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.05)'">
                            <div>
                                <h3 style="color: #0969da; margin: 0 0 8px 0; font-size: 1rem;">📚 ${item.full_name}</h3>
                                <p style="color: #57606a; font-size: 0.85rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                                    ${item.description || '暂无描述'}
                                </p>
                            </div>
                            <div style="font-size: 0.75rem; color: #57606a; margin-top: 15px; display:flex; justify-content:space-between;">
                                <span>🟡 ${item.language || 'Unknown'}</span>
                                <span>⭐ ${item.stargazers_count}</span>
                            </div>
                        </a>
                    `;
                    grid.innerHTML += card;
                });
            } else {
                grid.innerHTML = '<p style="text-align:center; color:#666; width:100%; grid-column:1/-1;">👾 未搜寻到相关信号...</p>';
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('gh-loading').innerHTML = '❌ 通讯中断';
        });
}

// 绑定回车搜索
document.getElementById('gh-search-input').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') searchGitHub();
});

// --- 3. 普通工具切换功能 ---
function showSection(sectionId, btnElement) {
    document.querySelectorAll('.section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
    btnElement.classList.add('active');
}

// --- 4. 元素视野 ---
document.getElementById('elemental-sight').addEventListener('input', function(e) {
    let term = e.target.value.toLowerCase();
    let cards = document.querySelectorAll('.tool-card');
    cards.forEach(card => {
        let title = card.querySelector('h3').innerText.toLowerCase();
        let desc = card.querySelector('p').innerText.toLowerCase();
        if (title.includes(term) || desc.includes(term)) {
            card.style.display = 'flex'; 
            card.style.animation = 'fadeIn 0.5s';
        } else {
            card.style.display = 'none';
        }
    });
});
</script>

<?php 
// 辅助函数：生成 GitHub 卡片 HTML (避免代码重复)
function renderGitHubCard($repo) {
    $desc = !empty($repo['description']) ? $repo['description'] : '暂无描述...';
    $lang = !empty($repo['language']) ? $repo['language'] : 'Unknown';
    $stars = number_format($repo['stars']);
    ?>
    <a href="<?php echo $repo['url']; ?>" target="_blank" class="gh-card" style="
        text-decoration: none;
        background: #fff;
        border: 1px solid #d0d7de;
        border-radius: 6px;
        padding: 16px;
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        position: relative;
    " onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.1)'" 
       onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.05)'">
        
        <div>
            <h3 style="color: #0969da; margin: 0 0 8px 0; font-size: 1rem; font-weight: 600; word-break: break-all;">
                📚 <?php echo htmlspecialchars($repo['name']); ?>
            </h3>
            <p style="color: #57606a; font-size: 0.85rem; line-height: 1.5; margin: 0 0 15px 0; 
                      display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                <?php echo htmlspecialchars($desc); ?>
            </p>
        </div>
        
        <div style="font-size: 0.75rem; color: #57606a; border-top: 1px dashed #eee; padding-top: 10px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display:flex; align-items:center;">
                <span style="width:10px; height:10px; background:#f1e05a; border-radius:50%; display:inline-block; margin-right:6px;"></span>
                <?php echo htmlspecialchars($lang); ?>
            </div>
            <div style="font-weight: 600;">
                ⭐ <?php echo $stars; ?>
            </div>
        </div>
    </a>
    <?php
}
include 'includes/footer.php'; 
?>