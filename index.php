<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$page_title = "灵感传输终端";
$style = "index";
$show_nav = false;

include __DIR__ . '/includes/header.php';

// 查询当前是否有一条生效中的广播。
$active_notice = null;
$stmt = $conn->prepare("SELECT * FROM announcements WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$stmt->execute();
$notice_res = $stmt->get_result();
if ($notice_res && $notice_res->num_rows > 0) {
    $active_notice = $notice_res->fetch_assoc();
}
$stmt->close();
?>

<?php if ($active_notice): ?>
<div id="global-modal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3>📡 灵感终端广播</h3>
            <span class="close-btn" onclick="closeNotice()">×</span>
        </div>
        <div class="modal-content">
            <?php echo $active_notice['content']; ?>
            <div style="margin-top: 15px; font-size: 0.85rem; color: #999;">
                发布于: <?php echo date('Y-m-d H:i', strtotime($active_notice['created_at'])); ?>
            </div>
        </div>
        <div class="modal-footer">
            <button class="confirm-btn" onclick="markAsRead(<?php echo (int) $active_notice['id']; ?>)">收到信号</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="logo-area">
    <h1>
        <span class="g-blue">In</span><span class="g-red">spi</span><span class="g-yellow">ra</span><span class="g-blue">ti</span><span class="g-green">on</span>
        <span class="g-red">T</span>erminal
    </h1>
    <p class="subtitle">
        这里是灵感的传输终端。连接宇宙深处的信号，聚合实用主义的工具，或是记录虚空中的低语。
    </p>
</div>

<div class="card-container">
    <a href="blog.php" class="card card-blog">
        <span class="icon"> 🚀 </span>
        <h2>深空日志</h2>
        <p>Admin的私人观测站。星际拓荒风格，记录思维的波形与宇宙的余晖。</p>
    </a>

    <a href="tools.php" class="card card-tools">
        <span class="icon"> 🧩 </span>
        <h2>提瓦特百宝箱</h2>
        <p>实用工具聚合。原神UI风格，分区收录Motrix、Everything等冒险家必备道具。</p>
    </a>

    <a href="community.php" class="card card-community">
        <span class="icon"> 🦋 </span>
        <h2>虚空梦语</h2>
        <p>用户交流与灵感记录。空洞骑士风格，在圣巢的石碑上刻下你的记忆（需登录）。</p>
    </a>
</div>

<script>
<?php if ($active_notice): ?>
document.addEventListener("DOMContentLoaded", function () {
    const noticeId = "<?php echo (int) $active_notice['id']; ?>";

    if (!localStorage.getItem('read_notice_' + noticeId)) {
        document.getElementById('global-modal').style.display = 'flex';
    }
});

function markAsRead(id) {
    localStorage.setItem('read_notice_' + id, 'true');
    closeNotice();
}

function closeNotice() {
    document.getElementById('global-modal').style.display = 'none';
}
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
