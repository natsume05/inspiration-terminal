<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/image_helper.php';
require_once __DIR__ . '/includes/item_loader.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/level_system.php';

$uid = require_login();

// --- 处理发帖 ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_post'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        exit('🛑 信号校验失败');
    }

    $content = post_text('content');
    $author = $_SESSION['username'];
    $tag = post_text('tag', 'daily');

    $image_path = null;
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === 0) {
        $base_name = "post_" . time() . "_" . rand(100, 999);
        $processed_name = upload_and_compress_webp($_FILES['post_image']['tmp_name'], "assets/uploads/community/" . $base_name, 800, 75);
        if ($processed_name) {
            $image_path = $processed_name;
        }
    }

    $stmt = $conn->prepare("INSERT INTO posts (author, content, image, tag) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $author, $content, $image_path, $tag);
    if ($stmt->execute()) {
        add_exp($conn, $uid, 10);
    }
    $stmt->close();

    redirect("channel.php?tag=" . urlencode($tag));
}

// --- 查询逻辑 ---
$filter = get_text('tag', 'all');

$sql = "SELECT p.*, u.id as author_id, u.username, u.avatar, u.custom_title, u.exp,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as is_liked
        FROM posts p
        LEFT JOIN users u ON p.author = u.username ";

$params = [$uid];
$types = 'i';

if ($filter !== 'all') {
    $sql .= " WHERE p.tag = ? ";
    $params[] = $filter;
    $types .= 's';
}
$sql .= " ORDER BY p.created_at DESC LIMIT 50";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$channels = [
    'all'   => ['icon' => '🌎', 'name' => '全频段'],
    'daily' => ['icon' => '☕', 'name' => '日常吐槽'],
    'game'  => ['icon' => '🎮', 'name' => '游戏圣堂'],
    'tech'  => ['icon' => '💻', 'name' => '代码深空'],
    'void'  => ['icon' => '🕳️', 'name' => '虚空回响'],
];

$page_title = "深空频道";
$style = "community";

include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="assets/css/effects.css?v=<?php echo time(); ?>">

<style>
.action-btn { cursor: pointer; user-select: none; display: flex; align-items: center; gap: 5px; color: #888; transition: 0.2s; }
.action-btn .icon { font-size: 1.2rem; line-height: 1; }
.action-btn:hover { color: #66fcf1; }
.action-btn.liked { color: #ff4d4f; }
.action-btn.liked .icon { transform: scale(1.1); }
.comment-section { background: rgba(0,0,0,0.2); border-top: 1px solid #30363d; padding: 15px; margin-top: 15px; display: none; }
.comment-item { display: flex; gap: 10px; margin-bottom: 10px; border-bottom: 1px dashed #333; padding-bottom: 5px; }
.c-avatar { width: 30px; height: 30px; border-radius: 50%; }
.c-input { flex: 1; background: #0d1117; border: 1px solid #30363d; color: #fff; padding: 8px; border-radius: 20px; outline: none; }
.c-submit { background: #238636; color: #fff; border: none; padding: 0 15px; border-radius: 20px; cursor: pointer; }
</style>

<div class="container community-layout">
    <aside class="sidebar-left">
        <a href="community.php" class="dream-btn small full-width" style="margin-bottom:20px; background:#333; color:#aaa!important;">⬅️ 返回大厅</a>
        <div class="side-card nav-card">
            <h4>📡 频道调频</h4>
            <nav class="channel-nav">
                <?php foreach ($channels as $key => $channel): $active = ($filter === $key) ? 'active' : ''; ?>
                <a href="channel.php?tag=<?php echo $key; ?>" class="channel-item <?php echo $active; ?>">
                    <span class="c-icon"><?php echo $channel['icon']; ?></span><span class="c-name"><?php echo $channel['name']; ?></span>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </aside>

    <main class="feed-stream">
        <div class="post-box">
            <form action="channel.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <textarea id="post-content" name="content" required placeholder="在此刻下你的思想... (代码请用 ``` 包裹)"></textarea>
                <div class="post-toolbar">
                    <div class="tools-left">
                        <select name="tag" class="channel-select">
                            <option value="daily">☕ 日常吐槽</option>
                            <option value="game">🎮 游戏圣堂</option>
                            <option value="tech">💻 代码深空</option>
                            <option value="void">🕳️ 虚空回响</option>
                        </select>
                        <button type="button" class="tool-btn" onclick="toggleEmojiPanel()">😊表情</button>
                        <label class="tool-btn">📷图片 <input type="file" name="post_image" accept="image/*" style="display:none;" onchange="showFileName(this)"></label>
                        <span id="file-name" style="font-size:0.8rem; color:#666;"></span>
                    </div>
                    <button type="submit" name="submit_post" class="dream-btn small">✨ 发送</button>
                </div>
                <div id="emoji-panel" class="emoji-panel" style="display:none;">
                    <span onclick="insertEmoji('[s:smile]')">🙂</span>
                    <span onclick="insertEmoji('[s:joy]')">😂</span>
                    <span onclick="insertEmoji('[s:lol]')">🤣</span>
                    <span onclick="insertEmoji('[s:love]')">😍</span>
                    <span onclick="insertEmoji('[s:cool]')">😎</span>
                    <span onclick="insertEmoji('[s:cry]')">😭</span>
                    <span onclick="insertEmoji('[s:angry]')">😡</span>
                    <span onclick="insertEmoji('[s:clown]')">🤡</span>
                    <span onclick="insertEmoji('[s:thumbsup]')">👍</span>
                    <span onclick="insertEmoji('[s:ok]')">👌</span>
                    <span onclick="insertEmoji('[s:heart]')">❤️</span>
                    <span onclick="insertEmoji('[s:broken]')">💔</span>
                    <span onclick="insertEmoji('[s:ghost]')">👻</span>
                    <span onclick="insertEmoji('[s:alien]')">👽</span>
                    <span onclick="insertEmoji('[s:robot]')">🤖</span>
                    <span onclick="insertEmoji('[s:fire]')">🔥</span>
                    <span onclick="insertEmoji('[s:star]')">✨</span>
                    <span onclick="insertEmoji('[s:rocket]')">🚀</span>
                    <span onclick="insertEmoji('[s:moon]')">🌙</span>
                    <span onclick="insertEmoji('[s:game]')">🎮</span>
                    <span onclick="insertEmoji('[s:cat]')">🐱</span>
                    <span onclick="insertEmoji('[s:dog]')">🐶</span>
                    <span onclick="insertEmoji('[s:fox]')">🦊</span>
                    <span onclick="insertEmoji('[s:bug]')">🐞</span>
                    <span onclick="insertEmoji('[s:paimon]')" title="应急食品">🥘</span>
                    <span onclick="insertEmoji('[s:primogem]')" title="原石">💎</span>
                    <span onclick="insertEmoji('[s:gwent]')" title="昆特牌">🃏</span>
                    <span onclick="insertEmoji('[s:sword]')" title="剑">⚔️</span>
                    <span onclick="insertEmoji('[s:objection]')" title="异议">👉</span>
                    <span onclick="insertEmoji('[s:tree]')" title="灵树">🌳</span>
                    <span onclick="insertEmoji('[s:dragon]')" title="龙">🐉</span>
                </div>
            </form>
        </div>

        <div class="posts-list">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()):
                    $author_id = !empty($row['author_id']) ? (int) $row['author_id'] : 0;
                    $decor = get_user_decorations($conn, $author_id);
                ?>
                <div class="post-card fade-in" id="post-<?php echo (int) $row['id']; ?>">
                    <div class="post-header">
                        <div class="author-box">
                            <a href="profile.php?id=<?php echo $author_id; ?>" style="text-decoration: none;">
                                <div class="avatar-wrapper <?php echo $decor['avatar_class']; ?>" style="border-radius:50%; display:inline-block; padding:2px; transition: transform 0.2s;">
                                    <img src="<?php echo e(get_avatar_url($row['avatar'])); ?>" class="avatar-small">
                                </div>
                            </a>

                            <div class="author-info">
                                <a href="profile.php?id=<?php echo $author_id; ?>" style="text-decoration: none;">
                                    <span class="username <?php echo $decor['name_class']; ?>">
                                        <?php echo e($row['username'] ?? '虚空游侠'); ?>
                                    </span>
                                </a>

                                <?php if (!empty($decor['badge_icon'])): ?>
                                    <span title="徽章" style="cursor:help; margin-left:5px;"><?php echo $decor['badge_icon']; ?></span>
                                <?php endif; ?>

                                <span style="font-size:0.7rem; background:#333; color:#aaa; padding:1px 5px; border-radius:4px; margin-left:5px; border:1px solid #444;">
                                    <?php echo get_rank_name($row['exp'] ?? 0); ?>
                                </span>

                                <?php if (!empty($row['custom_title'])): ?>
                                    <span class="custom-title-badge" style="background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); color: #333; font-weight: bold; font-size: 0.75rem; padding: 1px 6px; border-radius: 12px; margin-left: 5px;">
                                        <?php echo e($row['custom_title']); ?>
                                    </span>
                                <?php endif; ?>

                                <span class="tag-badge"><?php echo $channels[$row['tag']]['icon'] ?? '📝'; ?></span>
                            </div>
                        </div>
                        <span class="post-time"><?php echo date('m-d H:i', strtotime($row['created_at'])); ?></span>
                    </div>

                    <textarea class="raw-markdown" style="display:none;"><?php echo e($row['content']); ?></textarea>
                    <div class="post-content markdown-body"></div>
                    <?php if (!empty($row['image'])): ?><div class="post-image"><img src="assets/uploads/community/<?php echo e($row['image']); ?>" onclick="openLightbox(this.src)"></div><?php endif; ?>

                    <div class="post-footer">
                        <div style="display:flex; gap:20px;">
                            <span class="action-btn <?php echo ($row['is_liked'] > 0) ? 'liked' : ''; ?>" onclick="toggleLike(<?php echo (int) $row['id']; ?>, this)">
                                <span class="icon"><?php echo ($row['is_liked'] > 0) ? '❤️' : '🤍'; ?></span>
                                <span class="count"><?php echo (int) $row['like_count']; ?></span>
                            </span>
                            <span class="action-btn" onclick="toggleComments(<?php echo (int) $row['id']; ?>)"><span class="icon">💬</span> 评论</span>
                            <span class="action-btn" onclick="sharePost(<?php echo (int) $row['id']; ?>)"><span class="icon">🔗</span> 分享</span>
                        </div>
                        <?php if (is_admin()): ?>
                            <form method="POST" action="delete_post.php" onsubmit="return confirm('是否确认删除这条帖子？');" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="post_id" value="<?php echo (int) $row['id']; ?>">
                                <button class="tool-btn" style="color:red;">🗑️</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div id="comment-box-<?php echo (int) $row['id']; ?>" class="comment-section">
                        <div class="comment-list" id="comment-list-<?php echo (int) $row['id']; ?>"></div>
                        <div class="comment-input-box">
                            <input type="text" id="comment-input-<?php echo (int) $row['id']; ?>" class="c-input" placeholder="输入评论..." onkeypress="if(event.key==='Enter') submitComment(<?php echo (int) $row['id']; ?>)">
                            <button onclick="submitComment(<?php echo (int) $row['id']; ?>)" class="c-submit">发送</button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style='text-align:center; padding:50px; color:#666;'>暂无信号...</div>
            <?php endif; ?>
        </div>
    </main>

    <aside class="sidebar-right">
        <div class="side-card notice-corner">
            <h4>📢 虚空广播</h4>
            <p style="font-size:0.85rem; color:#888;">请遵守星际公约。</p>
        </div>
    </aside>
</div>

<div id="lightbox" onclick="this.style.display='none'" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:999; justify-content:center; align-items:center;"><img id="lightbox-img" style="max-width:90%; max-height:90%;"></div>

<script>
function toggleLike(postId, btn) {
    const icon = btn.querySelector('.icon');
    const count = btn.querySelector('.count');
    const liked = btn.classList.contains('liked');

    if (liked) {
        btn.classList.remove('liked');
        icon.innerText = '🤍';
        count.innerText = Math.max(0, parseInt(count.innerText) - 1);
    } else {
        btn.classList.add('liked');
        icon.innerText = '❤️';
        count.innerText = parseInt(count.innerText) + 1;
    }

    fetch('api_like.php?post_id=' + postId)
        .then(r => r.json())
        .then(d => {
            if (!d.success) alert(d.message);
            else if (d.drop) alert(d.drop.msg + "\n+" + d.drop.val + "✨");
        });
}

function toggleComments(id) {
    const box = document.getElementById('comment-box-' + id);
    if (box.style.display === 'none') {
        box.style.display = 'block';
        loadComments(id);
    } else {
        box.style.display = 'none';
    }
}

function loadComments(id) {
    fetch('api_comment.php?action=list&post_id=' + id)
        .then(r => r.json())
        .then(d => {
            let html = '';
            if (d.success && d.data.length > 0) {
                d.data.forEach(c => {
                    html += `<div class="comment-item"><img src="assets/uploads/avatars/${c.avatar}" class="c-avatar"><div style="flex:1;"><div style="font-size:0.8rem; color:#ccc;"><b>${c.username}</b> <span style="float:right; color:#666;">${c.time}</span></div><div style="color:#aaa;">${c.content}</div></div></div>`;
                });
            } else {
                html = '<div style="text-align:center; color:#666;">暂无评论</div>';
            }
            document.getElementById('comment-list-' + id).innerHTML = html;
        });
}

function submitComment(id) {
    const input = document.getElementById('comment-input-' + id);
    const value = input.value.trim();
    if (!value) return;

    const fd = new FormData();
    fd.append('post_id', id);
    fd.append('content', value);

    fetch('api_comment.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            alert(d.msg);
            if (d.success) {
                input.value = '';
                loadComments(id);
            }
        });
}

function parseEmojisJS(text) {
    if (!text) return '';
    const emojiMap = {
        '\\[s:smile\\]': '🙂', '\\[s:joy\\]': '😂', '\\[s:lol\\]': '🤣', '\\[s:love\\]': '😍',
        '\\[s:cool\\]': '😎', '\\[s:thinking\\]': '🤔', '\\[s:cry\\]': '😭', '\\[s:scared\\]': '😱',
        '\\[s:angry\\]': '😡', '\\[s:clown\\]': '🤡', '\\[s:vomit\\]': '🤮', '\\[s:shhh\\]': '🤫',
        '\\[s:thumbsup\\]': '👍', '\\[s:ok\\]': '👌', '\\[s:heart\\]': '❤️', '\\[s:broken\\]': '💔',
        '\\[s:fire\\]': '🔥', '\\[s:star\\]': '✨', '\\[s:poop\\]': '💩',
        '\\[s:ghost\\]': '👻', '\\[s:alien\\]': '👽', '\\[s:robot\\]': '🤖',
        '\\[s:rocket\\]': '🚀', '\\[s:moon\\]': '🌙', '\\[s:game\\]': '🎮',
        '\\[s:cat\\]': '🐱', '\\[s:dog\\]': '🐶', '\\[s:fox\\]': '🦊', '\\[s:bug\\]': '🐞',
        '\\[s:paimon\\]': '🥘', '\\[s:primogem\\]': '💎', '\\[s:gwent\\]': '🃏',
        '\\[s:sword\\]': '⚔️', '\\[s:objection\\]': '👉', '\\[s:tree\\]': '🌳', '\\[s:dragon\\]': '🐉',
    };
    for (const key in emojiMap) {
        text = text.replace(new RegExp(key, 'g'), emojiMap[key]);
    }
    return text;
}

function toggleEmojiPanel() {
    const panel = document.getElementById('emoji-panel');
    panel.style.display = panel.style.display === 'none' ? 'grid' : 'none';
}

function insertEmoji(code) {
    document.getElementById('post-content').value += code;
    toggleEmojiPanel();
}

function showFileName(input) {
    document.getElementById('file-name').innerText = input.files[0].name;
}

function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').style.display = 'flex';
}

function sharePost(id) {
    navigator.clipboard.writeText(location.origin + location.pathname + '?post=' + id);
    alert('复制成功');
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof marked === 'undefined') {
        console.error('Libs missing');
        return;
    }
    marked.use({ breaks: true, gfm: true });

    document.querySelectorAll('.post-card').forEach(post => {
        const raw = post.querySelector('.raw-markdown');
        const content = post.querySelector('.post-content');
        if (!raw || !content) return;

        try {
            content.innerHTML = DOMPurify.sanitize(marked.parse(parseEmojisJS(raw.value)), { FORBID_TAGS: ['style', 'script'] });
            hljs.highlightAll();
        } catch (error) {
            content.innerText = raw.value;
        }
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
