<?php
// view_post.php - 独立文章页
require 'includes/db.php';

// 1. 获取文章 ID
if (!isset($_GET['id'])) {
    header("Location: blog.php"); exit(); // 没 ID 就踢回列表
}
$post_id = intval($_GET['id']);

// 2. 浏览量 +1 (PHP 直接处理，比 JS 更准)
// 只有在第一次访问该页面时才增加 (防止刷新刷量，可选)
$conn->query("UPDATE blog_posts SET views = views + 1 WHERE id = $post_id");

// 3. 查询文章详情
$sql = "SELECT * FROM blog_posts WHERE id = $post_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("⛔ 信号丢失：找不到这篇日志。");
}
$row = $result->fetch_assoc();

// 页面配置
$page_title = $row['title']; 
$style = "blog"; // 复用博客的 CSS
include 'includes/header.php'; 
?>

<div class="container" style="max-width: 800px; margin-top: 30px;">
    
    <a href="blog.php" class="dream-btn small" style="display:inline-block; width:auto; margin-bottom:20px;">
        ← 返回航行日志
    </a>

    <div class="blog-card" style="animation: fadeIn 0.5s;">
        
        <?php if($row['cover_image']): ?>
            <img src="<?php echo htmlspecialchars($row['cover_image']); ?>" class="blog-cover" alt="Cover">
        <?php endif; ?>

        <div class="blog-body">
            <h1 class="blog-title" style="font-size: 2rem; margin-bottom: 15px;">
                <?php echo htmlspecialchars($row['title']); ?>
            </h1>
            
            <div class="blog-meta-row">
                <span class="meta-item">📅 <?php echo date('Y.m.d', strtotime($row['created_at'])); ?></span>
                <span class="meta-item">👁️ <?php echo $row['views']; ?> 阅读</span>
                <?php if(!empty($row['tags'])): 
                    $tags = explode(',', $row['tags']);
                    foreach($tags as $t) echo '<span class="tag">#'.trim($t).'</span> ';
                endif; ?>
            </div>

            <hr style="border:0; border-top:1px dashed #444; margin: 20px 0;">

            <div class="blog-content" style="font-size: 1.1rem; line-height: 1.8;">
                <?php 
                // nl2br = New Line to Break (<br>)
                // 意思就是：把回车变成换行符
                echo nl2br(htmlspecialchars($row['content'])); 
                ?>
            </div>

        </div>

        <div class="blog-footer">
            <div class="action-btn" onclick="toggleLike(<?php echo $post_id; ?>, this)">
                 ❤ 点赞
            </div>
            <div class="action-btn" onclick="sharePost(<?php echo $post_id; ?>)">
                 🔗 分享坐标
            </div>
        </div>
        
    </div>
    
    </div>

<?php include 'includes/footer.php'; ?>