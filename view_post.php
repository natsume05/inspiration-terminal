<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($post_id <= 0) {
    redirect('blog.php');
}

// 浏览量 +1。
$conn->query("UPDATE blog_posts SET views = views + 1 WHERE id = $post_id");

$stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
$stmt->bind_param('i', $post_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit('⛔ 信号丢失：找不到这篇日志。');
}
$row = $result->fetch_assoc();
$stmt->close();

$page_title = $row['title'];
$page_description = $row['title'] . '——深空日志文章。';
$page_keywords = '博客,文章,日志';
$style = "blog";

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 800px; margin-top: 30px;">
    <a href="blog.php" class="dream-btn small" style="display:inline-block; width:auto; margin-bottom:20px;">
        ← 返回航行日志
    </a>

    <div class="blog-card" style="animation: fadeIn 0.5s;">
        <?php if ($row['cover_image']): ?>
            <img src="<?php echo e($row['cover_image']); ?>" class="blog-cover" alt="Cover">
        <?php endif; ?>

        <div class="blog-body">
            <h1 class="blog-title" style="font-size: 2rem; margin-bottom: 15px;">
                <?php echo e($row['title']); ?>
            </h1>

            <div class="blog-meta-row">
                <span class="meta-item">📅 <?php echo date('Y.m.d', strtotime($row['created_at'])); ?></span>
                <span class="meta-item">👁️ <?php echo (int) $row['views']; ?> 阅读</span>
                <?php if (!empty($row['tags'])):
                    $tags = array_filter(array_map('trim', explode(',', $row['tags'])));
                    foreach ($tags as $tag):
                ?>
                    <span class="tag">#<?php echo e($tag); ?></span>
                <?php endforeach; endif; ?>
            </div>

            <hr style="border:0; border-top:1px dashed #444; margin: 20px 0;">

            <div class="blog-content" style="font-size: 1.1rem; line-height: 1.8;">
                <?php echo nl2br(e($row['content'])); ?>
            </div>
        </div>

        <div class="blog-footer">
            <div class="action-btn" onclick="toggleLike(<?php echo $post_id; ?>, this)">❤ 点赞</div>
            <div class="action-btn" onclick="sharePost(<?php echo $post_id; ?>)">🔗 分享坐标</div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
