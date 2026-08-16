<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$uid = require_login();

$page_title = "信号塔";
$page_description = "信号塔——提交 Bug、功能建议或寻求舰桥协助。";
$page_keywords = "反馈,建议,帮助";
$style = "community";

include __DIR__ . '/includes/header.php';

// --- 处理提交 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback'])) {
    $stmt = $conn->prepare("SELECT id FROM feedback WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 MINUTE");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $recent = $stmt->get_result();

    if ($recent->num_rows > 0) {
        $error = "⏳ 频段拥堵，请稍候再发送信号...";
    } else {
        $type = post_text('type');
        $message = post_text('message');

        if ($message === '') {
            $error = "❌ 信号内容不能为空。";
        } else {
            $stmt = $conn->prepare("INSERT INTO feedback (user_id, type, content) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $uid, $type, $message);
            if ($stmt->execute()) {
                $success = "📡 信号已发射！请留意下方的通讯记录。";
            } else {
                $error = "❌ 发射塔故障，请稍后再试。";
            }
        }
    }
    $stmt->close();
}

// --- 获取历史反馈 ---
$stmt = $conn->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $uid);
$stmt->execute();
$history_res = $stmt->get_result();
?>

<style>
.tower-layout { display: grid; grid-template-columns: 1fr 1.2fr; gap: 30px; margin-top: 40px; }
.feedback-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 25px; }
.history-item {
    background: #0d1117; border: 1px solid #30363d; border-radius: 8px;
    padding: 15px; margin-bottom: 15px; position: relative;
}
.history-item.replied { border-color: #238636; box-shadow: 0 0 10px rgba(35, 134, 54, 0.1); }
.f-tag { font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; display: inline-block; margin-bottom: 8px; }
.tag-bug { background: rgba(255, 77, 79, 0.2); color: #ff4d4f; border: 1px solid #ff4d4f; }
.tag-feature { background: rgba(250, 173, 20, 0.2); color: #faad14; border: 1px solid #faad14; }
.tag-help { background: rgba(22, 207, 241, 0.2); color: #16cff1; border: 1px solid #16cff1; }
.admin-reply {
    margin-top: 10px; padding-top: 10px; border-top: 1px dashed #30363d;
    color: #238636; font-size: 0.9rem;
}
.status-icon { position: absolute; top: 15px; right: 15px; font-size: 1.2rem; }
@media (max-width: 768px) { .tower-layout { grid-template-columns: 1fr; } }
</style>

<div class="container" style="max-width: 1000px;">
    <div style="margin-top: 30px; margin-bottom: 20px;">
        <a href="community.php" style="color: #888; text-decoration: none;">&lt; 返回大厅</a>
    </div>

    <div class="tower-layout">
        <div class="feedback-card" style="border-top: 3px solid #66fcf1;">
            <h2 style="color: #e6edf3; margin-top: 0;">📶 发射信号</h2>
            <p style="color: #888; font-size: 0.9rem; margin-bottom: 20px;">
                遇到了 BUG？有绝妙的功能建议？或者需要舰桥的协助？在这里发送，我们会收到的。
            </p>

            <?php if (isset($success)) echo "<div style='background:rgba(46,160,67,0.2); color:#3fb950; padding:10px; border-radius:6px; margin-bottom:15px;'>$success</div>"; ?>
            <?php if (isset($error)) echo "<div style='background:rgba(255,77,79,0.2); color:#ff7875; padding:10px; border-radius:6px; margin-bottom:15px;'>$error</div>"; ?>

            <form method="POST">
                <label style="color:#ccc; display:block; margin-bottom:8px; font-size:0.9rem;">信号类型</label>
                <select name="type" style="width:100%; padding:12px; background:#0d1117; border:1px solid #30363d; color:#fff; border-radius:6px; margin-bottom:20px; outline:none;">
                    <option value="bug">🐛 报告漏洞 (BUG)</option>
                    <option value="feature">💡 功能建议 (Idea)</option>
                    <option value="help">❓ 寻求协助 (Help)</option>
                </select>

                <label style="color:#ccc; display:block; margin-bottom:8px; font-size:0.9rem;">详细情报</label>
                <textarea name="message" rows="8" required placeholder="请详细描述..." style="width:100%; padding:12px; background:#0d1117; border:1px solid #30363d; color:#fff; border-radius:6px; margin-bottom:20px; box-sizing:border-box; outline:none; resize:vertical;"></textarea>

                <button type="submit" name="feedback" class="dream-btn full-width">🚀 发射信号</button>
            </form>
        </div>

        <div>
            <h3 style="color: #ccc; margin-top: 0; margin-bottom: 20px;">📜 通讯日志</h3>

            <?php if ($history_res && $history_res->num_rows > 0): ?>
                <?php while ($log = $history_res->fetch_assoc()):
                    $tagClass = 'tag-help';
                    $tagName = '提问';
                    if ($log['type'] === 'bug') { $tagClass = 'tag-bug'; $tagName = '漏洞'; }
                    if ($log['type'] === 'feature') { $tagClass = 'tag-feature'; $tagName = '建议'; }
                    $isReplied = !empty($log['admin_reply']);
                ?>
                <div class="history-item <?php echo $isReplied ? 'replied' : ''; ?>">
                    <div class="status-icon" title="<?php echo $isReplied ? '舰桥已回复' : '信号传输中...'; ?>">
                        <?php echo $isReplied ? '✅' : '📡'; ?>
                    </div>

                    <span class="f-tag <?php echo $tagClass; ?>"><?php echo $tagName; ?></span>
                    <span style="color: #666; font-size: 0.8rem; margin-left: 10px;">
                        <?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?>
                    </span>

                    <div style="color: #ccc; margin-top: 8px; line-height: 1.5; white-space: pre-wrap;"><?php echo e($log['content']); ?></div>

                    <?php if ($isReplied): ?>
                        <div class="admin-reply">
                            <strong>👨‍🚀 舰桥回复：</strong><br>
                            <?php echo e($log['admin_reply']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center; color:#666; padding:40px; border:2px dashed #30363d; border-radius:12px;">
                    暂无通讯记录
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
