<?php
/**
 * VGT OMEGA VAULT: Kryptografischer Kernel (AES-256-GCM)
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT SECURE ZONE: DIRECT ACCESS FORBIDDEN');
}

final class VGT_Omega_Crypto {
    
    private const KEY_DIR = '/vgt_keys';
    private const KEY_FILE = '/.vgt_core_secret.php';
    private const CIPHER = 'aes-256-gcm';
    private const GCM_TAG_LENGTH = 16;

    /**
     * Stellt die Integrität des physischen Dateischlüssels im Upload-Verzeichnis sicher.
     * Nutzt modernes Apache 2.4 Hardening für die .htaccess-Datei.
     */
    public static function verify_vault_integrity(): void {
        $upload_dir = wp_upload_dir();
        $vault_dir = $upload_dir['basedir'] . self::KEY_DIR;
        $key_path = $vault_dir . self::KEY_FILE;

        if (!file_exists($vault_dir)) {
            wp_mkdir_p($vault_dir);
        }

        $htaccess = $vault_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $htaccess_content = "# VGT OMEGA VAULT: DIRECT FILE ACCESS PROTECTION\n" .
                "<IfModule mod_authz_core.c>\n" .
                "    Require all denied\n" .
                "</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\n" .
                "    Order Deny,Allow\n" .
                "    Deny from all\n" .
                "</IfModule>\n";
            file_put_contents($htaccess, $htaccess_content);
        }

        $index = $vault_dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// VGT ZERO-SPACE");
        }

        if (!file_exists($key_path)) {
            try {
                $entropy = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                $entropy = hash('sha256', uniqid((string)wp_hash('vgt-entropy'), true));
            }
            $sha_key = hash('sha256', $entropy);
            
            $file_content = "<?php\nif(!defined('ABSPATH')) exit('VGT SECURE ZONE');\nif(!defined('VGT_OMEGA_SECRET')) {\n    define('VGT_OMEGA_SECRET', '$sha_key');\n}\n";
            file_put_contents($key_path, $file_content);
            @chmod($key_path, 0600);
        }
    }

    private static function get_omega_secret_raw(): string {
        $upload_dir = wp_upload_dir();
        $key_path = $upload_dir['basedir'] . self::KEY_DIR . self::KEY_FILE;
        
        if (file_exists($key_path)) {
            require_once($key_path);
        }

        if (!defined('VGT_OMEGA_SECRET')) {
            wp_die('VGT SYSTEM HALT: Cryptographic core failure.');
        }

        return VGT_OMEGA_SECRET;
    }

    private static function get_legacy_cipher_key(): string {
        return hash('sha256', self::get_omega_secret_raw(), true);
    }

    private static function get_supreme_cipher_key(): string {
        $secret = self::get_omega_secret_raw();
        $salt = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'vgt-emergency-omega-salt';
        
        if (function_exists('hash_hkdf')) {
            return hash_hkdf('sha256', $secret, 32, 'vgt_omega_supreme_v5_binding', $salt);
        }
        
        return hash_hmac('sha256', $secret . 'vgt_omega_supreme_v5_binding', $salt, true);
    }

    private static function get_site_domain(): string {
        $domain = 'vgt-omega-local';
        if (function_exists('home_url')) {
            $domain = parse_url(home_url(), PHP_URL_HOST) ?: home_url();
        }
        return sanitize_text_field((string)$domain);
    }

    public static function encrypt(string $data, string $context = 'payload', ?int $form_id = null): string {
        if ($data === '') {
            return '';
        }
        
        $key = self::get_supreme_cipher_key();
        $iv_len = openssl_cipher_iv_length(self::CIPHER);
        $iv_len = $iv_len !== false ? $iv_len : 12;
        $iv = random_bytes($iv_len);
        $tag = '';
        
        $aad = $context . '|' . self::get_site_domain() . ($form_id !== null ? '|' . $form_id : '');
        
        $ciphertext = openssl_encrypt(
            $data, 
            self::CIPHER, 
            $key, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag, 
            $aad, 
            self::GCM_TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('VGT Cryptographic write fault.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload, string $context = 'payload', ?int $db_row_id = null, ?string $db_column = null, ?int $form_id = null, ?string $table_name = null): string {
        if ($payload === '') {
            return '';
        }
        
        $data = base64_decode($payload, true);
        if ($data === false) {
            return '[DECRYPTION_FAILED_OR_TAMPERED]';
        }
        
        $iv_len = openssl_cipher_iv_length(self::CIPHER);
        $iv_len = $iv_len !== false ? $iv_len : 12;
        
        if (strlen($data) < $iv_len + self::GCM_TAG_LENGTH) {
            return '[DECRYPTION_FAILED_OR_TAMPERED]';
        }
        
        $iv = substr($data, 0, $iv_len);
        $tag = substr($data, $iv_len, self::GCM_TAG_LENGTH);
        $ciphertext = substr($data, $iv_len + self::GCM_TAG_LENGTH);
        
        $supreme_key = self::get_supreme_cipher_key();
        $aad = $context . '|' . self::get_site_domain() . ($form_id !== null ? '|' . $form_id : '');
        
        $decrypted = openssl_decrypt(
            $ciphertext, 
            self::CIPHER, 
            $supreme_key, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag, 
            $aad
        );
        
        if ($decrypted !== false) {
            return $decrypted;
        }

        $decrypted = openssl_decrypt(
            $ciphertext, 
            self::CIPHER, 
            $supreme_key, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag, 
            $context
        );
        
        if ($decrypted !== false) {
            if ($db_row_id !== null && $db_column !== null) {
                self::trigger_background_upgrade($db_row_id, $db_column, $decrypted, $context, $form_id, $table_name);
            }
            return $decrypted;
        }

        $legacy_key = self::get_legacy_cipher_key();
        
        $decrypted = openssl_decrypt(
            $ciphertext, 
            self::CIPHER, 
            $legacy_key, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag, 
            ''
        );
        
        if ($decrypted !== false) {
            if ($db_row_id !== null && $db_column !== null) {
                self::trigger_background_upgrade($db_row_id, $db_column, $decrypted, $context, $form_id, $table_name);
            }
            return $decrypted;
        }

        return '[DECRYPTION_FAILED_OR_TAMPERED]';
    }

    private static function trigger_background_upgrade(int $row_id, string $column, string $plain_text, string $context, ?int $form_id = null, ?string $table_name = null): void {
        global $wpdb;
        $table = $table_name ?: ($wpdb->prefix . VGT_Omega_DB::TABLE_NAME);
        
        $allowed_columns = ['domain', 'email', 'vector', 'threat', 'ip_origin', 'ip_socket', 'ip_claimed', 'payload'];
        if (!in_array($column, $allowed_columns, true)) {
            return;
        }

        try {
            $new_encrypted = self::encrypt($plain_text, $context, $form_id);
            $wpdb->update(
                $table,
                [$column => $new_encrypted],
                ['id' => $row_id],
                ['%s'],
                ['%d']
            );
        } catch (\Throwable $e) {
            error_log('[VGT_OMEGA_UPGRADE_ERROR] Failed to upgrade database record: ' . $e->getMessage());
        }
    }
}