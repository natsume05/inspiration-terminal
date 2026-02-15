<?php
// profile.php - 个人档案 (增强版)
session_start();
require 'includes/db.php';
require_once 'includes/image_helper.php';
require_once 'includes/level_system.php'; // 获取等级函数
require_once 'includes/item_loader.php';  // 获取特效

$style = "community"; 
include 'includes/header.php'; 

// 1. 确定要查看的用户ID
$current_uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$target_id = isset($_GET['id']) ? intval($_GET['id']) : $current_uid;

// 如果没有登录且没指定ID，去登录
if ($target_id == 0) { header("Location: login.php"); exit(); }

$is_me = ($target_id == $current_uid);

// 2. 处理更新 (仅限本人)
$msg = "";
if ($is_me && $_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. 修改基础信息
    if (isset($_POST['update_info'])) {
        $new_name = $conn->real_escape_string($_POST['username']);
        $new_bio = $conn->real_escape_string($_POST['bio']);
        
        // 检查用户名是否重复 (如果改了名)
        $check = $conn->query("SELECT id FROM users WHERE username='$new_name' AND id != $target_id");
        if ($check->num_rows > 0) {
            $msg = "❌ 该用户名已被占用！";
        } else {
            $conn->query("UPDATE users SET username='$new_name', bio='$new_bio' WHERE id=$target_id");
            $_SESSION['username'] = $new_name; // 更新 Session
            $msg = "✅ 档案信息已更新！";
        }
    }

    // B. 修改密码
    if (isset($_POST['update_pass'])) {
        $p1 = $_POST['pass1'];
        $p2 = $_POST['pass2'];
        if ($p1 === $p2 && !empty($p1)) {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$hash' WHERE id=$target_id");
            $msg = "✅ 安全密钥已重置！";
        } else {
            $msg = "❌ 两次输入的密码不一致！";
        }
    }

    // C. 上传头像
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] == 0) {
        $base = "user_" . $target_id . "_" . time();
        $processed = upload_and_compress_webp($_FILES['avatar_file']['tmp_name'], "assets/uploads/avatars/" . $base, 250, 80);
        if ($processed) {
            $conn->query("UPDATE users SET avatar='$processed' WHERE id=$target_id");
            $msg = "✅ 头像已上传！";
        }
    }
}

// 3. 读取用户数据
$res = $conn->query("SELECT * FROM users WHERE id = $target_id");
if ($res->num_rows == 0) die("该用户不存在 (404 Not Found)");
$user = $res->fetch_assoc();

// 加载特效
$decor = get_user_decorations($conn, $target_id);
$avatar_url = ($user['avatar'] != 'default.png') ? "assets/uploads/avatars/".$user['avatar'] : "assets/images/default.png";
?>

<link rel="stylesheet" href="assets/css/effects.css?v=<?php echo time(); ?>">

<div class="container" style="max-width: 800px; margin-top: 40px;">
    
    <div class="side-card" style="text-align: center; padding: 40px; position: relative; overflow: hidden;">
        <div style="position: absolute; top:0; left:0; width:100%; height:100%; background: linear-gradient(180deg, rgba(102, 252, 241, 0.05) 0%, rgba(0,0,0,0) 100%); z-index:0;"></div>
        
        <div style="position: relative; z-index: 1;">
            <div class="avatar-wrapper <?php echo $decor['avatar_class']; ?>" style="width: 120px; height: 120px; margin: 0 auto 20px; border-radius: 50%; padding: 4px;">
                <img src="<?php echo $avatar_url; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
            </div>

            <h2 class="<?php echo $decor['name_class']; ?>" style="font-size: 2rem; margin: 0;">
                <?php echo htmlspecialchars($user['username']); ?>
                <?php if($decor['badge_icon']) echo $decor['badge_icon']; ?>
            </h2>

            <div style="margin-top: 10px;">
                <span style="background: #333; color: #ccc; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; border: 1px solid #444;">
                    <?php echo get_rank_name($user['exp']); ?> (EXP: <?php echo $user['exp']; ?>)
                </span>
                <?php if ($user['custom_title']): ?>
                    <span class="custom-title-badge" style="margin-left: 5px; background: linear-gradient(135deg, #f6d365, #fda085); color: #333; font-weight: bold; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">
                        <?php echo htmlspecialchars($user['custom_title']); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div style="margin-top: 20px; color: #888; font-style: italic; max-width: 600px; margin-left: auto; margin-right: auto;">
                "<?php echo !empty($user['bio']) ? htmlspecialchars($user['bio']) : '这个人很懒，没有留下任何信号...'; ?>"
            </div>
        </div>
    </div>

    <?php if ($is_me): ?>
        <div style="margin-top: 30px;">
            <h3 style="color: #66fcf1; border-bottom: 1px solid #333; padding-bottom: 10px;">🔧 档案管理</h3>
            
            <?php if($msg): ?>
                <div style="background:rgba(46,160,67,0.2); color:#3fb950; padding:10px; border-radius:6px; margin-bottom:15px; text-align:center;">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                
                <div class="side-card" style="padding: 20px;">
                    <form method="POST" enctype="multipart/form-data">
                        <label style="color:#aaa; font-size:0.8rem;">代号 (Username)</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required 
                               style="width:100%; padding:10px; background:#0d1117; border:1px solid #30363d; color:#fff; border-radius:5px; margin-bottom:15px;">
                        
                        <label style="color:#aaa; font-size:0.8rem;">签名 (Bio)</label>
                        <input type="text" name="bio" value="<?php echo htmlspecialchars($user['bio']); ?>" 
                               style="width:100%; padding:10px; background:#0d1117; border:1px solid #30363d; color:#fff; border-radius:5px; margin-bottom:15px;">
                        
                        <label style="color:#aaa; font-size:0.8rem;">头像 (Avatar)</label>
                        <input type="file" name="avatar_file" accept="image/*" style="margin-bottom:15px; color:#888;">

                        <button type="submit" name="update_info" class="dream-btn small full-width">💾 保存资料</button>
                    </form>
                </div>

                <div class="side-card" style="padding: 20px;">
                    <form method="POST">
                        <label style="color:#aaa; font-size:0.8rem;">新密钥 (New Password)</label>
                        <input type="password" name="pass1" required 
                               style="width:100%; padding:10px; background:#0d1117; border:1px solid #30363d; color:#fff; border-radius:5px; margin-bottom:15px;">
                        
                        <label style="color:#aaa; font-size:0.8rem;">确认密钥 (Confirm)</label>
                        <input type="password" name="pass2" required 
                               style="width:100%; padding:10px; background:#0d1117; border:1px solid #30363d; color:#fff; border-radius:5px; margin-bottom:15px;">

                        <button type="submit" name="update_pass" class="dream-btn small full-width" style="background:#d63031;">🔒 重置密钥</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 50px;">
        <a href="community.php" class="btn-outline">⬅️ 返回大厅</a>
    </div>

</div>
<?php include 'includes/footer.php'; ?>