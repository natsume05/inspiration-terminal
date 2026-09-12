<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Validates and re-encodes uploaded images.
 *
 * The previous implementation checked only `$_FILES['x']['error'] === 0` before
 * handing the temporary file to GD. Two problems followed: an attacker could
 * upload an arbitrarily large image and exhaust memory during decoding, and a
 * non-image file would produce a confusing failure deep inside the image code.
 *
 * This class validates the upload against declared limits, verifies the real
 * image type from the file contents (never the client-supplied name), guards
 * against decompression bombs, and re-encodes the pixels. Re-encoding means the
 * stored file is always an image the server produced, so embedded payloads
 * cannot survive the round trip.
 */
final class ImageUploader
{
    /**
     * @param string $storageDirectory Absolute directory for generated files.
     * @param string $publicPrefix URL prefix that maps to the storage directory.
     * @param int $maxBytes Maximum accepted upload size.
     * @param int $maxPixels Maximum pixel count accepted after decoding.
     */
    public function __construct(
        private readonly string $storageDirectory,
        private readonly string $publicPrefix = '/uploads',
        private readonly int $maxBytes = 5_242_880,
        private readonly int $maxPixels = 40_000_000,
    ) {
    }

    /**
     * Validate, normalise, and store an uploaded image as WebP.
     *
     * @param array<string, mixed> $file One entry from `$_FILES`.
     * @param string $subdirectory Subdirectory under the storage root.
     * @param int $maxWidth Target maximum width in pixels.
     * @param int $quality WebP quality, 1-100.
     * @return string The public path of the stored file.
     */
    public function store(array $file, string $subdirectory, int $maxWidth = 800, int $quality = 80): string
    {
        $this->assertUploadIsSound($file);
        $this->assertWithinSizeLimit($file);

        $temporaryPath = (string) $file['tmp_name'];

        // getimagesize inspects the file contents, so a renamed .php or a
        // polyglot file with an image extension is rejected here.
        $info = @getimagesize($temporaryPath);

        if ($info === false) {
            throw new RuntimeException('该文件不是有效的图片。');
        }

        [$width, $height] = $info;
        $type = $info[2] ?? 0;

        unset($info);

        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException('图片尺寸无效。');
        }

        // Refuse images whose decoded size would exhaust memory: a small
        // compressed file can expand to an enormous bitmap.
        if (($width * $height) > $this->maxPixels) {
            throw new RuntimeException('图片分辨率过大，请压缩后再上传。');
        }

        $allowed = [
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_GIF => 'imagecreatefromgif',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
        ];

        if (!isset($allowed[$type])) {
            throw new RuntimeException('仅支持 JPEG、PNG、GIF 与 WebP 格式。');
        }

        $create = $allowed[$type];
        $source = @$create($temporaryPath);

        if ($source === false) {
            throw new RuntimeException('图片解码失败。');
        }

        try {
            $scaled = $this->scale($source, $width, $height, $maxWidth, $type);
        } finally {
            imagedestroy($source);
        }

        $filename = sprintf('%s_%s.webp', bin2hex(random_bytes(8)), time());
        $targetDirectory = rtrim($this->storageDirectory, '/') . '/' . trim($subdirectory, '/');

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
            imagedestroy($scaled);

            throw new RuntimeException('无法创建上传目录。');
        }

        $targetPath = $targetDirectory . '/' . $filename;
        $written = imagewebp($scaled, $targetPath, $quality);
        imagedestroy($scaled);

        if ($written === false) {
            throw new RuntimeException('图片保存失败。');
        }

        return rtrim($this->publicPrefix, '/') . '/' . trim($subdirectory, '/') . '/' . $filename;
    }

    /**
     * Create the scaled destination image, preserving transparency.
     *
     * @param \GdImage $source Decoded source image.
     * @param int $width Source width.
     * @param int $height Source height.
     * @param int $maxWidth Maximum output width.
     * @param int $type Source image type constant.
     * @return \GdImage The scaled image.
     */
    private function scale(\GdImage $source, int $width, int $height, int $maxWidth, int $type): \GdImage
    {
        if ($width > $maxWidth) {
            $ratio = $maxWidth / $width;
            $targetWidth = $maxWidth;
            $targetHeight = (int) round($height * $ratio);
        } else {
            $targetWidth = $width;
            $targetHeight = $height;
        }

        $targetHeight = max(1, $targetHeight);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            throw new RuntimeException('无法创建图片画布。');
        }

        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF || $type === IMAGETYPE_WEBP) {
            // Without this the transparent regions of a PNG render as black.
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);

            if ($transparent !== false) {
                imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
            }
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    /**
     * Check the upload succeeded and came from a real HTTP upload.
     *
     * @param array<string, mixed> $file One entry from `$_FILES`.
     * @return void
     */
    private function assertUploadIsSound(array $file): void
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ((int) $error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ((int) $error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '图片超过服务器允许的大小。',
                UPLOAD_ERR_PARTIAL => '图片上传中断，请重试。',
                UPLOAD_ERR_NO_FILE => '没有收到图片文件。',
                default => '图片上传失败。',
            });
        }

        // is_uploaded_file closes the door on a crafted tmp_name pointing at an
        // arbitrary local file such as /etc/passwd or a config file.
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('非法的上传来源。');
        }
    }

    /**
     * Enforce the configured byte limit before any decoding happens.
     *
     * @param array<string, mixed> $file One entry from `$_FILES`.
     * @return void
     */
    private function assertWithinSizeLimit(array $file): void
    {
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0) {
            throw new RuntimeException('图片文件为空。');
        }

        if ($size > $this->maxBytes) {
            throw new RuntimeException(sprintf(
                '图片不能超过 %d MB。',
                (int) floor($this->maxBytes / 1_048_576),
            ));
        }
    }
}
