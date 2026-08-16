<?php
/**
 * 通用工具函数集
 *
 * 集中存放跨页面复用的辅助函数，避免在每个页面重复实现，
 * 统一输出、跳转、鉴权、转义等基础能力。
 */

require_once __DIR__ . '/config.php';

/**
 * HTML 转义快捷函数（等价于 htmlspecialchars，统一 ENT_QUOTES 语义）。
 */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * 输出 JSON 响应并终止脚本。
 */
function emit_json($data, $http_code = 200)
{
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 跳转到站内地址并终止脚本。
 */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/**
 * 获取当前登录用户 ID；未登录返回 0。
 */
function current_user_id()
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

/**
 * 是否管理员（管理员 ID 统一由 config.php 的 ADMIN_USER_ID 控制）。
 */
function is_admin()
{
    return current_user_id() === ADMIN_USER_ID;
}

/**
 * 要求登录：未登录时跳转到登录页。
 */
function require_login()
{
    if (current_user_id() === 0) {
        redirect('login.php');
    }
    return current_user_id();
}

/**
 * 生成用户头像 URL；未上传头像时回退到默认头像。
 */
function get_avatar_url($avatar)
{
    $avatar = trim((string) $avatar);
    return $avatar !== '' && $avatar !== 'default.png'
        ? 'assets/uploads/avatars/' . $avatar
        : 'assets/images/default.png';
}

/**
 * 人性化时间显示（“几分钟前”）。
 */
function time_ago($timestamp)
{
    if (empty($timestamp)) {
        return '未知时间';
    }

    $seconds = time() - strtotime($timestamp);

    if ($seconds <= 60) {
        return '刚刚';
    }

    $minutes = round($seconds / 60);
    $hours   = round($seconds / 3600);
    $days    = round($seconds / 86400);
    $weeks   = round($seconds / 604800);
    $months  = round($seconds / 2629440);
    $years   = round($seconds / 31553280);

    if ($minutes <= 60) {
        return $minutes . '分钟前';
    }
    if ($hours <= 24) {
        return $hours . '小时前';
    }
    if ($days <= 7) {
        return $days . '天前';
    }
    if ($weeks <= 4.3) {
        return $weeks . '周前';
    }
    if ($months <= 12) {
        return $months . '月前';
    }
    return $years . '年前';
}

/**
 * 安全读取 POST 整型参数。
 */
function post_int($key, $default = 0)
{
    return isset($_POST[$key]) ? (int) $_POST[$key] : $default;
}

/**
 * 安全读取 POST 字符串参数（去除首尾空白）。
 */
function post_text($key, $default = '')
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

/**
 * 安全读取 GET 字符串参数（去除首尾空白）。
 */
function get_text($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

/**
 * 输出 SEO 友好的 <title> 与 meta 信息。
 * 页面可设置 $page_title / $page_description / $page_keywords 覆盖默认值。
 */
function page_meta()
{
    global $page_title, $page_description, $page_keywords;

    $site_name = defined('SITE_NAME') ? SITE_NAME : '灵感传输终端';
    $title = (!empty($page_title) && $page_title !== $site_name)
        ? $page_title . ' - ' . $site_name
        : $site_name;
    $description = !empty($page_description)
        ? $page_description
        : '灵感传输终端——集博客、工具箱与匿名社区于一体的个人门户网站。';
    $keywords = !empty($page_keywords)
        ? $page_keywords
        : '灵感传输终端,个人网站,博客,工具箱,社区';

    echo '<title>' . e($title) . '</title>' . "\n";
    echo '<meta name="description" content="' . e($description) . '">' . "\n";
    echo '<meta name="keywords" content="' . e($keywords) . '">' . "\n";
    echo '<meta name="author" content="MingMo">' . "\n";
    echo '<meta property="og:title" content="' . e($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . e($description) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
}
