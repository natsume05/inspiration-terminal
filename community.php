<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/level_system.php';

$uid = require_login();

$page_title = "虚空枢纽";
$page_description = "虚空枢纽——用户交流与灵感记录社区，匿名发帖、点赞评论、星尘交易所。";
$page_keywords = "社区,匿名交流,灵感记录";
$style = "lobby";

include __DIR__ . '/includes/header.php';

// 一次查询取全用户展示数据（含等级）。
$stmt = $conn->prepare("SELECT username, avatar, stardust, custom_title, exp FROM users WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<div class="container">
    <div class="user-dashboard fade-in">
        <div class="user-meta">
            <a href="profile.php">
                <img src="<?php echo e(get_avatar_url($user['avatar'])); ?>" class="avatar-large">
            </a>
            <div class="welcome-text">
                <h2>
                    欢迎回来，<?php echo e($user['username']); ?>
                    <a href="level_rules.php" style="font-size:0.8rem; color:#f6d365; text-decoration:none; border:1px solid #f6d365; padding:2px 6px; border-radius:10px;">
                        <?php echo get_rank_name($user['exp']); ?>
                    </a>
                </h2>
                <p><?php echo $user['custom_title'] ? e($user['custom_title']) : '探索者'; ?> | 信号连接稳定</p>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:10px; align-items:flex-end;">
            <div class="stardust-box">
                <div class="dust-label">星尘余额</div>
                <div class="dust-count" id="dash-stardust">✨ <?php echo number_format($user['stardust']); ?></div>
            </div>

            <button id="checkin-btn" onclick="doCheckin()" class="dream-btn small" style="background: linear-gradient(90deg, #00b09b, #96c93d);">
                📅 领取今日补给
            </button>
        </div>
    </div>

    <div class="lobby-grid">
        <a href="channel.php" class="lobby-card">
            <div class="card-icon">📡</div>
            <h3 class="card-title">深空频道</h3>
            <p class="card-desc">全频段广播接入。浏览思想碎片，发布观察日志。</p>
        </a>

        <a href="shop.php" class="lobby-card">
            <div class="card-icon">🌌</div>
            <h3 class="card-title">星尘交易所</h3>
            <p class="card-desc">消耗星尘兑换遗物与装扮。黑市大门已开启。</p>
        </a>

        <a href="games.php" class="lobby-card" style="border-color: #f6d365;">
            <div class="card-icon">🎲</div>
            <h3 class="card-title">娱乐终端</h3>
            <p class="card-desc">接入虚空牌局与解谜游戏。赢取星尘，或输掉一切。</p>
        </a>

        <a href="private_notes.php" class="lobby-card">
            <div class="card-icon">🔒</div>
            <h3 class="card-title">私密树洞</h3>
            <p class="card-desc">记录不想公开的秘密。只有你能听到的回响。</p>
        </a>

        <a href="feedback.php" class="lobby-card" style="border-color: #66fcf1;">
            <div class="card-icon">📶</div>
            <h3 class="card-title">信号塔</h3>
            <p class="card-desc">向舰桥发送反馈信标。提交BUG、建议或寻求援助。</p>
        </a>

        <a href="profile.php" class="lobby-card" style="border-color: #333;">
            <div class="card-icon">📂</div>
            <h3 class="card-title">个人档案</h3>
            <p class="card-desc">管理通讯ID、头像数据与历史记录。</p>
        </a>
    </div>
</div>

<script>
    function doCheckin() {
        const btn = document.getElementById('checkin-btn');
        btn.disabled = true;
        btn.innerHTML = '⏳ 通讯中...';

        fetch('api_checkin.php')
            .then(r => r.json())
            .then(d => {
                alert(d.msg);
                if (d.status === 'success') {
                    document.getElementById('dash-stardust').innerHTML = '✨ ' + new Intl.NumberFormat().format(d.new_balance);
                    btn.innerHTML = '✅ 已领取';
                    btn.style.background = '#333';
                } else {
                    btn.innerHTML = '📅 明日再来';
                    btn.style.background = '#333';
                }
            })
            .catch(() => {
                alert('❌ 信号中断');
                btn.disabled = false;
            });
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
