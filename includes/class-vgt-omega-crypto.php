<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGT_Omega_Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_BYTES = 16;
    private const IV_BYTES = 12;
    private const VERSION_PREFIX = 'v3.';
    private const LEGACY_KEY_DIR = '/vgt_keys';
    private const LEGACY_KEY_FILE = '/.vgt_core_secret.php';

    private static ?string $secretCache = null;

    public static function installKey(): void
    {
        VGT_Omega_Config::assertEnvironment();

        if (defined('VGT_OMEGA_MASTER_KEY') && is_string(VGT_OMEGA_MASTER_KEY)) {
            self::normalizeSecret(VGT_OMEGA_MASTER_KEY);
            return;
        }

        $storageRoot = VGT_Omega_Config::storageRoot();
        self::assertExternalPath($storageRoot);
        self::ensurePrivateDirectory($storageRoot);

        $keyPath = VGT_Omega_Config::keyFile();
        self::assertExternalPath(dirname($keyPath));
        self::ensurePrivateDirectory(dirname($keyPath));

        if (is_file($keyPath)) {
            self::readSecretFile($keyPath);
            return;
        }

        $legacy = self::readLegacySecret();
        $secret = $legacy ?? bin2hex(random_bytes(32));
        self::atomicWrite($keyPath, $secret . PHP_EOL, 0600);
        self::$secretCache = $secret;

    }

    public static function finalizeLegacyMigration(): void
    {
        $legacy = self::readLegacySecret();
        if ($legacy === null) {
            return;
        }

        $current = self::readSecret();
        if (!hash_equals($current, $legacy)) {
            throw new \VGTOmegaVault\SecurityException('Legacy key migration validation failed.');
        }

        self::removeLegacyKey();
    }

    public static function verify_vault_integrity(): void
    {
        self::installKey();
        self::assertExternalPath(dirname(VGT_Omega_Config::keyFile()));
        self::readSecret();
    }

    public static function encrypt(string $data, string $context = 'payload', ?int $form_id = null): string
    {
        if ($data === '') {
            return '';
        }

        $key = self::deriveKey('data-v7');
        $iv = random_bytes(self::IV_BYTES);
        $aad = self::aad($context, $form_id);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $data,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::TAG_BYTES
        );

        self::wipe($key);

        if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            throw new \VGTOmegaVault\StorageException('Cryptographic write fault.');
        }

        $envelope = [
            'v' => 3,
            'alg' => 'A256GCM',
            'kid' => self::keyId(),
            'iv' => self::b64urlEncode($iv),
            'tag' => self::b64urlEncode($tag),
            'ct' => self::b64urlEncode($ciphertext),
        ];

        return self::VERSION_PREFIX . self::b64urlEncode(
            json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    public static function decrypt(
        string $payload,
        string $context = 'payload',
        ?int $db_row_id = null,
        ?string $db_column = null,
        ?int $form_id = null,
        ?string $table_name = null
    ): string {
        unset($db_row_id, $db_column, $table_name);

        if ($payload === '') {
            return '';
        }

        if (str_starts_with($payload, self::VERSION_PREFIX)) {
            return self::decryptV3(substr($payload, strlen(self::VERSION_PREFIX)), $context, $form_id);
        }

        return self::decryptLegacy($payload, $context, $form_id);
    }

    public static function isCurrentEnvelope(string $payload): bool
    {
        return str_starts_with($payload, self::VERSION_PREFIX);
    }

    public static function deriveKey(string $purpose, string $salt = ''): string
    {
        if (!preg_match('/^[a-z0-9._|-]{1,96}$/i', $purpose)) {
            throw new \VGTOmegaVault\SecurityException('Invalid cryptographic context.');
        }

        $secretHex = self::readSecret();
        $master = hex2bin($secretHex);
        if ($master === false || strlen($master) !== 32) {
            throw new \VGTOmegaVault\SecurityException('Master key material validation failed.');
        }

        $applicationSalt = hash('sha256', 'vgt-omega-v7-kdf-salt', true);
        $derived = hash_hkdf('sha256', $master, 32, 'vgt-omega|' . $purpose, $applicationSalt . $salt);
        self::wipe($master);

        if (strlen($derived) !== 32) {
            throw new \VGTOmegaVault\StorageException('Key derivation failed.');
        }
        return $derived;
    }

    public static function integrityHash(string $data, string $context): string
    {
        $key = self::deriveKey('integrity|' . $context);
        $hash = hash_hmac('sha256', $data, $key);
        self::wipe($key);
        return $hash;
    }

    private static function decryptV3(string $encoded, string $context, ?int $formId): string
    {
        $json = self::b64urlDecode($encoded);
        try {
            $envelope = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \VGTOmegaVault\SecurityException('Cipher envelope validation failed.');
        }

        if (!is_array($envelope)
            || $envelope['v'] !== 3
            || $envelope['alg'] !== 'A256GCM'
            || !isset($envelope['kid'], $envelope['iv'], $envelope['tag'], $envelope['ct'])
            || !is_string($envelope['kid'])
            || !hash_equals(self::keyId(), $envelope['kid'])
        ) {
            throw new \VGTOmegaVault\SecurityException('Cipher envelope validation failed.');
        }

        $iv = self::b64urlDecode((string) $envelope['iv']);
        $tag = self::b64urlDecode((string) $envelope['tag']);
        $ciphertext = self::b64urlDecode((string) $envelope['ct']);

        if (strlen($iv) !== self::IV_BYTES || strlen($tag) !== self::TAG_BYTES) {
            throw new \VGTOmegaVault\SecurityException('Cipher envelope validation failed.');
        }

        $key = self::deriveKey('data-v7');
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::aad($context, $formId)
        );
        self::wipe($key);

        if ($plaintext === false) {
            throw new \VGTOmegaVault\SecurityException('Ciphertext authentication failed.');
        }

        return $plaintext;
    }

    private static function decryptLegacy(string $payload, string $context, ?int $formId): string
    {
        $data = base64_decode($payload, true);
        if ($data === false || strlen($data) < self::IV_BYTES + self::TAG_BYTES) {
            throw new \VGTOmegaVault\SecurityException('Legacy ciphertext validation failed.');
        }

        $iv = substr($data, 0, self::IV_BYTES);
        $tag = substr($data, self::IV_BYTES, self::TAG_BYTES);
        $ciphertext = substr($data, self::IV_BYTES + self::TAG_BYTES);
        $secret = self::readSecret();
        $salt = defined('SECURE_AUTH_KEY') && is_string(SECURE_AUTH_KEY)
            ? SECURE_AUTH_KEY
            : 'vgt-emergency-omega-salt';

        $supreme = hash_hkdf('sha256', $secret, 32, 'vgt_omega_supreme_v5_binding', $salt);
        $domain = self::siteDomain();
        $aadCandidates = [
            $context . '|' . $domain . ($formId !== null ? '|' . $formId : ''),
            $context,
        ];

        foreach ($aadCandidates as $aad) {
            $plaintext = openssl_decrypt(
                $ciphertext,
                self::CIPHER,
                $supreme,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $aad
            );
            if ($plaintext !== false) {
                self::wipe($supreme);
                return $plaintext;
            }
        }

        $legacyKey = hash('sha256', $secret, true);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $legacyKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            ''
        );

        self::wipe($supreme);
        self::wipe($legacyKey);

        if ($plaintext === false) {
            throw new \VGTOmegaVault\SecurityException('Legacy ciphertext authentication failed.');
        }

        return $plaintext;
    }

    private static function aad(string $context, ?int $formId): string
    {
        if (!preg_match('/^[a-z0-9._|-]{1,96}$/i', $context)) {
            throw new \VGTOmegaVault\SecurityException('Invalid cryptographic context.');
        }

        return json_encode([
            'v' => 3,
            'kid' => self::keyId(),
            'context' => $context,
            'form_id' => $formId,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function keyId(): string
    {
        return substr(hash('sha256', self::readSecret()), 0, 16);
    }

    private static function siteDomain(): string
    {
        $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new \VGTOmegaVault\SecurityException('Site origin unavailable.');
        }
        return strtolower($host);
    }

    private static function readSecret(): string
    {
        if (self::$secretCache !== null) {
            return self::$secretCache;
        }

        if (defined('VGT_OMEGA_MASTER_KEY') && is_string(VGT_OMEGA_MASTER_KEY)) {
            self::$secretCache = self::normalizeSecret(VGT_OMEGA_MASTER_KEY);
            return self::$secretCache;
        }

        self::installKey();
        self::$secretCache = self::readSecretFile(VGT_Omega_Config::keyFile());
        return self::$secretCache;
    }

    private static function readSecretFile(string $path): string
    {
        self::assertExternalPath(dirname($path));

        $resolved = realpath($path);
        if ($resolved === false || !is_file($resolved) || is_link($path)) {
            throw new \VGTOmegaVault\SecurityException('Invalid key path.');
        }

        $perms = fileperms($resolved);
        if ($perms === false || (($perms & 0o077) !== 0)) {
            throw new \VGTOmegaVault\SecurityException('Key file permissions are too broad.');
        }

        $stat = stat($resolved);
        if ($stat === false || (isset($stat['nlink']) && (int) $stat['nlink'] !== 1)) {
            throw new \VGTOmegaVault\SecurityException('Key file hard-link validation failed.');
        }
        if (function_exists('posix_geteuid')
            && isset($stat['uid'])
            && (int) $stat['uid'] !== posix_geteuid()
        ) {
            throw new \VGTOmegaVault\SecurityException('Key file ownership validation failed.');
        }

        $contents = file_get_contents($resolved);
        if ($contents === false) {
            throw new \VGTOmegaVault\StorageException('Key file read failed.');
        }

        return self::normalizeSecret(trim($contents));
    }

    private static function normalizeSecret(string $value): string
    {
        $trimmed = trim($value);
        if (preg_match('/^[a-f0-9]{64}$/i', $trimmed) === 1) {
            return strtolower($trimmed);
        }

        $decoded = base64_decode($trimmed, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return bin2hex($decoded);
        }

        throw new \VGTOmegaVault\SecurityException('Master key validation failed.');
    }

    private static function readLegacySecret(): ?string
    {
        $upload = wp_upload_dir(null, false, true);
        if (!empty($upload['error']) || !isset($upload['basedir']) || !is_string($upload['basedir'])) {
            return null;
        }

        $path = $upload['basedir'] . self::LEGACY_KEY_DIR . self::LEGACY_KEY_FILE;
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \VGTOmegaVault\StorageException('Legacy key read failed.');
        }

        if (preg_match("/define\\s*\\(\\s*['\"]VGT_OMEGA_SECRET['\"]\\s*,\\s*['\"]([a-f0-9]{64})['\"]\\s*\\)/i", $contents, $match) !== 1) {
            throw new \VGTOmegaVault\SecurityException('Legacy key validation failed.');
        }

        return strtolower($match[1]);
    }

    private static function removeLegacyKey(): void
    {
        $upload = wp_upload_dir(null, false, true);
        if (!isset($upload['basedir']) || !is_string($upload['basedir'])) {
            return;
        }

        $directory = $upload['basedir'] . self::LEGACY_KEY_DIR;
        $path = $directory . self::LEGACY_KEY_FILE;
        if (is_file($path) && !unlink($path)) {
            throw new \VGTOmegaVault\SecurityException('Legacy key path could not be removed.');
        }

        foreach (['/.htaccess', '/index.php'] as $companion) {
            $candidate = $directory . $companion;
            if (is_file($candidate)) {
                @unlink($candidate);
            }
        }
        if (is_dir($directory)) {
            @rmdir($directory);
        }
    }

    private static function ensurePrivateDirectory(string $directory): void
    {
        if (is_link($directory)) {
            throw new \VGTOmegaVault\SecurityException('Private key directory symlink rejected.');
        }

        $previousUmask = umask(0077);
        try {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \VGTOmegaVault\StorageException('Private key directory creation failed.');
            }
            if (!chmod($directory, 0700)) {
                throw new \VGTOmegaVault\StorageException('Private key directory hardening failed.');
            }
        } finally {
            umask($previousUmask);
        }
    }

    private static function atomicWrite(string $path, string $contents, int $mode): void
    {
        $directory = dirname($path);
        $tmp = $directory . DIRECTORY_SEPARATOR . '.tmp-' . bin2hex(random_bytes(12));
        $previousUmask = umask(0077);

        try {
            $bytes = file_put_contents($tmp, $contents, LOCK_EX);
            if ($bytes !== strlen($contents) || !chmod($tmp, $mode) || !rename($tmp, $path)) {
                @unlink($tmp);
                throw new \VGTOmegaVault\StorageException('Atomic key write failed.');
            }
        } finally {
            umask($previousUmask);
        }
    }

    private static function assertExternalPath(string $input): void
    {
        $resolvedDir = realpath($input);
        if ($resolvedDir === false || !is_dir($resolvedDir)) {
            $parent = realpath(dirname($input));
            if ($parent === false || !is_dir($parent)) {
                throw new \VGTOmegaVault\SecurityException('Invalid directory.');
            }
            $resolvedDir = $parent . DIRECTORY_SEPARATOR . basename($input);
        }

        $webRoots = array_filter([
            realpath(ABSPATH),
            defined('WP_CONTENT_DIR') ? realpath(WP_CONTENT_DIR) : false,
        ], static fn(mixed $value): bool => is_string($value));

        foreach ($webRoots as $webRoot) {
            $root = rtrim($webRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $candidate = rtrim($resolvedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($candidate, $root)) {
                throw new \VGTOmegaVault\SecurityException('Key path is inside the web root.');
            }
        }
    }

    private static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        if ($data === '' || preg_match('/^[A-Za-z0-9_-]+$/', $data) !== 1) {
            throw new \VGTOmegaVault\SecurityException('Encoded token validation failed.');
        }

        $padding = (4 - (strlen($data) % 4)) % 4;
        $decoded = base64_decode(strtr($data . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) {
            throw new \VGTOmegaVault\SecurityException('Encoded token validation failed.');
        }
        return $decoded;
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
