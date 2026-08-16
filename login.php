<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = post_text('username');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $msg = "❌ 请输入代号与密钥。";
    } else {
        $stmt = $conn->prepare("SELECT id, password, role, avatar FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = (int) $row['id'];
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $row['role'];
                $_SESSION['avatar'] = $row['avatar'];
                redirect('community.php');
            } else {
                $msg = "❌ 密钥错误。";
            }
        } else {
            $msg = "❌ 查无此人。";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="登录灵感传输终端，进入个人中心与社区。">
    <title>登录 | 虚空终端</title>
    <style>
        body { background: #0b0c10; color: #c5c6c7; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .box { border: 1px solid #45a29e; padding: 40px; border-radius: 10px; box-shadow: 0 0 15px rgba(69, 162, 158, 0.2); text-align: center; }
        input { background: transparent; border: 1px solid #45a29e; color: #66fcf1; padding: 10px; margin: 10px 0; width: 100%; box-sizing: border-box; }
        button { background: #45a29e; color: #0b0c10; padding: 10px 20px; border: none; cursor: pointer; width: 100%; }
        a { color: #66fcf1; text-decoration: none; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="box">
        <h2>连接虚空网络</h2>
        <p style="color: #ffae42;"><?php echo e($msg); ?></p>
        <form method="POST">
            <input type="text" name="username" placeholder="代号" required>
            <input type="password" name="password" placeholder="密钥" required>
            <button type="submit">接入</button>
        </form>
        <br>
        <a href="register.php">注册新身份</a> | <a href="community.php">以访客身份浏览</a>
    </div>
</body>
</html>
