<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGT_Omega_File_Vault
{
    private const MAGIC = 'VGTO7';
    private const CIPHER = 'aes-256-gcm';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    public static function install(): void
    {
        $root = self::root();
        self::assertCandidateOutsideWebRoot($root);
        self::ensureDirectory($root);
        self::assertOutsideWebRoot($root);

        $marker = $root . DIRECTORY_SEPARATOR . '.vgt-omega-root';
        if (!is_file($marker)) {
            self::atomicWrite($marker, "VGT OMEGA VAULT 7\n");
        }

        $files = $root . DIRECTORY_SEPARATOR . 'files';
        self::assertCandidateOutsideWebRoot($files);
        self::ensureDirectory($files);
        self::assertOutsideWebRoot($files);
    }

    /**
     * @param array{path:string,name:string,mime:string,size:int,sha256:string} $upload
     * @return array{id:string,name:string,mime:string,size:int,sha256:string,type:string}
     */
    public static function store(array $upload, int $formId, string $fieldId): array
    {
        self::install();

        if ($formId <= 0 || preg_match('/^[a-z][a-z0-9_]{1,63}$/', $fieldId) !== 1) {
            throw new \VGTOmegaVault\SecurityException('File storage context validation failed.');
        }

        $path = $upload['path'];
        if (!is_file($path) || is_link($path)) {
            throw new \VGTOmegaVault\SecurityException('Upload path validation failed.');
        }

        $realSize = filesize($path);
        if ($realSize === false || $realSize === 0 || $realSize > VGT_Omega_Config::MAX_FILE_BYTES) {
            throw new \VGTOmegaVault\ValidationException('Size boundary violation.', 413);
        }

        $fileId = bin2hex(random_bytes(24));
        $shard = substr($fileId, 0, 2);
        $directory = self::root() . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $shard;
        self::ensureDirectory($directory);

        $filename = $fileId . '.vgt';
        $resolvedDir = realpath($directory);
        if ($resolvedDir === false || !is_dir($resolvedDir)) throw new \VGTOmegaVault\SecurityException('Invalid directory.');
        $destination = $resolvedDir . DIRECTORY_SEPARATOR . $filename;
        if (!str_starts_with($destination, $resolvedDir . DIRECTORY_SEPARATOR)) {
            throw new \VGTOmegaVault\SecurityException('Path escaped jail.');
        }

        $data = file_get_contents($path);
        if ($data === false || strlen($data) !== $realSize) {
            throw new \VGTOmegaVault\StorageException('File payload read failed.');
        }

        $container = json_encode([
            'v' => 1,
            'id' => $fileId,
            'form_id' => $formId,
            'field_id' => $fieldId,
            'name' => $upload['name'],
            'mime' => $upload['mime'],
            'size' => $upload['size'],
            'sha256' => $upload['sha256'],
            'data' => base64_encode($data),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $data = '';

        $salt = random_bytes(16);
        $iv = random_bytes(self::IV_BYTES);
        $key = VGT_Omega_Crypto::deriveKey('file-vault|' . $fileId, $salt);
        $aad = self::aad($fileId, $formId, $fieldId);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $container,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::TAG_BYTES
        );
        self::wipe($key);
        $container = '';

        if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            throw new \VGTOmegaVault\StorageException('File encryption failed.');
        }

        $envelope = json_encode([
            'magic' => self::MAGIC,
            'v' => 1,
            'id' => $fileId,
            'form_id' => $formId,
            'field_id' => $fieldId,
            'salt' => self::b64($salt),
            'iv' => self::b64($iv),
            'tag' => self::b64($tag),
            'ct' => self::b64($ciphertext),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        self::atomicWrite($destination, $envelope);

        return [
            'id' => $fileId,
            'name' => $upload['name'],
            'mime' => $upload['mime'],
            'size' => $upload['size'],
            'sha256' => $upload['sha256'],
            'type' => 'encrypted_file',
        ];
    }

    /**
     * @return array{id:string,form_id:int,field_id:string,name:string,mime:string,size:int,sha256:string,data:string}
     */
    public static function read(string $fileId): array
    {
        $path = self::pathForId($fileId);
        if (!is_file($path) || is_link($path)) {
            throw new \VGTOmegaVault\ValidationException(__('File not found.', 'vgt-omega-vault'), 404);
        }
        self::assertPrivateFile($path);

        $raw = file_get_contents($path);
        if ($raw === false || strlen($raw) > (VGT_Omega_Config::MAX_FILE_BYTES * 2)) {
            throw new \VGTOmegaVault\StorageException('Encrypted file read failed.');
        }

        try {
            $envelope = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file envelope validation failed.');
        }

        if (!is_array($envelope)
            || ($envelope['magic'] ?? null) !== self::MAGIC
            || ($envelope['v'] ?? null) !== 1
            || ($envelope['id'] ?? null) !== $fileId
            || !is_int($envelope['form_id'])
            || !is_string($envelope['field_id'])
        ) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file envelope validation failed.');
        }

        $salt = self::unb64($envelope['salt'] ?? null);
        $iv = self::unb64($envelope['iv'] ?? null);
        $tag = self::unb64($envelope['tag'] ?? null);
        $ciphertext = self::unb64($envelope['ct'] ?? null);

        if (strlen($salt) !== 16 || strlen($iv) !== self::IV_BYTES || strlen($tag) !== self::TAG_BYTES) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file envelope validation failed.');
        }

        $key = VGT_Omega_Crypto::deriveKey('file-vault|' . $fileId, $salt);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::aad($fileId, $envelope['form_id'], $envelope['field_id'])
        );
        self::wipe($key);

        if ($plaintext === false) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file authentication failed.');
        }

        try {
            $container = json_decode($plaintext, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file payload validation failed.');
        }
        $plaintext = '';

        if (!is_array($container)
            || ($container['id'] ?? null) !== $fileId
            || !is_string($container['data'] ?? null)
            || !is_string($container['sha256'] ?? null)
        ) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file payload validation failed.');
        }

        $data = base64_decode($container['data'], true);
        if ($data === false
            || strlen($data) !== (int) $container['size']
            || !hash_equals($container['sha256'], hash('sha256', $data))
        ) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file integrity validation failed.');
        }

        return [
            'id' => $fileId,
            'form_id' => (int) $container['form_id'],
            'field_id' => (string) $container['field_id'],
            'name' => sanitize_file_name((string) $container['name']),
            'mime' => (string) $container['mime'],
            'size' => (int) $container['size'],
            'sha256' => (string) $container['sha256'],
            'data' => $data,
        ];
    }

    public static function delete(string $fileId): void
    {
        $path = self::pathForId($fileId);
        if (is_file($path)) {
            self::assertPrivateFile($path);
            if (!unlink($path)) {
                throw new \VGTOmegaVault\StorageException('Encrypted file deletion failed.');
            }
        }
    }

    /**
     * @param mixed $payload
     * @return list<string>
     */
    public static function collectFileIds(mixed $payload): array
    {
        $result = [];
        self::walk($payload, $result);
        return array_values(array_unique($result));
    }

    private static function root(): string
    {
        return VGT_Omega_Config::storageRoot();
    }

    private static function pathForId(string $fileId): string
    {
        if (preg_match('/^[a-f0-9]{48}$/', $fileId) !== 1) {
            throw new \VGTOmegaVault\SecurityException('File token validation failed.');
        }

        self::install();
        $directory = self::root() . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . substr($fileId, 0, 2);
        if (!is_dir($directory)) {
            return $directory . DIRECTORY_SEPARATOR . $fileId . '.vgt';
        }

        $resolvedDir = realpath($directory);
        if ($resolvedDir === false || !is_dir($resolvedDir)) throw new \VGTOmegaVault\SecurityException('Invalid directory.');
        $filename = $fileId . '.vgt';
        $destination = $resolvedDir . DIRECTORY_SEPARATOR . $filename;
        if (!str_starts_with($destination, $resolvedDir . DIRECTORY_SEPARATOR)) {
            throw new \VGTOmegaVault\SecurityException('Path escaped jail.');
        }
        return $destination;
    }

    private static function ensureDirectory(string $directory): void
    {
        if (is_link($directory)) {
            throw new \VGTOmegaVault\SecurityException('Private vault directory symlink rejected.');
        }

        $previousUmask = umask(0077);
        try {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \VGTOmegaVault\StorageException('Private vault directory creation failed.');
            }
            if (!chmod($directory, 0700)) {
                throw new \VGTOmegaVault\StorageException('Private vault directory hardening failed.');
            }
        } finally {
            umask($previousUmask);
        }
    }

    private static function assertCandidateOutsideWebRoot(string $path): void
    {
        $candidateParent = realpath(dirname($path));
        if ($candidateParent === false || !is_dir($candidateParent)) {
            throw new \VGTOmegaVault\SecurityException('Invalid vault parent path.');
        }

        $candidate = rtrim($candidateParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
        foreach ([realpath(ABSPATH), defined('WP_CONTENT_DIR') ? realpath(WP_CONTENT_DIR) : false] as $webRoot) {
            if (!is_string($webRoot)) {
                continue;
            }
            $root = rtrim($webRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $normalized = rtrim($candidate, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($normalized, $root)) {
                throw new \VGTOmegaVault\SecurityException('Vault path is inside the web root.');
            }
        }
    }

    private static function assertOutsideWebRoot(string $path): void
    {
        $resolved = realpath($path);
        if ($resolved === false) {
            throw new \VGTOmegaVault\SecurityException('Invalid vault path.');
        }

        foreach ([realpath(ABSPATH), defined('WP_CONTENT_DIR') ? realpath(WP_CONTENT_DIR) : false] as $webRoot) {
            if (!is_string($webRoot)) {
                continue;
            }
            $root = rtrim($webRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $candidate = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($candidate, $root)) {
                throw new \VGTOmegaVault\SecurityException('Vault path is inside the web root.');
            }
        }
    }

    private static function assertPrivateFile(string $path): void
    {
        if (is_link($path)) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file symlink rejected.');
        }

        $perms = fileperms($path);
        $stat = stat($path);
        if ($perms === false || (($perms & 0o077) !== 0)) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file permissions are too broad.');
        }
        if ($stat === false || (isset($stat['nlink']) && (int) $stat['nlink'] !== 1)) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file hard-link validation failed.');
        }
        if (function_exists('posix_geteuid')
            && isset($stat['uid'])
            && (int) $stat['uid'] !== posix_geteuid()
        ) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file ownership validation failed.');
        }
    }

    private static function atomicWrite(string $path, string $contents): void
    {
        $tmp = dirname($path) . DIRECTORY_SEPARATOR . '.tmp-' . bin2hex(random_bytes(12));
        $previousUmask = umask(0077);
        try {
            $written = file_put_contents($tmp, $contents, LOCK_EX);
            if ($written !== strlen($contents) || !chmod($tmp, 0600) || !rename($tmp, $path)) {
                @unlink($tmp);
                throw new \VGTOmegaVault\StorageException('Encrypted file atomic write failed.');
            }
        } finally {
            umask($previousUmask);
        }
    }

    private static function aad(string $fileId, int $formId, string $fieldId): string
    {
        return json_encode([
            'magic' => self::MAGIC,
            'id' => $fileId,
            'form_id' => $formId,
            'field_id' => $fieldId,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function unb64(mixed $value): string
    {
        if (!is_string($value) || $value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file encoding validation failed.');
        }
        $padding = (4 - (strlen($value) % 4)) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) {
            throw new \VGTOmegaVault\SecurityException('Encrypted file encoding validation failed.');
        }
        return $decoded;
    }

    /**
     * @param mixed $value
     * @param list<string> $result
     */
    private static function walk(mixed $value, array &$result): void
    {
        if (!is_array($value)) {
            return;
        }
        if (($value['type'] ?? null) === 'encrypted_file' && is_string($value['id'] ?? null)) {
            $result[] = $value['id'];
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                self::walk($child, $result);
            }
        }
    }

    private static function wipe(string &$value): void
    {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($value);
        } else {
            $value = str_repeat("\0", strlen($value));
        }
    }
}
