<?php
// includes/image_helper.php - 图片处理工厂 (源头瘦身版)

/**
 * 图片处理核心函数
 * @param string $source_path  临时文件路径
 * @param string $target_path  目标保存路径 (不带后缀)
 * @param int    $max_width    最大宽度 (默认改为 800，防止过大)
 * @param int    $quality      压缩质量
 */
function upload_and_compress_webp($source_path, $target_path, $max_width = 800, $quality = 80) {
    
    // 1. 获取图片信息
    $image_info = getimagesize($source_path);
    if (!$image_info) return false; 
    
    list($width, $height, $type) = $image_info;

    // 2. 根据类型创建画布
    switch ($type) {
        case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($source_path); break;
        case IMAGETYPE_PNG:  $image = imagecreatefrompng($source_path); break;
        case IMAGETYPE_GIF:  $image = imagecreatefromgif($source_path); break;
        default: return false; 
    }

    // 3. 计算新尺寸 (源头控制：如果太宽，直接算出一个小尺寸)
    if ($width > $max_width) {
        $ratio = $max_width / $width;
        $new_width = intval($max_width);
        $new_height = intval($height * $ratio);
    } else {
        // 如果原本就很小，就不放大，保持原样
        $new_width = intval($width);
        $new_height = intval($height);
    }

    // 4. 创建新画布
    $new_image = imagecreatetruecolor($new_width, $new_height);

    // 5. 保留透明通道 (针对 PNG/GIF)
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        // 关闭混合模式，以便保存 Alpha 通道
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        // 创建透明色
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }

    // 6. 重采样拷贝 (这一步会把大图物理变小)
    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // 7. 保存为 WebP
    $final_filename = $target_path . ".webp";
    imagewebp($new_image, $final_filename, $quality);

    // 8. 释放内存
    imagedestroy($image);
    imagedestroy($new_image);

    return basename($final_filename);
}
?>