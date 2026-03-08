<?php
/**
 * Plugin Name: VGT OMEGA VAULT
 * Plugin URI:  https://visiongaiatechnology.de
 * Description: Kryptografischer Datentresor & Secure Com-Link Endpoint. DIAMANT VGT SUPREME STATUS. Zero-Dependency, O(n) Optimized, AES-256-GCM, CSRF-Hardened.
 * Version:     5.0.0
 * Author:      VisionGaia Technology Intelligence System
 * Requires PHP: 8.0
 * License:     AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * * VGT OMEGA PROTOCOL: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or (at your option) 
 * any later version.
 */

if (!defined('ABSPATH')) {
    exit('VGT SECURE ZONE: DIRECT ACCESS FORBIDDEN');
}

/**
 * ==============================================================================
 * KERNEL: KRYPTOGRAFIE (AES-256-GCM)
 * ==============================================================================
 */
final class VGT_Omega_Crypto {
    
    private const KEY_DIR = '/vgt_keys';
    private const KEY_FILE = '/.vgt_core_secret.php';

    public static function verify_vault_integrity(): void {
        $upload_dir = wp_upload_dir();
        $vault_dir = $upload_dir['basedir'] . self::KEY_DIR;
        $key_path = $vault_dir . self::KEY_FILE;

        if (!file_exists($vault_dir)) {
            wp_mkdir_p($vault_dir);
        }

        $htaccess = $vault_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order Allow,Deny\nDeny from all");
        }

        $index = $vault_dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// VGT ZERO-SPACE");
        }

        if (!file_exists($key_path)) {
            $entropy = random_bytes(64);
            $sha_key = hash('sha256', $entropy);
            $file_content = "<?php\nif(!defined('ABSPATH')) exit('VGT SECURE ZONE');\n// VGT OMEGA KERNEL SECRET\n// MANUELLE ÄNDERUNG ZERSTÖRT ALLE VERSCHLÜSSELTEN DATEN!\ndefine('VGT_OMEGA_SECRET', '$sha_key');\n";
            file_put_contents($key_path, $file_content);
            chmod($key_path, 0600);
        }
    }

    private static function get_cipher_key(): string {
        $upload_dir = wp_upload_dir();
        $key_path = $upload_dir['basedir'] . self::KEY_DIR . self::KEY_FILE;
        
        if (file_exists($key_path)) {
            require_once($key_path);
        }

        if (!defined('VGT_OMEGA_SECRET')) {
            wp_die('VGT SYSTEM HALT: Cryptographic core failure.');
        }

        return hash('sha256', VGT_OMEGA_SECRET, true);
    }

    public static function encrypt(string $data): string {
        if ($data === '') return '';
        
        $key = self::get_cipher_key();
        $cipher = 'aes-256-gcm';
        $iv_len = 12; 
        $iv = random_bytes($iv_len);
        $tag = '';
        
        $ciphertext = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): string {
        if ($payload === '') return '';
        
        $key = self::get_cipher_key();
        $cipher = 'aes-256-gcm';
        $data = base64_decode($payload);
        
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);
        
        $decrypted = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted !== false ? $decrypted : '[DECRYPTION_FAILED_OR_TAMPERED]';
    }
}

/**
 * ==============================================================================
 * KERNEL: DATENBANK & ABSTRAKTION
 * ==============================================================================
 */
final class VGT_Omega_DB {
    
    public const TABLE_NAME = 'vgt_omega_audits';

    public static function install(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain text NOT NULL,
            email text NOT NULL,
            vector text NOT NULL,
            threat text NOT NULL,
            ip_origin text NOT NULL,
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
        return (bool) $wpdb->insert($wpdb->prefix . self::TABLE_NAME, $data);
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

    public static function handle_request(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(['message' => 'VGT: Method Not Allowed.'], 405);
        }

        if (!isset($_POST['vgt_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['vgt_nonce']), 'vgt_omega_comlink_action')) {
            wp_send_json_error(['message' => 'VGT: CSRF Token Invalid. Connection Terminated.'], 403);
        }

        $client_ip = $_SERVER['REMOTE_ADDR'];
        $rate_limit_key = 'vgt_rl_' . md5($client_ip);
        if (get_transient($rate_limit_key)) {
            wp_send_json_error(['message' => 'VGT: Rate Limit Exceeded. Cooldown Engaged.'], 429);
        }
        set_transient($rate_limit_key, true, 60);

        if (!empty($_POST['vgt_full_name'])) {
            wp_send_json_error(['message' => 'VGT: Bot anomaly detected. Dropping payload.'], 400);
        }

        $raw_domain = wp_unslash($_POST['vgt_domain'] ?? '');
        $raw_email  = wp_unslash($_POST['vgt_email'] ?? '');
        $raw_vector = wp_unslash($_POST['vgt_vector'] ?? '');
        $raw_threat = wp_unslash($_POST['vgt_threat'] ?? '');

        if (!is_email($raw_email) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $raw_email)) {
            wp_send_json_error(['message' => 'VGT: Email Syntax Violation.'], 400);
        }

        if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9\-]+\.)+[a-zA-Z]{2,}(?:\/\S*)?$/i', $raw_domain)) {
            wp_send_json_error(['message' => 'VGT: Domain Architecture Violation.'], 400);
        }

        if (!preg_match('/^[a-z0-9\-]{3,50}$/i', $raw_vector)) {
            wp_send_json_error(['message' => 'VGT: Threat Vector Syntax Violation.'], 400);
        }

        if (preg_match('/[<>{}\[\]\=]/', $raw_threat)) {
            wp_send_json_error(['message' => 'VGT: Injection Attempt Blocked. Active Defense Engaged.'], 403);
        }

        $payload = [
            'domain'    => VGT_Omega_Crypto::encrypt(sanitize_text_field($raw_domain)),
            'email'     => VGT_Omega_Crypto::encrypt(sanitize_email($raw_email)),
            'vector'    => VGT_Omega_Crypto::encrypt(sanitize_text_field($raw_vector)),
            'threat'    => VGT_Omega_Crypto::encrypt(sanitize_textarea_field($raw_threat)),
            'ip_origin' => VGT_Omega_Crypto::encrypt($client_ip)
        ];

        if (!VGT_Omega_DB::insert($payload)) {
            wp_send_json_error(['message' => 'VGT: DB Crypto-Write Failure.'], 500);
        }

        self::dispatch_notification();
        wp_send_json_success(['message' => 'Transmission Complete. Data Secured.']);
    }

    private static function dispatch_notification(): void {
        $to = get_option('admin_email');
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
        $ajax_url = admin_url('admin-ajax.php');

        ob_start();
        ?>
        <style>
            .vgt-fe-wrapper {
                --vgt-bg: #050505;
                --vgt-surface: rgba(10, 10, 10, 0.7);
                --vgt-border: rgba(255, 255, 255, 0.1);
                --vgt-gold: #d4af37;
                --vgt-gold-glow: rgba(212, 175, 55, 0.3);
                --vgt-text: #f3f4f6;
                --vgt-text-muted: #9ca3af;
                --vgt-error: #ef4444;
                --vgt-success: #10b981;
                font-family: system-ui, -apple-system, sans-serif;
                background: var(--vgt-bg);
                color: var(--vgt-text);
                padding: 2.5rem;
                border-radius: 12px;
                border: 1px solid var(--vgt-border);
                box-shadow: 0 10px 30px rgba(0,0,0,0.8), inset 0 0 20px rgba(255,255,255,0.02);
                max-width: 600px;
                margin: 0 auto;
                backdrop-filter: blur(10px);
            }
            .vgt-fe-header { text-align: center; margin-bottom: 2rem; border-bottom: 1px solid var(--vgt-border); padding-bottom: 1.5rem; }
            .vgt-fe-title { color: var(--vgt-gold); font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem 0; letter-spacing: 2px; text-transform: uppercase; text-shadow: 0 0 10px var(--vgt-gold-glow); }
            .vgt-fe-subtitle { color: var(--vgt-text-muted); font-size: 0.85rem; font-family: monospace; letter-spacing: 1px; }
            .vgt-fe-group { margin-bottom: 1.5rem; position: relative; }
            .vgt-fe-label { display: block; font-size: 0.75rem; color: var(--vgt-text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; font-family: monospace; }
            .vgt-fe-input { width: 100%; background: rgba(0,0,0,0.5); border: 1px solid var(--vgt-border); color: var(--vgt-text); padding: 0.875rem 1rem; border-radius: 6px; font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; }
            .vgt-fe-input:focus { outline: none; border-color: var(--vgt-gold); box-shadow: 0 0 15px var(--vgt-gold-glow); background: rgba(0,0,0,0.8); }
            .vgt-fe-textarea { resize: vertical; min-height: 120px; }
            .vgt-fe-btn { width: 100%; background: transparent; color: var(--vgt-gold); border: 1px solid var(--vgt-gold); padding: 1rem; font-size: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; border-radius: 6px; cursor: pointer; transition: all 0.3s ease; display: flex; justify-content: center; align-items: center; gap: 0.5rem; box-shadow: 0 0 10px var(--vgt-gold-glow); }
            .vgt-fe-btn:hover { background: var(--vgt-gold); color: #000; box-shadow: 0 0 20px var(--vgt-gold-glow); }
            .vgt-fe-btn:disabled { opacity: 0.5; cursor: not-allowed; border-color: var(--vgt-text-muted); color: var(--vgt-text-muted); box-shadow: none; }
            .vgt-fe-honeypot { display: none !important; }
            .vgt-fe-msg { margin-top: 1.5rem; padding: 1rem; border-radius: 6px; font-size: 0.875rem; font-family: monospace; display: none; text-align: center; }
            .vgt-fe-msg.success { display: block; background: rgba(16, 185, 129, 0.1); color: var(--vgt-success); border: 1px solid var(--vgt-success); }
            .vgt-fe-msg.error { display: block; background: rgba(239, 68, 68, 0.1); color: var(--vgt-error); border: 1px solid var(--vgt-error); }
            .vgt-fe-loader { width: 16px; height: 16px; border: 2px solid currentColor; border-bottom-color: transparent; border-radius: 50%; display: inline-block; animation: rotation 1s linear infinite; display: none; }
            @keyframes rotation { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>

        <div class="vgt-fe-wrapper">
            <div class="vgt-fe-header">
                <h2 class="vgt-fe-title">Secure Com-Link</h2>
                <div class="vgt-fe-subtitle">ENCRYPTED TRANSMISSION PROTOCOL</div>
            </div>
            
            <form id="vgt-omega-form" autocomplete="off">
                <input type="hidden" name="action" value="vgt_omega_audit_request">
                <input type="hidden" name="vgt_nonce" value="<?php echo esc_attr($nonce); ?>">
                
                <div class="vgt-fe-honeypot">
                    <input type="text" name="vgt_full_name" tabindex="-1" autocomplete="new-password">
                </div>

                <div class="vgt-fe-group">
                    <label class="vgt-fe-label">Target Domain / URL</label>
                    <input type="text" name="vgt_domain" class="vgt-fe-input" required placeholder="https://target-system.com">
                </div>

                <div class="vgt-fe-group">
                    <label class="vgt-fe-label">Secure Return Address (Email)</label>
                    <input type="email" name="vgt_email" class="vgt-fe-input" required placeholder="operative@domain.com">
                </div>

                <div class="vgt-fe-group">
                    <label class="vgt-fe-label">Threat Vector Class</label>
                    <input type="text" name="vgt_vector" class="vgt-fe-input" required placeholder="e.g. sqli, xss, logical-flaw">
                </div>

                <div class="vgt-fe-group">
                    <label class="vgt-fe-label">Threat Scenario Details</label>
                    <textarea name="vgt_threat" class="vgt-fe-input vgt-fe-textarea" required placeholder="Describe the vulnerability structure..."></textarea>
                </div>

                <button type="submit" class="vgt-fe-btn" id="vgt-submit-btn">
                    <span class="vgt-fe-loader" id="vgt-loader"></span>
                    <span id="vgt-btn-text">Transmit Payload</span>
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
            btnText.innerText = 'ENCRYPTING...';
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
                msgBox.innerText = 'SYSTEM HALT: Network Failure.';
                msgBox.className = 'vgt-fe-msg error';
            } finally {
                btn.disabled = false;
                loader.style.display = 'none';
                btnText.innerText = 'Transmit Payload';
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
            wp_die('VGT SYSTEM HALT: Unauthorized clearance level.');
        }

        global $wpdb;
        $table = $wpdb->prefix . VGT_Omega_DB::TABLE_NAME;
        if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
             VGT_Omega_DB::install();
        }

        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $total_audits = VGT_Omega_DB::get_total_count();
        $total_pages = ceil($total_audits / $per_page);
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
            .vgt-threat { max-width: 300px; line-height: 1.5; color: var(--vgt-text-muted); }
            .vgt-btn-danger { display: inline-flex; align-items: center; justify-content: center; padding: 0.5rem; border: 1px solid rgba(239, 68, 68, 0.3); color: var(--vgt-red); border-radius: 0.25rem; text-decoration: none; transition: all 0.2s; background: transparent; cursor: pointer; }
            .vgt-btn-danger:hover { background: var(--vgt-red); color: #fff; }
            .vgt-shortcode-box { margin-top: 1rem; padding: 1rem; background: rgba(212, 175, 55, 0.05); border: 1px solid var(--vgt-gold); border-radius: 0.5rem; color: var(--vgt-gold); display: flex; align-items: center; justify-content: space-between; }
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
                        <div>SYSTEM INTEGRITY: <span class="text-green">300% (DIAMANT STATUS)</span></div>
                        <div>ENCRYPTION: AES-256-GCM</div>
                    </div>
                </header>

                <div class="vgt-stats-grid">
                    <div class="vgt-card">
                        <div class="vgt-card-icon text-gold"><?php echo self::get_svg('database'); ?></div>
                        <div class="vgt-mono vgt-title-xs">Total Audits Secured</div>
                        <div class="vgt-card-value"><?php echo esc_html($total); ?></div>
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
                                <th>Domain / Com-Link</th>
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
                                    $dec_domain = VGT_Omega_Crypto::decrypt($audit->domain);
                                    $dec_email  = VGT_Omega_Crypto::decrypt($audit->email);
                                    $dec_vector = VGT_Omega_Crypto::decrypt($audit->vector);
                                    $dec_threat = VGT_Omega_Crypto::decrypt($audit->threat);
                                    $dec_ip     = VGT_Omega_Crypto::decrypt($audit->ip_origin);
                                ?>
                                    <tr>
                                        <td class="vgt-mono vgt-title-xs"><?php echo esc_html(wp_date('d.m.Y H:i', strtotime($audit->created_at))); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url($dec_domain); ?>" target="_blank" class="vgt-link"><?php echo esc_html($dec_domain); ?></a>
                                            <div class="vgt-mono vgt-title-xs text-gold vgt-flex-center">
                                                <?php echo self::get_svg('mail'); ?>
                                                <a href="mailto:<?php echo esc_attr($dec_email); ?>" style="color: inherit; text-decoration: none;"><?php echo esc_html($dec_email); ?></a>
                                            </div>
                                        </td>
                                        <td><span class="vgt-badge vgt-mono"><?php echo esc_html($dec_vector); ?></span></td>
                                        <td><div class="vgt-threat"><?php echo nl2br(esc_html($dec_threat)); ?></div></td>
                                        <td class="text-right vgt-mono vgt-title-xs"><?php echo esc_html($dec_ip); ?></td>
                                        <td class="text-right">
                                            <?php $delete_url = wp_nonce_url(admin_url('admin-post.php?action=vgt_delete_audit&id=' . $audit->id), 'vgt_delete_audit_nonce'); ?>
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
        add_menu_page('VGT Vault', 'VGT Vault', 'manage_options', 'vgt-omega-vault', [VGT_Omega_UI::class, 'render'], 'dashicons-shield', 3);
    }

    public static function handle_deletion(): void {
        if (!current_user_can('manage_options')) {
            wp_die('VGT SYSTEM HALT: Unauthorized clearance level.');
        }

        check_admin_referer('vgt_delete_audit_nonce');

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id > 0) {
            VGT_Omega_DB::delete($id);
        }

        wp_redirect(admin_url('admin.php?page=vgt-omega-vault'));
        exit;
    }
}

// System Initialisierung
VGT_Omega_Bootstrapper::ignite();