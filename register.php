<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = post_text('username');
    $password = (string) ($_POST['password'] ?? '');
    $captcha_answer = (int) ($_POST['captcha'] ?? 0);

    if ((int) ($_SESSION['captcha_answer'] ?? 0) !== $captcha_answer) {
        $msg = "❌ 验证码错误，你可能是机器人？";
    } elseif ($username === '' || $password === '') {
        $msg = "❌ 代号和密钥不能为空。";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $check = $stmt->get_result();

        if ($check->num_rows > 0) {
            $msg = "⚠️ 该代号已被其他旅行者占用。";
        } else {
            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param('ss', $username, $hashed_pass);

            if ($stmt->execute()) {
                $msg = "✅ 注册成功！正在跳转...";
                header("refresh:2;url=login.php");
            } else {
                $msg = "注册失败，请稍后再试。";
            }
        }
        $stmt->close();
    }
}

$num1 = rand(1, 9);
$num2 = rand(1, 9);
$_SESSION['captcha_answer'] = $num1 + $num2;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="注册灵感传输终端账号，加入虚空梦语社区。">
    <title>注册 | 虚空终端</title>
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
        <h2>申请虚空通行证</h2>
        <p style="color: #ffae42;"><?php echo e($msg); ?></p>
        <form method="POST">
            <input type="text" name="username" placeholder="代号 (Username)" required>
            <input type="password" name="password" placeholder="密钥 (Password)" required>
            <p>验证：<?php echo $num1 . " + " . $num2 . " = ?"; ?></p>
            <input type="number" name="captcha" placeholder="输入答案" required>
            <button type="submit">注册</button>
        </form>
        <br>
        <a href="login.php">已有通行证？去登录</a>
    </div>
</body>
</html>
