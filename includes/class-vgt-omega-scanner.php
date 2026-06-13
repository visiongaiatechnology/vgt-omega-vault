<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT SECURE ZONE: DIRECT ACCESS FORBIDDEN');
}

/**
 * ENGINE: VGT OMEGA FILE UPLOAD GUARD & SCANNER
 * Status: DIAMANT VGT SUPREME
 * 
 * Verifies Magic Bytes/MIME-type integrity, wipes EXIF metadata via image reconstruction,
 * cleans CSV Formula Injections, and scans binary/text streams for malware signatures.
 */
final class VGT_Omega_Scanner {

    private const SCAN_MAX_FILESIZE = 5242880; // 5MB limit
    
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'csv'];

    /**
     * VGT EMBEDDED MALWARE SIGNATURE DATABASE
     */
    private const MALWARE_SIGNATURES = [
        'wp_vcd_injector'      => '/eval\s*\(\s*base64_decode\s*\(\s*gzinflate/i',
        'pharma_hack'          => '/\$\w{1,3}\s*=\s*Array\s*\(\s*[\'"]\w+[\'"]\s*,\s*[\'"]\w+[\'"]\s*\)/',
        'eval_base64_dropper'  => '/eval\s*\(\s*base64_decode\s*\(\s*[\'"][A-Za-z0-9+\/]{100,}[\'"]/i',
        'eval_gzinflate'       => '/eval\s*\(\s*gzinflate\s*\(\s*base64_decode/i',
        'webshell_wso'         => '/WSOsetcookie|WSOlogin|wso_(?:ex|login)/i',
        'webshell_filesman'    => '/FilesMan|class\s+filesman/i',
        'webshell_c99'         => '/c99sh|c99shell|c99madShell/i',
        'webshell_r57'         => '/r57shell|r57\.gen|r57\.php/i',
        'webshell_phpspy'      => '/phpspy|PhpSpy|PHPSpy/i',
        'webshell_alfa'        => '/ALFA_DATA|alfaCmd|AlfaTeaM/i',
        'webshell_marijuana'   => '/Marijuana\s+Shell|MarijuanaShell/i',
        
        // PHP Polyglots inside images
        'gif_php_polyglot'     => '/^GIF89a.*?<\?(?:php|=)/s',
        'jpg_php_polyglot'     => '/\xFF\xD8\xFF.*?<\?(?:php|=)/s',
        'png_php_polyglot'     => '/\x89PNG\x0D\x0A.*?<\?(?:php|=)/s',
        
        // Command Execution & backdoor patterns
        'shell_exec_userinput' => '/(?:shell_exec|passthru|system|exec|popen|proc_open)\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE|SERVER)/i',
        'eval_userinput'       => '/eval\s*\(\s*(?:stripslashes\s*\()?\s*\$_(?:GET|POST|REQUEST|COOKIE)/i',
        'preg_replace_eval'    => '/preg_replace\s*\(\s*[\'"][^\'\"]*\/e[\'"]/',
        'assert_userinput'     => '/assert\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)/i',
        
        // Remote file inclusion
        'remote_include'       => '/(?:include|require)(?:_once)?\s*\(\s*[\'"]https?:\/\//i',
        'data_wrapper_exec'    => '/(?:include|require)(?:_once)?\s*\(\s*[\'"]data:\/\//i',
        'php_input_exec'       => '/(?:include|require)(?:_once)?\s*\(\s*[\'"]php:\/\/input/i',
        'php_filter_exec'      => '/(?:include|require)(?:_once)?\s*\(\s*[\'"]php:\/\/filter/i',
        
        // XSS script tags and suspicious SVG/XML indicators
        'xss_script_tags'      => '/<\s*script[^>]*>|javascript\s*:/i',
        'xss_event_handlers'   => '/\bon(?:load|click|error|mouseover|focus)\s*=/i',
        'xml_svg_script'       => '/<\s*svg.*?<\s*script/is'
    ];

    /**
     * Entrypoint for file scanning and safety sanitization.
     * Throws ValidationException or SecurityException if threat is detected.
     */
    public static function scan_and_sanitize(array $file_info): void {
        $temp_path = $file_info['tmp_name'] ?? '';
        $orig_name = $file_info['name'] ?? '';

        if (!is_string($temp_path) || $temp_path === '' || !file_exists($temp_path)) {
            throw new \VGTOmegaVault\ValidationException(esc_html__('Ungültiger Datei-Pfad.', 'vgt-omega-vault'));
        }

        if (!is_string($orig_name) || $orig_name === '') {
            throw new \VGTOmegaVault\ValidationException(esc_html__('Fehlender Dateiname.', 'vgt-omega-vault'));
        }

        // 1. Strict Extension Check
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \VGTOmegaVault\ValidationException(sprintf(esc_html__('Dateityp .%s ist nicht erlaubt.', 'vgt-omega-vault'), $ext));
        }

        // Size check
        $size = @filesize($temp_path);
        if ($size === false || $size === 0 || $size > self::SCAN_MAX_FILESIZE) {
            throw new \VGTOmegaVault\ValidationException(esc_html__('Datei überschreitet das maximale Limit.', 'vgt-omega-vault'));
        }

        // 2. Validate Echte Dateistruktur (Magic Bytes / MIME-Type Verification with fallback)
        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $temp_path);
                finfo_close($finfo);
            }
        } elseif (function_exists('mime_content_type')) {
            $mime = @mime_content_type($temp_path);
        }

        if (is_string($mime) && $mime !== '') {
            self::verify_mime_consistency($ext, $mime);
        } else {
            // High-security Fallback check for images using native getimagesize() parsing
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                $img_info = @getimagesize($temp_path);
                if ($img_info === false || empty($img_info['mime'])) {
                    throw new \VGTOmegaVault\SecurityException('MIME-Type verifier fallback failed for image content.');
                }
                $mime = $img_info['mime'];
                self::verify_mime_consistency($ext, $mime);
            }
        }

        // Read file contents safely
        $contents = @file_get_contents($temp_path);
        if ($contents === false) {
            throw new \VGTOmegaVault\ValidationException(esc_html__('Datei konnte nicht gelesen werden.', 'vgt-omega-vault'));
        }

        // 3. Malware Signature Engine matching
        self::match_signatures($contents, $orig_name);

        // 4. Image-Sanitization & EXIF-Wipe + Strikter MIME Cross-Check (Pattern 1.5.D)
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            $imageInfo = @getimagesize($temp_path);
            if ($imageInfo === false) {
                throw new \VGTOmegaVault\SecurityException('Image metadata extraction failed.');
            }
            
            $expectedType = match($mime) {
                'image/jpeg', 'image/pjpeg' => IMAGETYPE_JPEG,
                'image/png'                 => IMAGETYPE_PNG,
                'image/gif'                 => IMAGETYPE_GIF,
                default                     => throw new \VGTOmegaVault\SecurityException('Unsupported image mime context.'),
            };

            if ($imageInfo[2] !== $expectedType) {
                throw new \VGTOmegaVault\SecurityException('MIME/type mismatch. Polyglot vector blocked.');
            }

            self::sanitize_image($temp_path, $ext);
        }

        // 5. XML/SVG Script Prevention (Blocking SVGs entirely as a supreme security measure)
        if ($ext === 'svg' || strpos($orig_name, '.svg') !== false) {
            throw new \VGTOmegaVault\SecurityException('SVG uploads are prohibited for security reasons.');
        }

        // 6. CSV Formula Injection Shield
        if (in_array($ext, ['csv', 'txt'], true)) {
            self::sanitize_csv($temp_path, $contents);
        }
    }

    /**
     * Validates that the detected MIME type is consistent with the declared extension.
     */
    private static function verify_mime_consistency(string $ext, string $mime): void {
        $map = [
            'jpg'  => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'pdf'  => ['application/pdf', 'application/x-pdf'],
            'doc'  => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls'  => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'txt'  => ['text/plain'],
            'zip'  => ['application/zip', 'application/x-zip-compressed'],
            'csv'  => ['text/csv', 'text/plain', 'text/x-csv', 'application/csv', 'application/x-csv']
        ];

        if (isset($map[$ext])) {
            $allowed_mimes = $map[$ext];
            $matched = false;
            foreach ($allowed_mimes as $allowed) {
                if (stripos($mime, $allowed) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                throw new \VGTOmegaVault\SecurityException('MIME-Type spoofing detected. Extension does not match file content.');
            }
        }
    }

    /**
     * Checks raw data stream against the malware signature database.
     */
    private static function match_signatures(string $contents, string $filename): void {
        $old_backtrack = ini_set('pcre.backtrack_limit', '1000000');
        $old_recursion = ini_set('pcre.recursion_limit', '1000000');

        foreach (self::MALWARE_SIGNATURES as $sig_name => $pattern) {
            $result = @preg_match($pattern, $contents);
            if ($result === 1) {
                if ($old_backtrack !== false) ini_set('pcre.backtrack_limit', $old_backtrack);
                if ($old_recursion !== false) ini_set('pcre.recursion_limit', $old_recursion);
                throw new \VGTOmegaVault\SecurityException(sprintf('Malware signature matched in uploaded file: %s (%s)', esc_html($filename), $sig_name));
            } elseif ($result === false) {
                if ($old_backtrack !== false) ini_set('pcre.backtrack_limit', $old_backtrack);
                if ($old_recursion !== false) ini_set('pcre.recursion_limit', $old_recursion);
                throw new \VGTOmegaVault\SecurityException('Scanner PCRE evaluation failure. Fail-closed protection triggered.');
            }
        }

        if ($old_backtrack !== false) ini_set('pcre.backtrack_limit', $old_backtrack);
        if ($old_recursion !== false) ini_set('pcre.recursion_limit', $old_recursion);
    }

    /**
     * Parses size strings like "128M" or "1G" into bytes. Returns -1 if unlimited/empty.
     */
    private static function parse_size(string $size_str): int {
        $size_str = trim($size_str);
        if ($size_str === '' || $size_str === '-1') {
            return -1;
        }
        $last = strtolower($size_str[strlen($size_str)-1]);
        $val = (int)$size_str;
        switch ($last) {
            case 'g':
                $val *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $val *= 1024 * 1024;
                break;
            case 'k':
                $val *= 1024;
                break;
        }
        return $val;
    }

    /**
     * Sanitizes images by reconstructing them through PHP GD, stripping all metadata.
     * Prevents Decompression Bombs by estimating memory requirements first.
     */
    private static function sanitize_image(string $path, string $ext): void {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg') || !function_exists('imagepng') || !function_exists('imagegif')) {
            return; // Fallback if GD is missing, regex-checks already ran
        }

        // Decompression Bomb Protection (DoS Mitigation)
        $info = @getimagesize($path);
        if (is_array($info)) {
            $width = $info[0] ?? 0;
            $height = $info[1] ?? 0;
            // Estimated RAM required: width * height * 4 bytes/pixel * safety buffer multiplier
            $estimated_memory = $width * $height * 4 * 5; 
            
            $memory_limit_str = ini_get('memory_limit');
            $memory_limit = self::parse_size($memory_limit_str);
            
            // Allow a maximum of 64MB working RAM, or 85% of memory_limit if smaller
            $max_mem = 67108864; // 64MB
            if ($memory_limit > 0 && $memory_limit < $max_mem) {
                $max_mem = (int)($memory_limit * 0.85);
            }

            if ($estimated_memory > $max_mem) {
                throw new \VGTOmegaVault\ValidationException(esc_html__('Bildauflösung überschreitet zulässigen Speicherbedarf (Decompressions-Bombe).', 'vgt-omega-vault'));
            }
        }

        $img_data = @file_get_contents($path);
        if ($img_data === false) {
            return;
        }

        $im = @imagecreatefromstring($img_data);
        if ($im === false) {
            throw new \VGTOmegaVault\SecurityException('Failed to parse image structure. Corrupted or malicious file.');
        }

        // Re-render and save the image, which discards original EXIF/XMP fields
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $success = @imagejpeg($im, $path, 90);
                break;
            case 'png':
                @imagealphablending($im, false);
                @imagesavealpha($im, true);
                $success = @imagepng($im, $path, 6);
                break;
            case 'gif':
                $success = @imagegif($im, $path);
                break;
            default:
                $success = false;
        }

        @imagedestroy($im);

        if (!$success) {
            throw new \VGTOmegaVault\SecurityException('Failed to write sanitized image stream.');
        }
    }

    /**
     * Neutralizes Formula Injections (CSV/Excel) by escaping leading mathematical characters.
     */
    private static function sanitize_csv(string $path, string $contents): void {
        $lines = explode("\n", $contents);
        $modified = false;

        foreach ($lines as $i => $line) {
            // Check cells in CSV
            $cells = str_getcsv($line);
            $cell_modified = false;
            foreach ($cells as $j => $cell) {
                if ($cell !== '') {
                    $first_char = $cell[0];
                    if (in_array($first_char, ['=', '+', '-', '@'], true)) {
                        // Prepend single quote to neutralize formula injection
                        $cells[$j] = "'" . $cell;
                        $cell_modified = true;
                        $modified = true;
                    }
                }
            }
            if ($cell_modified) {
                // Reconstruct line
                $fp = fopen('php://temp', 'r+');
                if ($fp !== false) {
                    fputcsv($fp, $cells);
                    rewind($fp);
                    $new_line = stream_get_contents($fp);
                    fclose($fp);
                    $lines[$i] = rtrim($new_line, "\r\n");
                }
            }
        }

        if ($modified) {
            $new_contents = implode("\n", $lines);
            @file_put_contents($path, $new_contents, LOCK_EX);
        }
    }
}
