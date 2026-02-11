<?php
// logout.php - 安全登出
session_start();

// 1. 清除所有 Session 变量
$_SESSION = array();

// 2. 如果有 Cookie，也顺便清理掉 (彻底断开)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. 销毁 Session
session_destroy();

// 4. 跳转回登录页 (而不是服务器根目录)
header("Location: login.php");
exit();
?>