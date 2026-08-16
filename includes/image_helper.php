<?php
/**
 * 图片处理：上传并压缩为 WebP。
 */

/**
 * 将图片压缩并保存为 WebP。
 *
 * @param string $source_path 临时文件路径
 * @param string $target_path 目标保存路径（不带后缀）
 * @param int    $max_width   最大宽度
 * @param int    $quality     压缩质量 0-100
 * @return string|false 成功返回最终文件名，失败返回 false
 */
function upload_and_compress_webp($source_path, $target_path, $max_width = 800, $quality = 80)
{
    $image_info = @getimagesize($source_path);
    if (!$image_info) {
        return false;
    }

    [$width, $height, $type] = $image_info;

    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = @imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $image = @imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $image = @imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }

    if (!$image) {
        return false;
    }

    if ($width > $max_width) {
        $ratio = $max_width / $width;
        $new_width = (int) $max_width;
        $new_height = (int) ($height * $ratio);
    } else {
        $new_width = (int) $width;
        $new_height = (int) $height;
    }

    $new_image = imagecreatetruecolor($new_width, $new_height);

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }

    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    $final_filename = $target_path . '.webp';
    imagewebp($new_image, $final_filename, $quality);

    imagedestroy($image);
    imagedestroy($new_image);

    return basename($final_filename);
}
