<?php
/**
 * CSRF 防护工具。
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 生成或获取当前会话的 CSRF Token。
 */
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 生成表单隐藏域 HTML。
 */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(generate_csrf_token()) . '">';
}

/**
 * 校验提交的 Token；成功返回 true，失败返回 false（由调用方决定如何提示）。
 */
function verify_csrf_token($submit_token)
{
    return isset($_SESSION['csrf_token'])
        && is_string($submit_token)
        && hash_equals($_SESSION['csrf_token'], $submit_token);
}
