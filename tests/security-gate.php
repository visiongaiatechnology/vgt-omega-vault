<?php
// STATUS: PLATIN

declare(strict_types=1);

namespace {
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vgt-omega-security-' . bin2hex(random_bytes(8));
$webRoot = $base . DIRECTORY_SEPARATOR . 'webroot';
$contentRoot = $webRoot . DIRECTORY_SEPARATOR . 'wp-content';
$storageRoot = $base . DIRECTORY_SEPARATOR . 'private-vault';

if (!mkdir($contentRoot, 0700, true) && !is_dir($contentRoot)) {
    throw new RuntimeException('Test environment creation failed.');
}

define('ABSPATH', $webRoot . DIRECTORY_SEPARATOR);
define('WP_CONTENT_DIR', $contentRoot);
define('VGT_OMEGA_STORAGE_ROOT', $storageRoot);
define('DAY_IN_SECONDS', 86400);

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int
    {
        unset($encoding);
        return strlen($value);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $offset, ?int $length = null, ?string $encoding = null): string
    {
        unset($encoding);
        return $length === null ? substr($value, $offset) : substr($value, $offset, $length);
    }
}
if (!function_exists('mb_check_encoding')) {
    function mb_check_encoding(string $value, ?string $encoding = null): bool
    {
        unset($encoding);
        return preg_match('//u', $value) === 1;
    }
}

}

namespace VGTOmegaVault {
    class AppException extends \Exception {}
    class ValidationException extends AppException {}
    class SecurityException extends AppException {}
    class StorageException extends AppException {}
}

namespace {
    /** @var array<string,mixed> */
    $GLOBALS['vgt_test_options'] = [
        'vgt_omega_enable_file_uploads' => '1',
    ];

    function get_option(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['vgt_test_options'][$name] ?? $default;
    }

    function wp_salt(string $scheme = 'auth'): string
    {
        return hash('sha256', 'vgt-test-salt|' . $scheme);
    }

    function home_url(string $path = ''): string
    {
        return 'https://security-test.example' . $path;
    }

    function wp_parse_url(string $url, int $component = -1): mixed
    {
        return parse_url($url, $component);
    }

    /** @return array{basedir:string,error:string} */
    function wp_upload_dir(?string $time = null, bool $create_dir = true, bool $refresh_cache = false): array
    {
        unset($time, $create_dir, $refresh_cache);
        $path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($path)) {
            mkdir($path, 0700, true);
        }
        return ['basedir' => $path, 'error' => ''];
    }

    function sanitize_text_field(string $value): string
    {
        $value = strip_tags($value);
        return trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');
    }

    function sanitize_file_name(string $value): string
    {
        $value = basename(str_replace('\\', '/', $value));
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $value) ?? '';
    }

    function wp_basename(string $path): string
    {
        return basename($path);
    }

    function esc_url_raw(string $url, ?array $protocols = null): string
    {
        unset($protocols);
        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : '';
    }

    function wp_get_environment_type(): string
    {
        return 'production';
    }

    function __(string $message, string $domain = 'default'): string
    {
        unset($domain);
        return $message;
    }

    function trailingslashit(string $path): string
    {
        return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    function get_temp_dir(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR;
    }

    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        unset($hook, $args);
        return $value;
    }

    function do_action(string $hook, mixed ...$args): void
    {
        unset($hook, $args);
    }

    $pluginRoot = dirname(__DIR__);
    require_once $pluginRoot . '/includes/class-vgt-omega-config.php';
    require_once $pluginRoot . '/includes/class-vgt-omega-crypto.php';
    require_once $pluginRoot . '/includes/class-vgt-omega-schema.php';
    require_once $pluginRoot . '/includes/class-vgt-omega-file-vault.php';
    require_once $pluginRoot . '/includes/class-vgt-omega-api.php';

    /** @var list<string> */
    $failures = [];

    $assert = static function(bool $condition, string $message) use (&$failures): void {
        if (!$condition) {
            $failures[] = $message;
        }
    };

    $expectSecurityException = static function(callable $operation, string $message) use (&$failures): void {
        try {
            $operation();
            $failures[] = $message;
        } catch (\VGTOmegaVault\SecurityException) {
        }
    };

    try {
        VGT_Omega_Config::assertEnvironment();
        VGT_Omega_Crypto::installKey();

        $keyPath = VGT_Omega_Config::keyFile();
        $assert(is_file($keyPath), 'Master key was not created.');
        $keyPerms = fileperms($keyPath);
        $assert($keyPerms !== false && (($keyPerms & 0o077) === 0), 'Master key permissions exceed 0600.');

        $ciphertext = VGT_Omega_Crypto::encrypt('classified-payload', 'security-test', 42);
        $assert(str_starts_with($ciphertext, 'v3.'), 'Versioned v3 cryptographic envelope is missing.');
        $plaintext = VGT_Omega_Crypto::decrypt($ciphertext, 'security-test', null, null, 42);
        $assert($plaintext === 'classified-payload', 'AES-GCM round trip failed.');
        $expectSecurityException(
            static fn(): string => VGT_Omega_Crypto::decrypt($ciphertext, 'security-test', null, null, 43),
            'AAD form binding did not reject a mismatched form.'
        );

        $validConfig = [
            'title' => 'Security Test',
            'type' => 'form',
            'fields' => [
                [
                    'id' => 'message',
                    'type' => 'textarea',
                    'label' => 'Message',
                    'required' => true,
                    'max_length' => 1024,
                ],
            ],
            'settings' => [
                'theme' => 'dark',
                'button_text' => 'Submit',
                'subtitle' => 'TLS-protected transmission with encrypted storage',
                'consent_required' => true,
            ],
        ];
        $sanitized = VGT_Omega_Schema::sanitize($validConfig, 7);
        $assert(($sanitized['id'] ?? null) === 7, 'Schema did not bind the server-side form ID.');

        $invalidConfig = $validConfig;
        $invalidConfig['unexpected'] = true;
        try {
            VGT_Omega_Schema::sanitize($invalidConfig, 7);
            $failures[] = 'Unknown configuration keys were accepted.';
        } catch (\VGTOmegaVault\ValidationException) {
        }

        VGT_Omega_File_Vault::install();
        $marker = 'VGT_PRIVATE_FILE_' . bin2hex(random_bytes(12));
        $source = $base . DIRECTORY_SEPARATOR . 'source.bin';
        file_put_contents($source, $marker, LOCK_EX);
        chmod($source, 0600);

        $stored = VGT_Omega_File_Vault::store([
            'path' => $source,
            'name' => 'evidence.txt',
            'mime' => 'text/plain',
            'size' => strlen($marker),
            'sha256' => hash('sha256', $marker),
        ], 7, 'evidence_file');

        $vaultFile = $storageRoot . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR .
            substr($stored['id'], 0, 2) . DIRECTORY_SEPARATOR . $stored['id'] . '.vgt';
        $assert(is_file($vaultFile), 'Encrypted file container was not written.');
        $rawContainer = file_get_contents($vaultFile);
        $assert(is_string($rawContainer) && !str_contains($rawContainer, $marker), 'Private file was stored in plaintext.');

        $restored = VGT_Omega_File_Vault::read($stored['id']);
        $assert($restored['data'] === $marker, 'Encrypted file round trip failed.');
        VGT_Omega_File_Vault::delete($stored['id']);
        $assert(!is_file($vaultFile), 'Encrypted file deletion failed.');

        $_FILES = [
            'rogue_upload' => [
                'name' => 'rogue.txt',
                'tmp_name' => $source,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($marker),
                'type' => 'text/plain',
            ],
        ];
        $method = new ReflectionMethod(VGT_Omega_API::class, 'processFiles');
        $method->setAccessible(true);
        $fields = [
            'message' => [
                'id' => 'message',
                'type' => 'textarea',
                'label' => 'Message',
            ],
        ];
        $payload = [];
        $storedIds = [];
        $temporaryPaths = [];

        $expectSecurityException(
            static function() use ($method, $fields, &$payload, &$storedIds, &$temporaryPaths): void {
                $arguments = [$fields, 7, &$payload, &$storedIds, &$temporaryPaths];
                $method->invokeArgs(null, $arguments);
            },
            'An upload field absent from the server-side schema was accepted.'
        );
        $_FILES = [];

        $forbiddenPatterns = [
            '/\buniqid\s*\(/i' => 'Weak uniqid entropy fallback detected.',
            '/error_reporting\s*\(\s*0\s*\)/i' => 'Suppressed PHP error reporting detected.',
            '/\$_FILES\s*\[[^\]]+\]\s*\[\s*[\'"]size[\'"]\s*\]/i' => 'Client-declared upload size is trusted.',
            '/\bwp_handle_upload\s*\(/i' => 'Public WordPress upload storage call detected.',
            '/chmod\s*\([^,]+,\s*0?644\s*\)/i' => 'Broad 0644 file permission detected.',
            '/chmod\s*\([^,]+,\s*0?755\s*\)/i' => 'Broad 0755 directory permission detected.',
            '#https?://[^\'"\s>]+(?:cdn|cdnjs|unpkg|jsdelivr)#i' => 'External CDN dependency detected.',
        ];

        foreach (array_merge([$pluginRoot . '/vault.php'], glob($pluginRoot . '/includes/*.php') ?: []) as $phpFile) {
            $sourceCode = file_get_contents($phpFile);
            $assert(is_string($sourceCode), 'Could not read source file: ' . $phpFile);
            if (!is_string($sourceCode)) {
                continue;
            }
            $assert(str_contains(substr($sourceCode, 0, 160), 'STATUS: PLATIN'), 'Missing PLATIN status: ' . $phpFile);
            $assert(str_contains(substr($sourceCode, 0, 1000), 'declare(strict_types=1);'), 'Missing strict_types: ' . $phpFile);
            foreach ($forbiddenPatterns as $pattern => $message) {
                $assert(preg_match($pattern, $sourceCode) !== 1, $message . ' File: ' . basename($phpFile));
            }
        }

        $cryptoSource = file_get_contents($pluginRoot . '/includes/class-vgt-omega-crypto.php');
        if (is_string($cryptoSource)) {
            $deriveStart = strpos($cryptoSource, 'public static function deriveKey');
            $deriveEnd = strpos($cryptoSource, 'public static function integrityHash');
            $deriveSource = $deriveStart !== false && $deriveEnd !== false
                ? substr($cryptoSource, $deriveStart, $deriveEnd - $deriveStart)
                : '';
            $assert($deriveSource !== '', 'Key derivation implementation could not be isolated.');
            $assert(!str_contains($deriveSource, 'SECURE_AUTH_KEY'), 'Mutable WordPress salts are used for v7 key derivation.');
            $assert(!str_contains($deriveSource, 'wp_salt'), 'Mutable WordPress salts are used for v7 key derivation.');
            $assert(!str_contains($deriveSource, 'siteDomain'), 'Mutable site identity is used for v7 key derivation.');
        } else {
            $failures[] = 'Could not read cryptographic source.';
        }

        $apiSource = file_get_contents($pluginRoot . '/includes/class-vgt-omega-api.php');
        $assert(
            is_string($apiSource) && str_contains($apiSource, "apply_filters('wp_handle_upload_prefilter'"),
            'Sentinel/WordPress upload prefilter integration is missing.'
        );
        $assert(
            is_string($apiSource) && str_contains($apiSource, 'Unauthorized upload field rejected.'),
            'Schema-bound upload rejection is missing.'
        );
    } catch (Throwable $e) {
        $failures[] = 'Unexpected test fault: ' . $e::class . ': ' . $e->getMessage();
    } finally {
        $deleteTree = static function(string $path) use (&$deleteTree): void {
            if (!file_exists($path) && !is_link($path)) {
                return;
            }
            if (is_link($path) || is_file($path)) {
                @unlink($path);
                return;
            }
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $deleteTree($path . DIRECTORY_SEPARATOR . $entry);
            }
            @rmdir($path);
        };
        $deleteTree($base);
    }

    if ($failures !== []) {
        fwrite(STDERR, "SECURITY GATE FAILED\n");
        foreach ($failures as $failure) {
            fwrite(STDERR, ' - ' . $failure . "\n");
        }
        exit(1);
    }

    fwrite(STDOUT, "SECURITY GATE PASSED\n");
    exit(0);
}
