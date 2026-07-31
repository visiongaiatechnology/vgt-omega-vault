<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGT_Omega_Config
{
    public const CAPABILITY = 'manage_vgt_omega_vault';
    public const MAX_REQUEST_BYTES = 8_388_608;
    public const MAX_CONFIG_BYTES = 262_144;
    public const MAX_FIELDS = 100;
    public const MAX_TEXT_BYTES = 32_768;
    public const MAX_FILE_BYTES = 5_242_880;
    public const TOKEN_TTL_SECONDS = 600;
    public const RATE_WINDOW_SECONDS = 60;
    public const RATE_MAX_SUBMISSIONS = 5;
    public const RATE_MAX_TOKENS = 30;

    /** @return list<string> */
    public static function globallyAllowedMimes(): array
    {
        $mimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (defined('VGT_OMEGA_ALLOW_TEXT_UPLOADS') && VGT_OMEGA_ALLOW_TEXT_UPLOADS === true) {
            $mimes[] = 'text/plain';
        }
        if (defined('VGT_OMEGA_ALLOW_PDF_UPLOADS') && VGT_OMEGA_ALLOW_PDF_UPLOADS === true) {
            $mimes[] = 'application/pdf';
        }

        return $mimes;
    }

    public static function assertEnvironment(): void
    {
        if (PHP_VERSION_ID < 80100) {
            throw new \VGTOmegaVault\SecurityException('PHP 8.1 or newer is required.');
        }

        foreach ([
            'openssl_encrypt',
            'openssl_decrypt',
            'random_bytes',
            'hash_hkdf',
            'finfo_open',
            'mb_strlen',
            'mb_substr',
            'mb_check_encoding',
        ] as $function) {
            if (!function_exists($function)) {
                throw new \VGTOmegaVault\SecurityException('Required cryptographic function unavailable: ' . $function);
            }
        }

        if (!in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            throw new \VGTOmegaVault\SecurityException('AES-256-GCM is unavailable.');
        }
    }

    public static function isReady(): bool
    {
        return defined('VGT_OMEGA_DB_VERSION')
            && get_option('vgt_omega_db_version', '0') === VGT_OMEGA_DB_VERSION;
    }

    public static function assertReady(): void
    {
        if (!self::isReady()) {
            throw new \VGTOmegaVault\SecurityException('Plugin readiness validation failed.', 503);
        }
    }

    public static function storageRoot(): string
    {
        $configured = defined('VGT_OMEGA_STORAGE_ROOT') && is_string(VGT_OMEGA_STORAGE_ROOT)
            ? VGT_OMEGA_STORAGE_ROOT
            : dirname(rtrim(ABSPATH, DIRECTORY_SEPARATOR)) . DIRECTORY_SEPARATOR . '.vgt-omega';

        return rtrim($configured, DIRECTORY_SEPARATOR);
    }

    public static function keyFile(): string
    {
        if (defined('VGT_OMEGA_KEY_FILE') && is_string(VGT_OMEGA_KEY_FILE)) {
            return VGT_OMEGA_KEY_FILE;
        }

        return self::storageRoot() . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'master.key';
    }

    public static function uploadsEnabled(): bool
    {
        return get_option('vgt_omega_enable_file_uploads', '0') === '1';
    }

    public static function notificationsEnabled(): bool
    {
        return get_option('vgt_omega_enable_notifications', '0') === '1';
    }

    public static function honeypotEnabled(): bool
    {
        return get_option('vgt_omega_enable_honeypot', '1') === '1';
    }

    public static function proxiesEnabled(): bool
    {
        return get_option('vgt_omega_allow_proxies', '0') === '1';
    }

    public static function retentionDays(): int
    {
        return max(1, min(3650, (int) get_option('vgt_omega_retention_days', 90)));
    }

    /** @return list<string> */
    public static function trustedProxyCidrs(): array
    {
        if (!defined('VGT_OMEGA_TRUSTED_PROXY_CIDRS') || !is_array(VGT_OMEGA_TRUSTED_PROXY_CIDRS)) {
            return [];
        }

        $result = [];
        foreach (VGT_OMEGA_TRUSTED_PROXY_CIDRS as $value) {
            if (!is_string($value) || preg_match('/^([0-9a-fA-F:.]+)\/(\d{1,3})$/', $value, $match) !== 1) {
                throw new \VGTOmegaVault\SecurityException('Trusted proxy CIDR validation failed.');
            }

            $packed = inet_pton($match[1]);
            $prefix = (int) $match[2];
            $maxBits = $packed === false ? -1 : strlen($packed) * 8;
            if ($packed === false || $prefix < 0 || $prefix > $maxBits) {
                throw new \VGTOmegaVault\SecurityException('Trusted proxy CIDR validation failed.');
            }

            $result[] = $value;
        }

        return array_values(array_unique($result));
    }
}
