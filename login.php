<?php
require 'db.php';

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $conn->real_escape_string($_POST['username']);
    $pass = $_POST['password'];

    $sql = "SELECT id, password, role FROM users WHERE username='$user'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // 验证加密密码
        if (password_verify($pass, $row['password'])) {
            // 登录成功！存入 Session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $user;
            $_SESSION['role'] = $row['role']; // 记住身份
            header("Location: community.php");
            exit();
        } else {
            $msg = "❌ 密钥错误。";
        }
    } else {
        $msg = "❌ 查无此人。";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <p style="color: #ffae42;"><?php echo $msg; ?></p>
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