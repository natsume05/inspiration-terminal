<?php
session_start();
require 'includes/db.php';

// 必须登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// --- 处理表单提交 ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. 修改签名
    if (isset($_POST['update_profile'])) {
        $bio = $conn->real_escape_string($_POST['bio']);
        $conn->query("UPDATE users SET bio = '$bio' WHERE id = $user_id");
        $msg = "✅ 签名已更新！";
    }

    // 2. 上传头像
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // 生成唯一文件名：user_ID_时间戳.jpg
            $new_name = "user_" . $user_id . "_" . time() . "." . $ext;
            $target = "assets/uploads/avatars/" . $new_name;
            
            if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $target)) {
                // 更新数据库
                $conn->query("UPDATE users SET avatar = '$new_name' WHERE id = $user_id");
                $msg = "✅ 头像更换成功！";
            } else {
                $msg = "❌ 上传失败，请检查文件夹权限。";
            }
        } else {
            $msg = "❌ 仅支持 JPG, PNG, GIF 格式。";
        }
    }
}

// 获取最新用户信息
$res = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $res->fetch_assoc();

// 处理头像路径 (如果没有上传过，就用默认图)
$avatar_url = "assets/images/default.png"; // 默认图路径
if ($user['avatar'] != 'default.png') {
    $avatar_url = "assets/uploads/avatars/" . $user['avatar'];
}

$page_title = "个人档案";
$style = "community"; 
include 'includes/header.php'; 
?>

<div class="container" style="max-width: 600px; margin-top: 50px;">
    
    <div class="post-card user-card" style="text-align: center;">
        <h2 style="color: #66fcf1;">📂 个人档案 / Profile</h2>
        
        <?php if($msg): ?>
            <div style="background:rgba(102, 252, 241, 0.1); color:#66fcf1; padding:10px; border-radius:5px; margin-bottom:20px;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div style="position: relative; width: 100px; height: 100px; margin: 0 auto 20px;">
            <img src="<?php echo $avatar_url; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%; border:3px solid #45a29e; box-shadow: 0 0 15px rgba(102, 252, 241, 0.3);">
            
            <?php if($user['username'] == 'MingMo'): // 舰长专属特效 ?>
                <div style="position:absolute; bottom:0; right:0; background:#FFD700; color:#000; padding:2px 6px; border-radius:10px; font-size:0.7rem; font-weight:bold;">👑</div>
            <?php endif; ?>
        </div>

        <h3 style="margin:0;"><?php echo htmlspecialchars($user['username']); ?></h3>
        <p style="color:#888; font-size:0.9rem;">UID: <?php echo $user['id']; ?> | 加入于 <?php echo isset($user['created_at']) ? date('Y-m-d', strtotime($user['created_at'])) : '未知时间'; ?>

        <hr style="border:0; border-top:1px dashed #444; margin: 30px 0;">

        <form method="POST" enctype="multipart/form-data" style="text-align: left;">
            
            <label style="display:block; margin-bottom:10px; color:#ccc;">📝 个性签名</label>
            <input type="text" name="bio" value="<?php echo htmlspecialchars($user['bio']); ?>" 
                   style="width:100%; padding:10px; background:rgba(0,0,0,0.3); border:1px solid #444; color:#fff; border-radius:5px; margin-bottom:20px;">
            
            <label style="display:block; margin-bottom:10px; color:#ccc;">🖼️ 更换头像</label>
            <input type="file" name="avatar_file" accept="image/*" style="margin-bottom:20px; color:#888;">

            <button type="submit" name="update_profile" class="dream-btn" style="width:100%;">💾 保存更改</button>
        </form>

        <br>
        <a href="community.php" style="color:#666; font-size:0.9rem;">← 返回社区</a>
    </div>

</div>

<?php include 'includes/footer.php'; ?>