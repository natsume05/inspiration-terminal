<?php
// includes/image_helper.php

/**
 * 图片处理核心函数
 * @param string $source_path  临时文件路径 (比如 $_FILES['file']['tmp_name'])
 * @param string $target_path  目标保存路径 (不带后缀)
 * @param int    $max_width    最大宽度 (超过这个宽度会自动缩小，防止图片太大)
 * @param int    $quality      压缩质量 (0-100，建议 75-80)
 * @return string|false        成功返回带 .webp 后缀的文件名，失败返回 false
 */
function upload_and_compress_webp($source_path, $target_path, $max_width = 1200, $quality = 80) {
    
    // 1. 获取图片信息
    list($width, $height, $type) = getimagesize($source_path);
    
    if (!$width) return false; // 不是有效的图片

    // 2. 根据类型创建画布
    switch ($type) {
        case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($source_path); break;
        case IMAGETYPE_PNG:  $image = imagecreatefrompng($source_path); break;
        case IMAGETYPE_GIF:  $image = imagecreatefromgif($source_path); break;
        default: return false; // 不支持的格式
    }

    // 3. 计算新尺寸 (如果图片太宽，就等比缩小)
    if ($width > $max_width) {
        $new_width = intval($max_width); // 🟢 强制转整数
        // 🟢 强制转整数
        $new_height = intval(($height / $width) * $new_width);
    } else {
        $new_width = intval($width);
        $new_height = intval($height);
    }

    // 4. 创建新画布 (真彩色)
    // 这里的参数必须是整数，现在安全了
    $new_image = imagecreatetruecolor($new_width, $new_height);

    // 5. 处理透明通道 (关键！否则 PNG 转 WebP 会变黑底)
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }

    // 6. 复制并调整大小
    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // 7. 保存为 WebP
    // 确保目标路径没有后缀，我们自己加 .webp
    $final_filename = $target_path . ".webp";
    
    // 保存 (imagewebp 是 PHP 内置函数)
    imagewebp($new_image, $final_filename, $quality);


    // 返回生成的文件名 (比如 post_123.webp)
    return basename($final_filename);
}
?>