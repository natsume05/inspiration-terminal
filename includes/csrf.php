<?php
// includes/csrf.php - 专门负责防跨站攻击的工具
// 确保 Session 已经开启 (如果其他文件没开，这里补救一下)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 1. 生成或获取当前的 CSRF Token
 * 如果还没有 Token，就造一个随机的 32位 乱码
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes 是 PHP7+ 提供的真·随机数生成器，非常安全
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 2. 生成 HTML 隐藏域
 * 直接放在 <form> 里面用的
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * 3. 验证 Token 是否正确
 * 在处理 POST 请求时调用
 */
function verify_csrf_token($submit_token) {
    if (!isset($_SESSION['csrf_token']) || $submit_token !== $_SESSION['csrf_token']) {
        // 验证失败，直接终止程序，保护网站
        die('🛑 安全警报：CSRF 验证失败！请求来源非法。');
    }
    return true;
}
?>