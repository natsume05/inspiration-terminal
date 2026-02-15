<?php
// shop.php - 星尘交易所 (无刷新装备 + 描述显示版)
require 'includes/db.php';
$page_title = "星尘交易所";
$style = "shop"; 
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$uid = $_SESSION['user_id'];
$me = $conn->query("SELECT stardust FROM users WHERE id=$uid")->fetch_assoc();
?>

<link rel="stylesheet" href="assets/css/shop.css?v=<?php echo time(); ?>">
<style>
    /* 补丁样式 */
    .modal { display: none !important; z-index: 9999; }
    .modal.show { display: flex !important; }
    .tab-nav { display: flex; gap: 20px; margin-bottom: 30px; border-bottom: 1px solid #333; padding-bottom: 10px; }
    .tab-btn { background: none; border: none; color: #666; font-size: 1.2rem; cursor: pointer; padding: 10px 20px; font-weight: bold; transition: 0.3s; }
    .tab-btn.active { color: #66fcf1; border-bottom: 3px solid #66fcf1; }
    .tab-btn:hover { color: #fff; }
    
    .equip-btn { width: 100%; margin-top: 10px; padding: 8px; border-radius: 4px; cursor: pointer; border: 1px solid #66fcf1; background: transparent; color: #66fcf1; transition: 0.2s;}
    .equip-btn.equipped { background: #66fcf1; color: #000; border-color: #66fcf1; }
    .equip-btn:hover { opacity: 0.8; }
    
    .item-desc { font-size: 0.8rem; color: #888; margin-top: 5px; height: 40px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
</style>

<div class="container shop-container">
    
    <div class="shop-header">
        <div class="balance-card">
            <div class="label">持有星尘</div>
            <div class="value" id="user-balance">✨ <?php echo number_format($me['stardust']); ?></div>
        </div>
        <div class="gacha-machine">
            <div class="gacha-info">
                <h3>🔮 虚空低语</h3>
                <p>每日一次，向深渊祈愿。</p>
            </div>
            <button onclick="playGacha()" id="gacha-btn" class="gacha-btn">开始共鸣</button>
        </div>
    </div>

    <div class="tab-nav">
        <button onclick="switchTab('store')" id="tab-store" class="tab-btn active">🏺 交易所</button>
        <button onclick="switchTab('inventory')" id="tab-inventory" class="tab-btn">🎒 虚空仓库</button>
    </div>

    <div id="view-store" class="shop-section fade-in">
        <div class="shop-grid">
            <?php
            $my_items = [];
            $inv_res = $conn->query("SELECT item_id FROM user_inventory WHERE user_id=$uid");
            if($inv_res) while($r = $inv_res->fetch_assoc()) $my_items[] = $r['item_id'];

            $sql = "SELECT * FROM shop_items WHERE is_forsale=1 ORDER BY price ASC";
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0):
                while($item = $result->fetch_assoc()):
                    $owned = in_array($item['id'], $my_items);
            ?>
                <div class="item-card rarity-<?php echo $item['rarity']; ?>">
                    <div class="item-icon"><?php echo $item['icon']; ?></div>
                    <div class="item-info">
                        <h4><?php echo $item['name']; ?></h4>
                        <div class="item-type"><?php echo strtoupper($item['type']); ?></div>
                        <p class="item-desc"><?php echo $item['description']; ?></p>
                    </div>
                    <div class="item-action">
                        <?php if($owned): ?>
                            <button class="buy-btn disabled" disabled>已拥有</button>
                        <?php else: ?>
                            <button onclick="buyItem(<?php echo $item['id']; ?>, <?php echo $item['price']; ?>)" class="buy-btn">✨ <?php echo $item['price']; ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; endif; ?>
        </div>
    </div>

    <div id="view-inventory" class="shop-section fade-in" style="display:none;">
        <div class="shop-grid">
            <?php
            $sql_inv = "SELECT s.*, ui.is_equipped 
                        FROM user_inventory ui 
                        JOIN shop_items s ON ui.item_id = s.id 
                        WHERE ui.user_id = $uid 
                        ORDER BY ui.obtained_at DESC";
            $res_inv = $conn->query($sql_inv);
            
            if ($res_inv && $res_inv->num_rows > 0):
                while($item = $res_inv->fetch_assoc()):
            ?>
                <div class="item-card rarity-<?php echo $item['rarity']; ?>">
                    <div class="item-icon"><?php echo $item['icon']; ?></div>
                    <div class="item-info">
                        <h4><?php echo $item['name']; ?></h4>
                        <div class="item-type"><?php echo strtoupper($item['type']); ?></div>
                        <p class="item-desc"><?php echo $item['description']; ?></p>
                    </div>
                    <div class="item-action">
                        <button onclick="toggleEquip(<?php echo $item['id']; ?>, this)" 
                                data-type="<?php echo $item['type']; ?>"
                                class="equip-btn <?php echo $item['is_equipped'] ? 'equipped' : ''; ?>">
                            <?php echo $item['is_equipped'] ? '已装备' : '装备'; ?>
                        </button>
                    </div>
                </div>
            <?php endwhile; else: echo "<div style='color:#666; grid-column:1/-1; text-align:center;'>仓库是空的。</div>"; endif; ?>
        </div>
    </div>

</div>

<div id="gacha-modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <div id="gacha-result"></div>
    </div>
</div>

<script>
function closeModal() { document.getElementById('gacha-modal').classList.remove('show'); location.reload(); }
function switchTab(tab) {
    document.getElementById('view-store').style.display = (tab === 'store') ? 'block' : 'none';
    document.getElementById('view-inventory').style.display = (tab === 'inventory') ? 'block' : 'none';
    document.getElementById('tab-store').className = (tab === 'store') ? 'tab-btn active' : 'tab-btn';
    document.getElementById('tab-inventory').className = (tab === 'inventory') ? 'tab-btn active' : 'tab-btn';
}

function buyItem(id, price) {
    if(!confirm('消耗 ' + price + ' 星尘兑换？')) return;
    const fd = new FormData(); fd.append('item_id', id);
    fetch('api_shop.php?action=buy', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ alert(d.msg); if(d.status==='success') location.reload(); });
}

// 🟢 核心改进：无刷新装备逻辑
function toggleEquip(id, btn) {
    const itemType = btn.getAttribute('data-type'); // 获取当前物品类型 (effect, avatar_frame...)
    const fd = new FormData(); fd.append('item_id', id);
    
    // 视觉反馈：变为处理中
    const originalText = btn.innerText;
    btn.innerText = '...';
    
    fetch('api_shop.php?action=toggle_equip', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if(d.status === 'success') {
                // 如果是“装备”操作 (new state = 1)
                if (d.is_equipped === 1) {
                    // 1. 找到所有同类型的按钮，重置为“装备”
                    const allBtns = document.querySelectorAll(`.equip-btn[data-type="${itemType}"]`);
                    allBtns.forEach(b => {
                        b.classList.remove('equipped');
                        b.innerText = '装备';
                    });
                    
                    // 2. 将当前按钮设为“已装备”
                    btn.classList.add('equipped');
                    btn.innerText = '已装备';
                } else {
                    // 如果是“卸下”操作
                    btn.classList.remove('equipped');
                    btn.innerText = '装备';
                }
            } else {
                alert(d.msg);
                btn.innerText = originalText;
            }
        })
        .catch(err => {
            alert('❌ 操作失败');
            btn.innerText = originalText;
        });
}

function playGacha() {
    document.getElementById('gacha-btn').disabled = true;
    fetch('api_shop.php?action=gacha').then(r=>r.json()).then(d=>{
        if(d.status==='success') showReward(d.reward);
        else alert(d.msg);
    });
}
function showReward(reward) {
    const content = document.getElementById('gacha-result');
    let html = '';
    if(reward.type === 'stardust') html = `<div style="font-size:4rem;">✨</div><h3>获得星尘</h3><p style="color:#f6d365; font-size:2rem;">+${reward.val}</p>`;
    else html = `<div style="font-size:4rem;">${reward.icon}</div><h3 class="rarity-${reward.rarity}">获得：${reward.name}</h3><p>已存入仓库</p>`;
    content.innerHTML = html;
    document.getElementById('gacha-modal').classList.add('show');
}
</script>
<?php include 'includes/footer.php'; ?>