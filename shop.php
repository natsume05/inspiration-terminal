<?php
require 'includes/db.php';
$page_title = "星尘交易所";
$style = "community"; // 复用社区样式
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}

// 获取用户余额
$uid = $_SESSION['user_id'];
$u_res = $conn->query("SELECT stardust FROM users WHERE id=$uid");
$my_dust = $u_res->fetch_assoc()['stardust'];
?>

<div class="container" style="max-width: 1000px; margin-top: 40px;">
    
    <div style="background:linear-gradient(135deg, #1e2530, #111); padding:30px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; border:1px solid #333;">
        <div>
            <h2 style="margin:0; color:#fff;">🌌 星尘交易所</h2>
            <p style="color:#888; margin:5px 0 0 0;">消耗星尘，兑换虚空遗物。</p>
        </div>
        <div style="text-align:right;">
            <div style="font-size:0.9rem; color:#aaa;">当前余额</div>
            <div style="font-size:2rem; color:#f6d365; font-weight:bold;">✨ <?php echo number_format($my_dust); ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:20px; margin-top:30px;">
        <?php
        $sql = "SELECT * FROM shop_items";
        $result = $conn->query($sql);
        while($item = $result->fetch_assoc()):
        ?>
        <div class="side-card" style="text-align:center; padding:20px; transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
            <div style="font-size:3rem; margin-bottom:10px;"><?php echo $item['icon']; ?></div>
            <h3 style="color:#e6edf3; margin:5px 0;"><?php echo htmlspecialchars($item['name']); ?></h3>
            <p style="color:#666; font-size:0.85rem; height:40px;"><?php echo htmlspecialchars($item['description']); ?></p>
            
            <div style="margin-top:15px;">
                <button onclick="buyItem(<?php echo $item['id']; ?>, <?php echo $item['price']; ?>)" class="dream-btn small" style="width:100%;">
                    ✨ <?php echo $item['price']; ?> 兑换
                </button>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

</div>

<script>
function buyItem(itemId, price) {
    if(!confirm('确定消耗 ' + price + ' 星尘兑换此物品吗？')) return;

    fetch('api_buy.php?id=' + itemId)
        .then(res => res.json())
        .then(data => {
            alert(data.msg);
            if(data.status === 'success') location.reload();
        });
}
</script>

<?php include 'includes/footer.php'; ?>