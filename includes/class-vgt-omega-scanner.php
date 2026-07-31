<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGT_Omega_Scanner
{
    /**
     * @param array<string,mixed> $fileArray
     * @param array<string,mixed> $policy
     * @return array{path:string,original_tmp:string,name:string,mime:string,extension:string,size:int,sha256:string}
     */
    public static function scan_and_sanitize(array $fileArray, array $policy = []): array
    {
        $requiredKeys = ['name', 'tmp_name', 'error'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $fileArray)) {
                throw new \VGTOmegaVault\SecurityException('Upload validation failed: missing file metadata.');
            }
        }

        if (!is_string($fileArray['name']) || !is_string($fileArray['tmp_name']) || !is_int($fileArray['error'])) {
            throw new \VGTOmegaVault\SecurityException('Upload validation failed: malformed file metadata.');
        }

        if ($fileArray['error'] !== UPLOAD_ERR_OK) {
            throw new \VGTOmegaVault\ValidationException(self::uploadErrorMessage($fileArray['error']), 422);
        }

        $tempPath = $fileArray['tmp_name'];
        if ($tempPath === '' || !is_uploaded_file($tempPath)) {
            throw new \VGTOmegaVault\SecurityException('Upload path validation failed.');
        }

        $maxBytes = isset($policy['max_bytes']) && is_int($policy['max_bytes'])
            ? max(1, min(VGT_Omega_Config::MAX_FILE_BYTES, $policy['max_bytes']))
            : VGT_Omega_Config::MAX_FILE_BYTES;

        $realSize = filesize($fileArray['tmp_name']);
        if ($realSize === false || $realSize === 0 || $realSize > $maxBytes) {
            throw new \VGTOmegaVault\ValidationException('Size boundary violation.', 413);
        }

        $name = self::sanitizeOriginalName($fileArray['name']);
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($extension === '') {
            throw new \VGTOmegaVault\ValidationException(__('File extension is required.', 'vgt-omega-vault'), 415);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($tempPath);
        if (!is_string($detectedMime) || $detectedMime === '') {
            throw new \VGTOmegaVault\SecurityException('MIME detection failed.');
        }

        $allowedMimes = isset($policy['allowed_mimes']) && is_array($policy['allowed_mimes'])
            ? array_values(array_intersect($policy['allowed_mimes'], VGT_Omega_Config::globallyAllowedMimes()))
            : ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($detectedMime, $allowedMimes, true)) {
            throw new \VGTOmegaVault\ValidationException(__('File type is not permitted.', 'vgt-omega-vault'), 415);
        }

        self::verifyExtension($extension, $detectedMime);

        $outputPath = $tempPath;
        if (str_starts_with($detectedMime, 'image/')) {
            $outputPath = self::reencodeImage($tempPath, $detectedMime);
            $reencodedSize = filesize($outputPath);
            if ($reencodedSize === false || $reencodedSize === 0 || $reencodedSize > $maxBytes) {
                @unlink($outputPath);
                throw new \VGTOmegaVault\ValidationException('Size boundary violation.', 413);
            }
            $realSize = $reencodedSize;
        } elseif ($detectedMime === 'application/pdf') {
            self::validatePdf($tempPath);
        } elseif ($detectedMime === 'text/plain') {
            self::validateText($tempPath);
        } else {
            throw new \VGTOmegaVault\SecurityException('Unsupported scanner route.');
        }

        $hash = hash_file('sha256', $outputPath);
        if (!is_string($hash) || strlen($hash) !== 64) {
            if ($outputPath !== $tempPath) {
                @unlink($outputPath);
            }
            throw new \VGTOmegaVault\StorageException('Upload hash calculation failed.');
        }

        return [
            'path' => $outputPath,
            'original_tmp' => $tempPath,
            'name' => $name,
            'mime' => $detectedMime,
            'extension' => $extension,
            'size' => (int) $realSize,
            'sha256' => $hash,
        ];
    }

    /**
     * Compatibility wrapper for the previous API.
     *
     * @param array<string,mixed> $fileInfo
     */
    public static function scanAndSanitize(array $fileInfo): void
    {
        self::scan_and_sanitize($fileInfo);
    }

    private static function verifyExtension(string $extension, string $detectedMime): void
    {
        $map = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'text/plain' => ['txt'],
            'application/pdf' => ['pdf'],
        ];

        if (!isset($map[$detectedMime]) || !in_array($extension, $map[$detectedMime], true)) {
            throw new \VGTOmegaVault\SecurityException('MIME/type mismatch. Polyglot vector blocked.');
        }
    }

    private static function reencodeImage(string $path, string $detectedMime): string
    {
        if (!extension_loaded('gd')) {
            throw new \VGTOmegaVault\SecurityException('Image sanitization requires GD.');
        }

        $imageInfo = getimagesize($path);
        if (!is_array($imageInfo) || !isset($imageInfo[0], $imageInfo[1], $imageInfo[2])) {
            throw new \VGTOmegaVault\SecurityException('Image parser rejected the upload.');
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        if ($width < 1 || $height < 1 || $width > 8_192 || $height > 8_192 || ($width * $height) > 20_000_000) {
            throw new \VGTOmegaVault\ValidationException(__('Image dimensions exceed the security boundary.', 'vgt-omega-vault'), 413);
        }

        $expectedType = match($detectedMime) {
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png'  => IMAGETYPE_PNG,
            'image/webp' => IMAGETYPE_WEBP,
        };
        if ($imageInfo[2] !== $expectedType) {
            throw new \VGTOmegaVault\SecurityException('MIME/type mismatch. Polyglot vector blocked.');
        }

        $memoryLimit = self::parseIniBytes((string) ini_get('memory_limit'));
        $estimated = ($width * $height * 8) + 16_777_216;
        if ($memoryLimit > 0 && (memory_get_usage(true) + $estimated) > (int) floor($memoryLimit * 0.80)) {
            throw new \VGTOmegaVault\ValidationException(__('Image requires excessive memory.', 'vgt-omega-vault'), 413);
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \VGTOmegaVault\StorageException('Image read failed.');
        }

        $image = imagecreatefromstring($bytes);
        $bytes = '';
        if ($image === false) {
            throw new \VGTOmegaVault\SecurityException('Image decoder rejected the upload.');
        }

        $extension = match($detectedMime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
        };
        $outputPath = trailingslashit(get_temp_dir()) . 'vgt-' . bin2hex(random_bytes(16)) . $extension;
        $previousUmask = umask(0077);

        try {
            $success = match($detectedMime) {
                'image/jpeg' => imagejpeg($image, $outputPath, 90),
                'image/png' => imagepng($image, $outputPath, 6),
                'image/webp' => imagewebp($image, $outputPath, 90),
            };
        } finally {
            imagedestroy($image);
            umask($previousUmask);
        }

        if (!$success || !is_file($outputPath) || !chmod($outputPath, 0600)) {
            @unlink($outputPath);
            throw new \VGTOmegaVault\SecurityException('Sanitized image write failed.');
        }

        return $outputPath;
    }

    private static function validatePdf(string $path): void
    {
        if (!(defined('VGT_OMEGA_ALLOW_PDF_UPLOADS') && VGT_OMEGA_ALLOW_PDF_UPLOADS === true)) {
            throw new \VGTOmegaVault\ValidationException(__('PDF uploads are disabled.', 'vgt-omega-vault'), 415);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \VGTOmegaVault\StorageException('PDF read failed.');
        }

        if (!str_starts_with($contents, '%PDF-') || stripos(substr($contents, -2048), '%%EOF') === false) {
            throw new \VGTOmegaVault\SecurityException('PDF structure validation failed.');
        }

        foreach (['/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/OpenAction', '/AA', '/XFA', '/RichMedia'] as $dangerousToken) {
            if (stripos($contents, $dangerousToken) !== false) {
                throw new \VGTOmegaVault\SecurityException('Active PDF content blocked: ' . $dangerousToken);
            }
        }
    }

    private static function validateText(string $path): void
    {
        if (!(defined('VGT_OMEGA_ALLOW_TEXT_UPLOADS') && VGT_OMEGA_ALLOW_TEXT_UPLOADS === true)) {
            throw new \VGTOmegaVault\ValidationException(__('Text uploads are disabled.', 'vgt-omega-vault'), 415);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \VGTOmegaVault\StorageException('Text file read failed.');
        }

        if (str_contains($contents, "\0") || !mb_check_encoding($contents, 'UTF-8')) {
            throw new \VGTOmegaVault\SecurityException('Text file encoding validation failed.');
        }
    }

    private static function sanitizeOriginalName(string $name): string
    {
        $name = wp_basename(str_replace(["\0", '\\'], ['', '/'], $name));
        $name = sanitize_file_name($name);
        if ($name === '' || strlen($name) > 180) {
            throw new \VGTOmegaVault\ValidationException(__('File name is invalid.', 'vgt-omega-vault'), 422);
        }
        return $name;
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('Uploaded file is too large.', 'vgt-omega-vault'),
            UPLOAD_ERR_PARTIAL => __('The upload was incomplete.', 'vgt-omega-vault'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'vgt-omega-vault'),
            UPLOAD_ERR_NO_TMP_DIR => __('The server upload directory is unavailable.', 'vgt-omega-vault'),
            UPLOAD_ERR_CANT_WRITE => __('The server could not write the upload.', 'vgt-omega-vault'),
            UPLOAD_ERR_EXTENSION => __('A server extension blocked the upload.', 'vgt-omega-vault'),
            default => __('The upload failed.', 'vgt-omega-vault'),
        };
    }

    private static function parseIniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;
        return match($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
