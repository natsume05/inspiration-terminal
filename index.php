<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$page_title = "灵感传输终端";
$page_description = "灵感传输终端——集深空日志、提瓦特百宝箱与虚空梦语社区于一体的个人门户网站。";
$page_keywords = "灵感传输终端,个人网站,博客,工具箱,匿名社区";
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

<section class="logo-area" aria-label="站点介绍">
    <h1>
        <span class="g-blue">In</span><span class="g-red">spi</span><span class="g-yellow">ra</span><span class="g-blue">ti</span><span class="g-green">on</span>
        <span class="g-red">T</span>erminal
    </h1>
    <p class="subtitle">
        一个以「深空日志、实用工具箱、匿名社区」为核心的个人门户，记录灵感、连接思想、沉淀创作。
    </p>
</section>

<div class="card-container">
    <a href="blog.php" class="card card-blog card-hover" title="进入深空日志">
        <span class="icon"> 🚀 </span>
        <h2>深空日志</h2>
        <p>个人博客与观测笔记，记录技术思考、项目复盘与灵感片段。</p>
    </a>

    <a href="tools.php" class="card card-tools card-hover" title="打开提瓦特百宝箱">
        <span class="icon"> 🧩 </span>
        <h2>提瓦特百宝箱</h2>
        <p>聚合开发与效率工具：GitHub 开源猎手、Steam 史低监控等实用模块。</p>
    </a>

    <a href="community.php" class="card card-community card-hover" title="进入虚空梦语社区">
        <span class="icon"> 🦋 </span>
        <h2>虚空梦语</h2>
        <p>匿名交流与灵感记录社区，写下你的思考，也能点亮他人的回响。</p>
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
