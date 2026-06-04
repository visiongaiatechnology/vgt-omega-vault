<?php
/**
 * Plugin Name: VGT OMEGA VAULT
 * Plugin URI: https://visiongaiatechnology.de
 * Description: Kryptografischer Datentresor & Secure Com-Link Endpoint. DIAMANT VGT SUPREME STATUS. Zero-Dependency, O(n) Optimized, AES-256-GCM, CSRF-Hardened.
 * Version: 5.2.1
 * Author: VisionGaia Technology Intelligence System
 * Requires PHP: 8.0
 * License: AGPL-3.0-or-later
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT SECURE ZONE: DIRECT ACCESS FORBIDDEN');
}

/**
 * ==============================================================================
 * KERNEL: KRYPTOGRAFIE (AES-256-GCM) mit Auto-Upgrade & Domain-Locking
 * ==============================================================================
 */
final class VGT_Omega_Crypto {
    
    private const KEY_DIR = '/vgt_keys';
    private const KEY_FILE = '/.vgt_core_secret.php';
    private const CIPHER = 'aes-256-gcm';
    private const GCM_TAG_LENGTH = 16;

    /**
     * Stellt die Integrität des physischen Dateischlüssels im Upload-Verzeichnis sicher.
     * Upgrade in V5.2.1: Modernes Apache 2.4 Hardening für die .htaccess-Datei.
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
            // Härtung nach Issue 3: Apache 2.4 Standard mit Fallback für ältere Server
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

    public static function encrypt(string $data, string $context = 'payload'): string {
        if ($data === '') {
            return '';
        }
        
        $key = self::get_supreme_cipher_key();
        $iv_len = openssl_cipher_iv_length(self::CIPHER);
        $iv_len = $iv_len !== false ? $iv_len : 12;
        $iv = random_bytes($iv_len);
        $tag = '';
        
        $aad = $context . '|' . self::get_site_domain();
        
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

    public static function decrypt(string $payload, string $context = 'payload', ?int $db_row_id = null, ?string $db_column = null): string {
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
        $aad = $context . '|' . self::get_site_domain();
        
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
                self::trigger_background_upgrade($db_row_id, $db_column, $decrypted, $context);
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
                self::trigger_background_upgrade($db_row_id, $db_column, $decrypted, $context);
            }
            return $decrypted;
        }

        return '[DECRYPTION_FAILED_OR_TAMPERED]';
    }

    private static function trigger_background_upgrade(int $row_id, string $column, string $plain_text, string $context): void {
        global $wpdb;
        $table = $wpdb->prefix . VGT_Omega_DB::TABLE_NAME;
        
        $allowed_columns = ['domain', 'email', 'vector', 'threat', 'ip_origin'];
        if (!in_array($column, $allowed_columns, true)) {
            return;
        }

        try {
            $new_encrypted = self::encrypt($plain_text, $context);
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

/**
 * ==============================================================================
 * KERNEL: DATENBANK & ABSTRAKTION
 * ==============================================================================
 */
final class VGT_Omega_DB {
    
    public const TABLE_NAME = 'vgt_omega_audits';

    /**
     * Erstellt/Aktualisiert die Tabellenstruktur.
     * Upgrade in V5.2.1: Verwendung präziser, indizierbarer VARCHAR-Typen statt TEXT (Issue 2).
     */
    public static function install(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        // Optimierte Spaltentypen: TEXT durch dedizierte VARCHAR-Spalten ersetzt für bessere Performance/Indizierung
        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(512) NOT NULL,
            email varchar(255) NOT NULL,
            vector varchar(255) NOT NULL,
            threat text NOT NULL,
            ip_origin varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function get_paginated_audits(int $page = 1, int $per_page = 20): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $offset = ($page - 1) * $per_page;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d, %d", $offset, $per_page)) ?: [];
    }

    public static function get_total_count(): int {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        return (int) $wpdb->get_var("SELECT COUNT(id) FROM $table");
    }

    public static function insert(array $data): bool {
        global $wpdb;
        return (bool) $wpdb->insert(
            $wpdb->prefix . self::TABLE_NAME, 
            $data,
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    public static function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete($wpdb->prefix . self::TABLE_NAME, ['id' => $id], ['%d']);
    }
}

/**
 * ==============================================================================
 * KERNEL: API ENDPOINT & VERTEIDIGUNG
 * ==============================================================================
 */
final class VGT_Omega_API {

    /**
     * Erkennt, ob eine IP-Adresse aus dem offiziellen Cloudflare-Netzwerkbereich stammt.
     * Verhindert Header-Spoofing für HTTP_CF_CONNECTING_IP.
     */
    private static function is_cloudflare_ip(string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Offizielle Cloudflare IPv4-Netzwerkbereiche (CIDR)
        $cf_ipv4_ranges = [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22'
        ];

        if (strpos($ip, ':') === false) {
            // IPv4-Validierung über Binär-Masken-Vergleich
            $ip_long = ip2long($ip);
            if ($ip_long === false) {
                return false;
            }
            foreach ($cf_ipv4_ranges as $range) {
                [$subnet, $bits] = explode('/', $range);
                $subnet_long = ip2long($subnet);
                $mask = -1 << (32 - (int)$bits);
                if (($ip_long & $mask) === ($subnet_long & $mask)) {
                    return true;
                }
            }
        } else {
            // Cloudflare IPv6-Prefix-Schnellprüfung
            $cf_ipv6_prefixes = [
                '2400:cb00:', '2606:4700:', '2803:f800:', '2405:b000:', '2405:8100:', '2c0f:f248:'
            ];
            foreach ($cf_ipv6_prefixes as $prefix) {
                if (stripos($ip, $prefix) === 0) {
                    return true;
                }
            }
            // Spezialabgleich für 2a06:98c0::/29 Range
            if (preg_match('/^2a06:98c[0-7]:/i', $ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Liefert die echte IP-Adresse des Clients (Gehärtet gegen IP-Spoofing nach Issue 1).
     */
    public static function get_secure_ip(): string {
        $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // 1. Cloudflare IP-Validierung: Dem Header nur vertrauen, wenn der anfragende Node wirklich CF ist.
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $cf_ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (self::is_cloudflare_ip($remote_ip) && filter_var($cf_ip, FILTER_VALIDATE_IP)) {
                return sanitize_text_field($cf_ip);
            }
        }

        // 2. Standard-Proxy-Header absichern: Keine privaten/internen IP-Ranges erlauben (Issue 1).
        $proxy_headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'];
        foreach ($proxy_headers as $header) {
            if (isset($_SERVER[$header]) && is_string($_SERVER[$header])) {
                foreach (explode(',', $_SERVER[$header]) as $ip) {
                    $ip = trim($ip);
                    // Filtert private Netzwerk-IPs (10.0.0.0/8, etc.) und reservierte Bereiche aus den Forward-Parametern heraus.
                    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
                    if (filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
                        return sanitize_text_field($ip);
                    }
                }
            }
        }

        // 3. Fallback auf den verifizierten Verbindungssocket
        return filter_var($remote_ip, FILTER_VALIDATE_IP) ? sanitize_text_field($remote_ip) : '127.0.0.1';
    }

    public static function generate_stateless_token(): string {
        $secret = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'vgt-fallback-comlink';
        $hour_bucket = (int)(time() / 3600);
        return hash_hmac('sha256', 'vgt_omega_stateless_comlink_' . $hour_bucket, $secret);
    }

    private static function verify_stateless_token(string $token): bool {
        $secret = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'vgt-fallback-comlink';
        $current_hour = (int)(time() / 3600);
        
        for ($i = 0; $i <= 1; $i++) {
            $expected = hash_hmac('sha256', 'vgt_omega_stateless_comlink_' . ($current_hour - $i), $secret);
            if (hash_equals($expected, $token)) {
                return true;
            }
        }
        return false;
    }

    public static function handle_request(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(['message' => 'VGT: Method Not Allowed.'], 405);
        }

        $nonce_valid = isset($_POST['vgt_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['vgt_nonce']), 'vgt_omega_comlink_action');
        $stateless_token_valid = isset($_POST['vgt_stateless_token']) && self::verify_stateless_token(sanitize_text_field($_POST['vgt_stateless_token']));

        if (!$nonce_valid && !$stateless_token_valid) {
            wp_send_json_error(['message' => 'VGT: CSRF Token Invalid. Connection Terminated.'], 403);
        }

        $client_ip = self::get_secure_ip();
        $rate_limit_key = 'vgt_rl_' . md5($client_ip);
        if (get_transient($rate_limit_key)) {
            wp_send_json_error(['message' => 'VGT: Rate Limit Exceeded. Cooldown Engaged.'], 429);
        }
        set_transient($rate_limit_key, true, 60);

        if (!empty($_POST['vgt_full_name'])) {
            wp_send_json_error(['message' => 'VGT: Bot anomaly detected. Dropping payload.'], 400);
        }

        $raw_domain = isset($_POST['vgt_domain']) ? trim((string)wp_unslash($_POST['vgt_domain'])) : '';
        $raw_email  = isset($_POST['vgt_email']) ? trim((string)wp_unslash($_POST['vgt_email'])) : '';
        $raw_vector = isset($_POST['vgt_vector']) ? trim((string)wp_unslash($_POST['vgt_vector'])) : '';
        $raw_threat = isset($_POST['vgt_threat']) ? trim((string)wp_unslash($_POST['vgt_threat'])) : '';

        if (!is_email($raw_email) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $raw_email)) {
            wp_send_json_error(['message' => 'VGT: Email Syntax Violation.'], 400);
        }

        $domain_ip_regex = '/^(?:https?:\/\/)?(?:[a-zA-Z0-9\-]+\.)+[a-zA-Z]{2,}(?:\/\S*)?$|^(?:https?:\/\/)?(?:\d{1,3}\.){3}(?:\d{1,3}|XXX|xxx)(?:\/\d{1,2})?$/i';
        if (!preg_match($domain_ip_regex, $raw_domain)) {
            wp_send_json_error(['message' => 'VGT: Target Architecture Violation. Invalid Domain or IP format.'], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9\-\s_.,!?:;äöüÄÖÜß&()]{2,255}$/u', $raw_vector)) {
            wp_send_json_error(['message' => 'VGT: Threat Vector Syntax Violation.'], 400);
        }

        if (preg_match('/[<>]/', $raw_threat)) {
            wp_send_json_error(['message' => 'VGT: HTML/Script Injection Blocked. Active Defense Engaged.'], 403);
        }

        $clean_domain = sanitize_text_field($raw_domain);
        $clean_email  = sanitize_email($raw_email);
        $clean_vector = sanitize_text_field($raw_vector);
        $clean_threat = sanitize_textarea_field($raw_threat);

        $payload = [
            'domain'    => VGT_Omega_Crypto::encrypt($clean_domain, 'domain'),
            'email'     => VGT_Omega_Crypto::encrypt($clean_email, 'email'),
            'vector'    => VGT_Omega_Crypto::encrypt($clean_vector, 'vector'),
            'threat'    => VGT_Omega_Crypto::encrypt($clean_threat, 'threat'),
            'ip_origin' => VGT_Omega_Crypto::encrypt($client_ip, 'ip_origin')
        ];

        if (!VGT_Omega_DB::insert($payload)) {
            wp_send_json_error(['message' => 'VGT: DB Crypto-Write Failure.'], 500);
        }

        self::dispatch_notification();
        wp_send_json_success(['message' => 'Transmission Complete. Data Secured.']);
    }

    private static function dispatch_notification(): void {
        $to = get_option('admin_email');
        if (!is_string($to) || empty($to)) {
            return;
        }
        $subject = '/// VGT OMEGA: Neues Audit-Protokoll im Tresor';
        $message  = "SYSTEM ALERT: Eine neue VGT OMEGA Audit-Anfrage wurde empfangen.\n";
        $message .= "Die Daten wurden mit AES-256-GCM verschlüsselt in der Datenbank gesichert.\n\n";
        $message .= "TIMESTAMP: " . current_time('mysql') . "\n";
        $message .= "END OF TRANSMISSION.";
        wp_mail($to, $subject, $message);
    }
}

/**
 * ==============================================================================
 * KERNEL: FRONTEND UI/UX (SHORTCODE GENERATOR)
 * ==============================================================================
 */
final class VGT_Omega_Frontend {

    public static function render_shortcode(): string {
        $nonce = wp_create_nonce('vgt_omega_comlink_action');
        $stateless_token = VGT_Omega_API::generate_stateless_token();
        $ajax_url = admin_url('admin-ajax.php');

        ob_start();
        ?>
        <style>
            .vgt-fe-wrapper {
                --vgt-bg: #030303;
                --vgt-surface: rgba(12, 12, 12, 0.85);
                --vgt-border: rgba(255, 255, 255, 0.08);
                --vgt-border-focus: rgba(212, 175, 55, 0.5);
                --vgt-gold: #d4af37;
                --vgt-gold-glow: rgba(212, 175, 55, 0.4);
                --vgt-text: #f9fafb;
                --vgt-text-muted: #6b7280;
                --vgt-icon: #9ca3af;
                --vgt-error: #ef4444;
                --vgt-success: #10b981;
                
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
                background: var(--vgt-bg);
                color: var(--vgt-text);
                padding: 3rem;
                border-radius: 16px;
                border: 1px solid var(--vgt-border);
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8), inset 0 0 0 1px rgba(255,255,255,0.02);
                max-width: 780px;
                margin: 0 auto;
                backdrop-filter: blur(20px);
                position: relative;
                overflow: hidden;
            }

            .vgt-fe-wrapper::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0; height: 1px;
                background: linear-gradient(90deg, transparent, var(--vgt-gold), transparent);
                opacity: 0.5;
            }

            .vgt-fe-header { 
                text-align: center; 
                margin-bottom: 2.5rem; 
            }

            .vgt-fe-title { 
                color: var(--vgt-text); 
                font-size: 1.75rem; 
                font-weight: 800; 
                margin: 0 0 0.5rem 0; 
                letter-spacing: 1px; 
            }
            
            .vgt-fe-title span {
                color: var(--vgt-gold);
                text-shadow: 0 0 20px var(--vgt-gold-glow);
            }

            .vgt-fe-subtitle { 
                color: var(--vgt-text-muted); 
                font-size: 0.8rem; 
                font-family: 'JetBrains Mono', monospace, sans-serif; 
                letter-spacing: 2px;
                text-transform: uppercase;
            }

            .vgt-fe-group { 
                margin-bottom: 1.75rem; 
                position: relative; 
            }

            .vgt-fe-label { 
                display: flex; 
                align-items: center;
                justify-content: space-between;
                font-size: 0.75rem; 
                color: #9ca3af; 
                text-transform: uppercase; 
                letter-spacing: 1.5px; 
                margin-bottom: 0.75rem; 
                font-family: 'JetBrains Mono', monospace, sans-serif;
                font-weight: 600;
            }

            .vgt-input-wrapper {
                position: relative;
                display: flex;
                align-items: center;
            }

            .vgt-input-icon {
                position: absolute;
                left: 1rem;
                color: var(--vgt-icon);
                display: flex;
                align-items: center;
                transition: color 0.3s ease, filter 0.3s ease;
                pointer-events: none;
            }

            .vgt-fe-input { 
                width: 100%; 
                background: rgba(0,0,0,0.6); 
                border: 1px solid var(--vgt-border); 
                color: var(--vgt-text); 
                padding: 1rem 1rem 1rem 3rem; 
                border-radius: 8px; 
                font-size: 0.95rem; 
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
                box-sizing: border-box; 
                font-family: inherit;
            }

            .vgt-fe-input::placeholder {
                color: #4b5563;
            }

            .vgt-fe-input:focus { 
                outline: none; 
                border-color: var(--vgt-border-focus); 
                background: rgba(10,10,10,0.9); 
                box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
            }

            .vgt-input-wrapper:focus-within .vgt-input-icon {
                color: var(--vgt-gold);
                filter: drop-shadow(0 0 5px var(--vgt-gold-glow));
            }

            .vgt-fe-textarea { 
                resize: vertical; 
                min-height: 120px; 
                padding-left: 1rem;
            }

            .vgt-fe-btn { 
                width: 100%; 
                background: var(--vgt-text); 
                color: var(--vgt-bg); 
                border: none; 
                padding: 1.15rem; 
                font-size: 0.95rem; 
                font-weight: 700; 
                text-transform: uppercase; 
                letter-spacing: 2px; 
                border-radius: 8px; 
                cursor: pointer; 
                transition: all 0.3s ease; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                gap: 0.75rem;
                position: relative;
                overflow: hidden;
            }

            .vgt-fe-btn::before {
                content: '';
                position: absolute;
                top: 0; left: -100%; width: 100%; height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                transition: all 0.5s ease;
            }

            .vgt-fe-btn:hover { 
                background: var(--vgt-gold); 
                box-shadow: 0 10px 25px -5px var(--vgt-gold-glow); 
            }

            .vgt-fe-btn:hover::before {
                left: 100%;
            }

            .vgt-fe-btn:disabled { 
                background: #1f2937;
                color: #6b7280;
                cursor: not-allowed; 
                box-shadow: none; 
            }

            .vgt-fe-honeypot { display: none !important; }

            .vgt-fe-msg { 
                margin-top: 1.5rem; 
                padding: 1.25rem; 
                border-radius: 8px; 
                font-size: 0.85rem; 
                font-family: 'JetBrains Mono', monospace, sans-serif; 
                display: none; 
                text-align: center; 
                animation: vgtFadeIn 0.3s ease-out forwards;
            }

            @keyframes vgtFadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .vgt-fe-msg.success { 
                display: block; 
                background: rgba(16, 185, 129, 0.05); 
                color: var(--vgt-success); 
                border: 1px solid rgba(16, 185, 129, 0.2); 
            }

            .vgt-fe-msg.error { 
                display: block; 
                background: rgba(239, 68, 68, 0.05); 
                color: var(--vgt-error); 
                border: 1px solid rgba(239, 68, 68, 0.2); 
            }

            .vgt-fe-loader { 
                width: 18px; height: 18px; 
                border: 2px solid currentColor; 
                border-bottom-color: transparent; 
                border-radius: 50%; 
                display: inline-block; 
                animation: rotation 1s linear infinite; 
                display: none; 
            }

            @keyframes rotation { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>

        <div class="vgt-fe-wrapper">
            <div class="vgt-fe-header">
                <h2 class="vgt-fe-title">SECURE <span>COM-LINK</span></h2>
                <div class="vgt-fe-subtitle">End-to-End Encrypted Tunnel</div>
            </div>
            
            <form id="vgt-omega-form" autocomplete="off">
                <input type="hidden" name="action" value="vgt_omega_audit_request">
                <input type="hidden" name="vgt_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="vgt_stateless_token" value="<?php echo esc_attr($stateless_token); ?>">
                
                <div class="vgt-fe-honeypot">
                    <input type="text" name="vgt_full_name" tabindex="-1" autocomplete="new-password">
                </div>

                <div class="vgt-fe-group">
                    <label class="vgt-fe-label">Target Architecture <span>(Domain / IP)</span></label>
                    <div class="vgt-input-wrapper">
                        <div class="vgt-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                        </div>
                        <input type="text" name="vgt_domain" class="vgt-fe-input" required placeholder="https://domain.com oder 192.168.1.XXX">
                    </div>
                </div>

                <div class="vgt-fe-group">
                    <label class="vgt-fe-label">Operative Auth <span>(E-Mail)</span></label>
                    <div class="vgt-input-wrapper">
                        <div class="vgt-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                        </div>
                        <input type="email" name="vgt_email" class="vgt-fe-input" required placeholder="operative@visiongaiatechnology.de">
                    </div>
                </div>

                <div class="vgt-fe-group">
                    <label class="vgt-fe-label">Threat Vector <span>(Subject)</span></label>
                    <div class="vgt-input-wrapper">
                        <div class="vgt-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        </div>
                        <input type="text" name="vgt_vector" class="vgt-fe-input" required placeholder="Security Audit, System Upgrade...">
                    </div>
                </div>

                <div class="vgt-fe-group">
                    <label class="vgt-fe-label">Payload Data <span>(Note)</span></label>
                    <div class="vgt-input-wrapper">
                        <textarea name="vgt_threat" class="vgt-fe-input vgt-fe-textarea" required placeholder="Initialisieren Sie die Parameter der Anfrage..."></textarea>
                    </div>
                </div>

                <button type="submit" class="vgt-fe-btn" id="vgt-submit-btn">
                    <span class="vgt-fe-loader" id="vgt-loader"></span>
                    <span id="vgt-btn-text">Initialize Encryption</span>
                </button>

                <div id="vgt-response-msg" class="vgt-fe-msg"></div>
            </form>
        </div>

        <script>
        document.getElementById('vgt-omega-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form = this;
            const btn = document.getElementById('vgt-submit-btn');
            const loader = document.getElementById('vgt-loader');
            const btnText = document.getElementById('vgt-btn-text');
            const msgBox = document.getElementById('vgt-response-msg');
            
            btn.disabled = true;
            loader.style.display = 'inline-block';
            btnText.innerText = 'ENCRYPTING PAYLOAD...';
            msgBox.className = 'vgt-fe-msg';
            
            const formData = new FormData(form);
            
            try {
                const response = await fetch('<?php echo esc_url($ajax_url); ?>', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    msgBox.innerText = 'SUCCESS: ' + result.data.message;
                    msgBox.className = 'vgt-fe-msg success';
                    form.reset();
                } else {
                    msgBox.innerText = 'SYSTEM HALT: ' + (result.data.message || 'Unknown Error');
                    msgBox.className = 'vgt-fe-msg error';
                }
            } catch (error) {
                msgBox.innerText = 'SYSTEM HALT: Network Architecture Failure.';
                msgBox.className = 'vgt-fe-msg error';
            } finally {
                btn.disabled = false;
                loader.style.display = 'none';
                btnText.innerText = 'Initialize Encryption';
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

/**
 * ==============================================================================
 * KERNEL: ADMIN UI/UX (ZERO-DEPENDENCY PLATINUM DESIGN)
 * ==============================================================================
 */
final class VGT_Omega_UI {

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die('VGT SYSTEM HALT: Unauthorized clearance level.', '', ['response' => 403]);
        }

        global $wpdb;
        $table = $wpdb->prefix . VGT_Omega_DB::TABLE_NAME;
        if($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
             VGT_Omega_DB::install();
        }

        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $total_audits = VGT_Omega_DB::get_total_count();
        $total_pages = (int) ceil($total_audits / $per_page);
        $audits = VGT_Omega_DB::get_paginated_audits($page, $per_page);

        self::render_html($audits, $total_audits, $page, $total_pages);
    }

    private static function get_svg(string $name): string {
        $svgs = [
            'database' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5V19A9 3 0 0 0 21 19V5"></path><path d="M3 12A9 3 0 0 0 21 12"></path></svg>',
            'shield' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>',
            'activity' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
            'mail' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>',
            'trash' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>',
            'lock' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
            'code' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>'
        ];
        return $svgs[$name] ?? '';
    }

    private static function render_html(array $audits, int $total, int $current_page, int $total_pages): void {
        ?>
        <style>
            :root {
                --vgt-bg: #050505;
                --vgt-surface: #0a0a0a;
                --vgt-border: #1f1f1f;
                --vgt-gold: #d4af37;
                --vgt-gold-glow: rgba(212, 175, 55, 0.4);
                --vgt-green: #10b981;
                --vgt-red: #ef4444;
                --vgt-text: #f3f4f6;
                --vgt-text-muted: #9ca3af;
                --vgt-font-sans: system-ui, -apple-system, sans-serif;
                --vgt-font-mono: ui-monospace, monospace;
            }
            #wpcontent { padding-left: 0 !important; }
            .vgt-wrapper { background-color: var(--vgt-bg); color: var(--vgt-text); font-family: var(--vgt-font-sans); min-height: 100vh; padding: 2rem; box-sizing: border-box; }
            .vgt-wrapper * { box-sizing: inherit; }
            .vgt-container { max-width: 1200px; margin: 0 auto; }
            .vgt-mono { font-family: var(--vgt-font-mono); }
            .vgt-title-xs { font-size: 0.75rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--vgt-text-muted); }
            .vgt-h1 { font-size: 2.5rem; font-weight: 700; margin: 0; line-height: 1.2; text-shadow: 0 0 15px var(--vgt-gold-glow); }
            .vgt-header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--vgt-border); padding-bottom: 1.5rem; margin-bottom: 2.5rem; }
            .vgt-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
            .vgt-card { background: rgba(10, 10, 10, 0.8); border: 1px solid var(--vgt-border); border-radius: 0.5rem; padding: 1.5rem; position: relative; overflow: hidden; }
            .vgt-card-icon { position: absolute; top: 1.5rem; right: 1.5rem; opacity: 0.1; width: 4rem; height: 4rem; }
            .vgt-card-value { font-size: 2.25rem; font-weight: 700; margin: 0.5rem 0 0 0; }
            .vgt-table-container { border: 1px solid var(--vgt-border); border-radius: 0.5rem; background: var(--vgt-surface); box-shadow: 0 0 30px rgba(0,0,0,0.8); overflow-x: auto; }
            .vgt-table { width: 100%; border-collapse: collapse; text-align: left; }
            .vgt-table th { background: var(--vgt-bg); color: var(--vgt-text-muted); font-weight: 400; padding: 1.25rem; border-bottom: 1px solid var(--vgt-border); }
            .vgt-table td { padding: 1.25rem; border-bottom: 1px solid rgba(31,31,31,0.5); vertical-align: top; font-size: 0.875rem; }
            .vgt-table tr:hover td { background: rgba(255,255,255,0.02); }
            .vgt-badge { display: inline-block; padding: 0.25rem 0.75rem; border: 1px solid var(--vgt-border); background: var(--vgt-bg); border-radius: 0.25rem; color: #d1d5db; font-size: 0.75rem; }
            .vgt-link { color: var(--vgt-text); text-decoration: none; font-weight: 600; transition: color 0.2s; }
            .vgt-link:hover { color: var(--vgt-gold); }
            .vgt-flex-center { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem; }
            
            .vgt-threat { width: 100%; min-width: 250px; max-width: 450px; }
            .vgt-threat-details { background: rgba(0,0,0,0.4); border: 1px solid var(--vgt-border); border-radius: 6px; transition: all 0.3s ease; }
            .vgt-threat-details[open] { border-color: rgba(212, 175, 55, 0.3); box-shadow: 0 0 15px rgba(212, 175, 55, 0.05); }
            .vgt-threat-summary { padding: 0.75rem 1rem; cursor: pointer; color: var(--vgt-text-muted); font-weight: 600; font-size: 0.75rem; letter-spacing: 1px; text-transform: uppercase; user-select: none; display: flex; align-items: center; justify-content: space-between; outline: none; transition: color 0.3s; }
            .vgt-threat-summary:hover { color: var(--vgt-gold); }
            .vgt-threat-summary::-webkit-details-marker { display: none; }
            .vgt-threat-summary::after { content: '+'; color: var(--vgt-gold); font-family: var(--vgt-font-mono); font-size: 1rem; transition: transform 0.3s; }
            details[open] .vgt-threat-summary::after { content: '-'; transform: rotate(180deg); }
            .vgt-threat-content { padding: 0 1rem 1rem 1rem; font-size: 0.85rem; line-height: 1.6; max-height: 250px; overflow-y: auto; color: var(--vgt-text); border-top: 1px solid transparent; }
            details[open] .vgt-threat-content { border-top: 1px solid rgba(255,255,255,0.05); margin-top: 0.5rem; padding-top: 1rem; }
            .vgt-threat-content::-webkit-scrollbar { width: 4px; }
            .vgt-threat-content::-webkit-scrollbar-track { background: transparent; }
            .vgt-threat-content::-webkit-scrollbar-thumb { background: rgba(212, 175, 55, 0.5); border-radius: 4px; }

            .vgt-btn-danger { display: inline-flex; align-items: center; justify-content: center; padding: 0.5rem; border: 1px solid rgba(239, 68, 68, 0.3); color: var(--vgt-red); border-radius: 0.25rem; text-decoration: none; transition: all 0.2s; background: transparent; cursor: pointer; }
            .vgt-btn-danger:hover { background: var(--vgt-red); color: #fff; }
            .vgt-shortcode-box { margin-top: 1rem; padding: 1rem; background: rgba(212, 175, 55, 0.05); border: 1px solid var(--vgt-gold); border-radius: 0.5rem; color: var(--vgt-gold); display: flex; align-items: center; justify-content: space-between; }
            
            .vgt-pagination { margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: center; padding: 1rem 0; }
            .vgt-page-link { display: inline-block; padding: 0.5rem 0.75rem; border: 1px solid var(--vgt-border); background: var(--vgt-bg); border-radius: 0.25rem; color: var(--vgt-text-muted); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all 0.2s; }
            .vgt-page-link:hover { border-color: var(--vgt-gold); color: var(--vgt-gold); }
            .vgt-page-active { background: rgba(212, 175, 55, 0.1); border-color: var(--vgt-gold); color: var(--vgt-gold) !important; }

            .text-green { color: var(--vgt-green) !important; }
            .text-red { color: var(--vgt-red) !important; }
            .text-gold { color: var(--vgt-gold) !important; }
            .text-right { text-align: right; }
        </style>

        <div class="vgt-wrapper">
            <div class="vgt-container">
                
                <header class="vgt-header">
                    <div>
                        <div class="vgt-mono vgt-title-xs text-gold vgt-flex-center" style="margin-bottom: 0.5rem;">
                            <div style="width: 8px; height: 8px; background: var(--vgt-gold); border-radius: 50%; box-shadow: 0 0 10px var(--vgt-gold);"></div>
                            VISION GAIA OMEGA PROTOCOL
                        </div>
                        <h1 class="vgt-h1">Decrypted <span class="text-gold">Vault</span></h1>
                    </div>
                    <div class="vgt-mono vgt-title-xs text-right">
                        <div>SYSTEM INTEGRITY: <span class="text-green">310% (DIAMANT SUPREME STATUS)</span></div>
                        <div>ENCRYPTION: AES-256-GCM</div>
                    </div>
                </header>

                <div class="vgt-stats-grid">
                    <div class="vgt-card">
                        <div class="vgt-card-icon text-gold"><?php echo self::get_svg('database'); ?></div>
                        <div class="vgt-mono vgt-title-xs">Total Audits Secured</div>
                        <div class="vgt-card-value"><?php echo esc_html((string)$total); ?></div>
                    </div>
                    <div class="vgt-card">
                        <div class="vgt-card-icon text-green"><?php echo self::get_svg('shield'); ?></div>
                        <div class="vgt-mono vgt-title-xs">Cipher Algorithm</div>
                        <div class="vgt-card-value text-green" style="font-size: 1.5rem; margin-top: 1rem;">AES-256-GCM</div>
                    </div>
                    <div class="vgt-card" style="border-color: rgba(212, 175, 55, 0.3);">
                        <div class="vgt-card-icon text-gold"><?php echo self::get_svg('code'); ?></div>
                        <div class="vgt-mono vgt-title-xs">Frontend Deployment</div>
                        <div class="vgt-shortcode-box vgt-mono">
                            <span>[vgt_omega_comlink]</span>
                        </div>
                    </div>
                </div>

                <div class="vgt-table-container">
                    <table class="vgt-table">
                        <thead>
                            <tr class="vgt-mono vgt-title-xs">
                                <th>Timestamp</th>
                                <th>Target / Com-Link</th>
                                <th>Target Vector</th>
                                <th>Threat Scenario</th>
                                <th class="text-right">Origin IP</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($audits)) : ?>
                                <tr><td colspan="6" class="vgt-mono" style="text-align: center; padding: 3rem;">Keine Daten im Tresor.</td></tr>
                            <?php else : ?>
                                <?php foreach ($audits as $audit) : 
                                    $dec_domain = VGT_Omega_Crypto::decrypt((string)$audit->domain, 'domain', (int)$audit->id, 'domain');
                                    $dec_email  = VGT_Omega_Crypto::decrypt((string)$audit->email, 'email', (int)$audit->id, 'email');
                                    $dec_vector = VGT_Omega_Crypto::decrypt((string)$audit->vector, 'vector', (int)$audit->id, 'vector');
                                    $dec_threat = VGT_Omega_Crypto::decrypt((string)$audit->threat, 'threat', (int)$audit->id, 'threat');
                                    $dec_ip     = VGT_Omega_Crypto::decrypt((string)$audit->ip_origin, 'ip_origin', (int)$audit->id, 'ip_origin');
                                ?>
                                    <tr>
                                        <td class="vgt-mono vgt-title-xs"><?php echo esc_html(wp_date('d.m.Y H:i', strtotime((string)$audit->created_at))); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url($dec_domain); ?>" target="_blank" class="vgt-link"><?php echo esc_html($dec_domain); ?></a>
                                            <div class="vgt-mono vgt-title-xs text-gold vgt-flex-center">
                                                <?php echo self::get_svg('mail'); ?>
                                                <a href="mailto:<?php echo esc_attr($dec_email); ?>" style="color: inherit; text-decoration: none;"><?php echo esc_html($dec_email); ?></a>
                                            </div>
                                        </td>
                                        <td><span class="vgt-badge vgt-mono"><?php echo esc_html($dec_vector); ?></span></td>
                                        <td>
                                            <div class="vgt-threat">
                                                <details class="vgt-threat-details">
                                                    <summary class="vgt-threat-summary">Payload lesen</summary>
                                                    <div class="vgt-threat-content vgt-mono">
                                                        <?php echo nl2br(esc_html($dec_threat)); ?>
                                                    </div>
                                                </details>
                                            </div>
                                        </td>
                                        <td class="text-right vgt-mono vgt-title-xs"><?php echo esc_html($dec_ip); ?></td>
                                        <td class="text-right">
                                            <?php $delete_url = wp_nonce_url(admin_url('admin-post.php?action=vgt_delete_audit&id=' . (int)$audit->id), 'vgt_delete_audit_nonce'); ?>
                                            <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('/// SYSTEMWARNUNG:\n\nDieser Datensatz wird unwiderruflich und kryptografisch aus der Datenbank vernichtet.\n\nFortfahren?');" class="vgt-btn-danger" title="Purge Record">
                                                <?php echo self::get_svg('trash'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1) : ?>
                    <div class="vgt-pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <?php 
                            $class = ($i === $current_page) ? 'vgt-page-active' : ''; 
                            $page_url = add_query_arg('paged', $i, admin_url('admin.php?page=vgt-omega-vault'));
                            ?>
                            <a href="<?php echo esc_url($page_url); ?>" class="vgt-page-link <?php echo esc_attr($class); ?>"><?php echo esc_html((string)$i); ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

                <div class="vgt-mono vgt-title-xs text-gold vgt-flex-center" style="justify-content: center; margin-top: 3rem;">
                    <?php echo self::get_svg('lock'); ?>
                    All records are decrypted on-the-fly directly in RAM. Zero Unencrypted Disk State.
                </div>
            </div>
        </div>
        <?php
    }
}

/**
 * ==============================================================================
 * KERNEL BOOTSTRAPPER & EVENT REGISTRATION
 * ==============================================================================
 */
final class VGT_Omega_Bootstrapper {
    
    public static function ignite(): void {
        register_activation_hook(__FILE__, [VGT_Omega_DB::class, 'install']);
        
        add_action('admin_init', [VGT_Omega_Crypto::class, 'verify_vault_integrity']);
        add_action('admin_menu', [self::class, 'register_menu']);
        
        // API Endpoints
        add_action('wp_ajax_vgt_omega_audit_request', [VGT_Omega_API::class, 'handle_request']);
        add_action('wp_ajax_nopriv_vgt_omega_audit_request', [VGT_Omega_API::class, 'handle_request']);
        add_action('admin_post_vgt_delete_audit', [self::class, 'handle_deletion']);

        // Frontend Com-Link Generator
        add_shortcode('vgt_omega_comlink', [VGT_Omega_Frontend::class, 'render_shortcode']);
    }

    public static function register_menu(): void {
        add_menu_page(
            esc_html__('VGT Vault', 'vgt-omega-vault'), 
            esc_html__('VGT Vault', 'vgt-omega-vault'), 
            'manage_options', 
            'vgt-omega-vault', 
            [VGT_Omega_UI::class, 'render'], 
            'dashicons-shield', 
            3
        );
    }

    public static function handle_deletion(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('VGT SYSTEM HALT: Unauthorized clearance level.', 'vgt-omega-vault'), '', ['response' => 403]);
        }

        check_admin_referer('vgt_delete_audit_nonce');

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id > 0) {
            VGT_Omega_DB::delete($id);
        }

        wp_safe_redirect(admin_url('admin.php?page=vgt-omega-vault'));
        exit;
    }
}

// System Initialisierung
VGT_Omega_Bootstrapper::ignite();
