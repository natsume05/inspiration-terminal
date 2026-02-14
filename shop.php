<?php
// shop.php - 星尘交易所 (修复崩溃版)
require 'includes/db.php';
$page_title = "星尘交易所";
$style = "shop"; 
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$uid = $_SESSION['user_id'];

// 获取基础数据
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
    .equip-btn { width: 100%; margin-top: 15px; padding: 8px; border-radius: 4px; cursor: pointer; border: 1px solid #66fcf1; background: transparent; color: #66fcf1; }
    .equip-btn.equipped { background: #66fcf1; color: #000; border-color: #66fcf1; }
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
            // 防崩溃检查 1
            $inv_res = $conn->query("SELECT item_id FROM user_inventory WHERE user_id=$uid");
            if ($inv_res) {
                while($r = $inv_res->fetch_assoc()) $my_items[] = $r['item_id'];
            }

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
                        <p><?php echo $item['description']; ?></p>
                    </div>
                    <div class="item-action">
                        <?php if($owned): ?>
                            <button class="buy-btn disabled" disabled>已拥有</button>
                        <?php else: ?>
                            <button onclick="buyItem(<?php echo $item['id']; ?>, <?php echo $item['price']; ?>)" class="buy-btn">
                                ✨ <?php echo $item['price']; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; else: echo "<p>商店暂无货物或查询出错。</p>"; endif; ?>
        </div>
    </div>

    <div id="view-inventory" class="shop-section fade-in" style="display:none;">
        <div class="shop-grid">
            <?php
            // 🚨 易崩溃点：如果 user_inventory 表没有 obtained_at 字段，这里会报错
            $sql_inv = "SELECT s.*, ui.is_equipped 
                        FROM user_inventory ui 
                        JOIN shop_items s ON ui.item_id = s.id 
                        WHERE ui.user_id = $uid 
                        ORDER BY ui.obtained_at DESC"; // 注意这个 obtained_at
            
            $res_inv = $conn->query($sql_inv);
            
            // 🛡️ 防弹衣：先检查查询是否成功，再检查行数
            if ($res_inv && $res_inv->num_rows > 0):
                while($item = $res_inv->fetch_assoc()):
            ?>
                <div class="item-card rarity-<?php echo $item['rarity']; ?>">
                    <div class="item-icon"><?php echo $item['icon']; ?></div>
                    <div class="item-info">
                        <h4><?php echo $item['name']; ?></h4>
                        <div class="item-type"><?php echo strtoupper($item['type']); ?></div>
                        <p style="font-size:0.8rem; color:#666;">
                            <?php echo $item['is_equipped'] ? '🟢 生效中' : '⚪ 未装备'; ?>
                        </p>
                    </div>
                    <div class="item-action">
                        <button onclick="toggleEquip(<?php echo $item['id']; ?>, this)" 
                                class="equip-btn <?php echo $item['is_equipped'] ? 'equipped' : ''; ?>">
                            <?php echo $item['is_equipped'] ? '卸下' : '装备'; ?>
                        </button>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div style="grid-column:1/-1; text-align:center; padding:50px; color:#666;">
                    <?php 
                    if (!$res_inv) {
                        echo "⚠️ 仓库数据读取失败。请检查数据库 user_inventory 表。<br>错误信息: " . $conn->error;
                    } else {
                        echo "仓库空空如也，快去交易所看看吧。";
                    }
                    ?>
                </div>
            <?php endif; ?>
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
// 弹窗控制
function closeModal() {
    document.getElementById('gacha-modal').classList.remove('show');
    location.reload(); 
}

function switchTab(tab) {
    document.getElementById('view-store').style.display = (tab === 'store') ? 'block' : 'none';
    document.getElementById('view-inventory').style.display = (tab === 'inventory') ? 'block' : 'none';
    document.getElementById('tab-store').className = (tab === 'store') ? 'tab-btn active' : 'tab-btn';
    document.getElementById('tab-inventory').className = (tab === 'inventory') ? 'tab-btn active' : 'tab-btn';
}

function buyItem(id, price) {
    if(!confirm('消耗 ' + price + ' 星尘兑换？')) return;
    const fd = new FormData(); fd.append('item_id', id);
    fetch('api_shop.php?action=buy', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            alert(d.msg);
            if(d.status === 'success') location.reload();
        })
        .catch(err => {
            alert('❌ 交易失败，请确保 api_shop.php 存在');
            console.error(err);
        });
}

function toggleEquip(id, btn) {
    const fd = new FormData(); fd.append('item_id', id);
    fetch('api_shop.php?action=toggle_equip', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if(d.status === 'success') location.reload(); 
            else alert(d.msg);
        })
        .catch(err => alert('❌ 操作失败'));
}

function playGacha() {
    const btn = document.getElementById('gacha-btn');
    btn.disabled = true; 
    btn.innerText = "祈祷中...";

    fetch('api_shop.php?action=gacha')
        .then(r => r.json())
        .then(d => {
            if(d.status === 'success') showReward(d.reward);
            else {
                alert(d.msg);
                btn.disabled = false;
                btn.innerText = "开始共鸣";
            }
        })
        .catch(err => {
            alert('❌ 抽奖失败');
            console.error(err);
            btn.disabled = false;
        });
}

function showReward(reward) {
    const content = document.getElementById('gacha-result');
    let html = '';
    if(reward.type === 'stardust') {
        html = `<div style="font-size:4rem;">✨</div><h3>获得星尘</h3><p style="color:#f6d365; font-size:2rem;">+${reward.val}</p>`;
    } else {
        html = `<div style="font-size:4rem;">${reward.icon}</div><h3 class="rarity-${reward.rarity}">获得：${reward.name}</h3><p>已存入仓库</p>`;
    }
    content.innerHTML = html;
    document.getElementById('gacha-modal').classList.add('show');
}
</script>
<?php include 'includes/footer.php'; ?>