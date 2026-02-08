<?php
require 'includes/db.php';

// 页面配置
$page_title = "提瓦特百宝箱";
$style = "tools";
$show_nav = true;

include 'includes/header.php'; 

// --- 1. 获取所有工具并按分类整理 ---
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
        // 把工具放入对应的数组篮子里
        $cat = $row['category'];
        if(isset($tools_by_category[$cat])) {
            $tools_by_category[$cat][] = $row;
        } else {
            // 如果是新分类（比如以后加的），放入 tools 默认篮子
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

    <nav class="nav-bar">
        <button class="nav-btn active" onclick="showSection('game', this)">🎮 游戏 (Game)</button>
        <button class="nav-btn" onclick="showSection('tools', this)">🛠️ 工具 (Tools)</button>
        <button class="nav-btn" onclick="showSection('life', this)">🍵 生活 (Life)</button>
        <button class="nav-btn" onclick="showSection('impression', this)">🌌 印象 (Impression)</button>
    </nav>

    <?php 
    // 定义每个分区的 ID
    $sections = ['game', 'tools', 'life', 'impression'];
    
    foreach($sections as $sec): 
        // 只有第一个(game)默认显示 active 类
        $activeClass = ($sec == 'game') ? 'active' : '';
    ?>
        <div id="<?php echo $sec; ?>" class="section <?php echo $activeClass; ?>">
            
            <?php 
            // 检查这个分类下有没有工具
            if (!empty($tools_by_category[$sec])) {
                foreach($tools_by_category[$sec] as $item): 
            ?>
                <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" class="tool-card">
                    <div class="tool-icon"><?php echo $item['icon']; ?></div>
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

<script>
function showSection(sectionId, btnElement) {
    // A. 隐藏所有内容区域
    document.querySelectorAll('.section').forEach(el => {
        el.classList.remove('active');
    });
    
    // B. 移除所有按钮的激活状态
    document.querySelectorAll('.nav-btn').forEach(el => {
        el.classList.remove('active');
    });
    
    // C. 激活当前选中的内容和按钮
    document.getElementById(sectionId).classList.add('active');
    btnElement.classList.add('active');
}
</script>

<?php include 'includes/footer.php'; ?>