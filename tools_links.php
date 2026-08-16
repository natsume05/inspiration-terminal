<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$page_title = "星际导航终端";
$style = "tools_sub";

include __DIR__ . '/includes/header.php';

$categories = ['game', 'tools', 'life', 'impression'];
$tools_by_category = array_fill_keys($categories, []);

$res = $conn->query("SELECT * FROM tools ORDER BY id DESC");
while ($res && $row = $res->fetch_assoc()) {
    $category = isset($tools_by_category[$row['category']]) ? $row['category'] : 'tools';
    $tools_by_category[$category][] = $row;
}
?>

<div class="container">
    <div style="text-align:center; margin-bottom:40px;">
        <h1>🌌 星际导航终端</h1>
        <p style="color:#888;">收录常用开发、设计与生活工具链接。</p>
        <input type="text" id="link-search" placeholder="🔍 搜索工具..."
               style="padding:10px 20px; width:60%; border-radius:25px; border:1px solid #ddd; outline:none; margin-top:20px;">
    </div>

    <div class="nav-bar">
        <button class="nav-btn active" onclick="showSection('game', this)">🎮 游戏</button>
        <button class="nav-btn" onclick="showSection('tools', this)">🛠️ 工具</button>
        <button class="nav-btn" onclick="showSection('life', this)">🍵 生活</button>
        <button class="nav-btn" onclick="showSection('impression', this)">🌌 印象</button>
    </div>

    <?php foreach ($categories as $section): $active = ($section === 'game') ? 'active' : ''; ?>
    <div id="<?php echo $section; ?>" class="section <?php echo $active; ?>">
        <?php foreach ($tools_by_category[$section] as $item): ?>
        <a href="<?php echo e($item['url']); ?>" target="_blank" class="link-card">
            <div class="link-icon">🔗</div>
            <div class="link-info">
                <h3><?php echo e($item['title']); ?></h3>
                <p><?php echo e($item['description']); ?></p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div style="text-align:center; margin-top:50px;">
        <a href="tools.php" class="btn-outline">🔙 返回百宝箱</a>
    </div>
</div>

<script>
function showSection(id, btn) {
    document.querySelectorAll('.section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}

document.getElementById('link-search').addEventListener('input', function (e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.link-card').forEach(card => {
        const text = card.innerText.toLowerCase();
        card.style.display = text.includes(term) ? 'flex' : 'none';
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
