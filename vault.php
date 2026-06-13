<?php
/**
 * Plugin Name: VGT OMEGA VAULT
 * Plugin URI: https://visiongaiatechnology.de
 * Description: Cryptographic Data Safe & Secure Com-Link Endpoint. Zero-Dependency, O(n) Optimized, AES-256-GCM, CSRF-Hardened.
 * Version: 5.3.0
 * Author: VisionGaia Technology Intelligence System
 * Requires PHP: 8.0
 * License: AGPL-3.0-or-later
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT SECURE ZONE: DIRECT ACCESS FORBIDDEN');
}

/**
 * Explicit Proxy-Trust-Configuration (Hardening V5.3.0)
 * Disabled by default to prevent spoofing. Override in wp-config.php if required.
 */
if (!defined('VGT_ALLOW_PROXIES')) {
    define('VGT_ALLOW_PROXIES', false);
}

// Absolute Path Definitions
define('VGT_OMEGA_PATH', plugin_dir_path(__FILE__));
define('VGT_OMEGA_URL', plugin_dir_url(__FILE__));

// Modular Kernel Loader
require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-crypto.php';
require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-db.php';
require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-api.php';
require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-frontend.php';
require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-ui.php';

/**
 * ==============================================================================
 * KERNEL BOOTSTRAPPER & EVENT REGISTRATION
 * ==============================================================================
 */
final class VGT_Omega_Bootstrapper {
    
    public static function ignite(): void {
        register_activation_hook(__FILE__, [VGT_Omega_DB::class, 'install']);
        
        // Auto-Migration & Integrity checks
        add_action('init', [self::class, 'maybe_upgrade_db'], 5);
        add_action('admin_init', [VGT_Omega_Crypto::class, 'verify_vault_integrity']);
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        
        // API Endpoints (Legacy)
        add_action('wp_ajax_vgt_omega_audit_request', [VGT_Omega_API::class, 'handle_request']);
        add_action('wp_ajax_nopriv_vgt_omega_audit_request', [VGT_Omega_API::class, 'handle_request']);
        add_action('admin_post_vgt_delete_audit', [self::class, 'handle_deletion']);
        
        // API Endpoints (New Builder)
        add_action('wp_ajax_vgt_save_form_builder', [VGT_Omega_API::class, 'handle_save_form']);
        add_action('wp_ajax_vgt_delete_form', [VGT_Omega_API::class, 'handle_delete_form']);
        add_action('wp_ajax_vgt_submit_builder_form', [VGT_Omega_API::class, 'handle_submit_builder_form']);
        add_action('wp_ajax_nopriv_vgt_submit_builder_form', [VGT_Omega_API::class, 'handle_submit_builder_form']);
        add_action('wp_ajax_vgt_get_submissions', [VGT_Omega_API::class, 'handle_get_submissions']);
        add_action('wp_ajax_vgt_delete_submission', [VGT_Omega_API::class, 'handle_delete_submission']);

        // Asynchronous Settings Saving (AJAX)
        add_action('wp_ajax_vgt_save_config', [self::class, 'handle_config_save']);

        // Frontend Com-Link Generator (Legacy & New Builder)
        add_shortcode('vgt_omega_comlink', [VGT_Omega_Frontend::class, 'render_shortcode']);
        add_shortcode('vgt_omega_form', [VGT_Omega_Frontend::class, 'render_form_shortcode']);
    }

    /**
     * Self-healing database migrator. Triggers seamlessly on system load
     * if the files are replaced without manual deactivation/reactivation.
     */
    public static function maybe_upgrade_db(): void {
        $current_version = '5.4.1';
        $installed_version = get_option('vgt_omega_db_version', '0');
        
        if (version_compare($installed_version, $current_version, '<')) {
            VGT_Omega_DB::install();
            update_option('vgt_omega_db_version', $current_version);
        }
    }

    /**
     * Enqueues specialized, decoupled CSS and JS asset payloads.
     */
    public static function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, 'vgt-omega-vault') === false) {
            return;
        }

        wp_enqueue_style(
            'vgt-vault-admin-style',
            esc_url(VGT_OMEGA_URL . 'assets/css/vgt-vault-admin.css'),
            [],
            '5.4.1'
        );

        wp_enqueue_script(
            'vgt-vault-admin-script',
            esc_url(VGT_OMEGA_URL . 'assets/js/vgt-vault-admin.js'),
            [],
            '5.4.1',
            true
        );

        // Fetch metrics timeline for secure client-side graph execution
        global $wpdb;
        $table = $wpdb->prefix . VGT_Omega_DB::TABLE_NAME;
        
        // Zero-Complexity grouped dates fetch for the SVG chart
        $timeline_data = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $results = $wpdb->get_results(
                "SELECT DATE(created_at) as event_date, COUNT(id) as event_count 
                 FROM $table 
                 GROUP BY DATE(created_at) 
                 ORDER BY event_date ASC 
                 LIMIT 14"
            );
            foreach ($results as $row) {
                $timeline_data[] = [
                    'date' => esc_js($row->event_date),
                    'count' => (int) $row->event_count
                ];
            }
        }

        wp_localize_script('vgt-vault-admin-script', 'vgtAdminParams', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'saveNonce' => wp_create_nonce('vgt_save_config_nonce'),
            'timeline' => $timeline_data,
            'confirmMsg' => esc_html__('CRITICAL WARNING:\n\nThis record will be permanently purged and mathematically wiped from the database.\n\nProceed?', 'vgt-omega-vault')
        ]);
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

    /**
     * AJAX handler to safely update operational configurations without refreshing the page.
     */
    public static function handle_config_save(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized clearance level.', 'vgt-omega-vault')], 403);
        }

        check_ajax_referer('vgt_save_config_nonce', 'security');

        $allow_proxies = isset($_POST['allow_proxies']) && $_POST['allow_proxies'] === '1' ? '1' : '0';
        update_option('vgt_omega_allow_proxies', $allow_proxies);

        // Save additional auxiliary settings
        $enable_notifications = isset($_POST['enable_notifications']) && $_POST['enable_notifications'] === '1' ? '1' : '0';
        update_option('vgt_omega_enable_notifications', $enable_notifications);

        $enable_honeypot = isset($_POST['enable_honeypot']) && $_POST['enable_honeypot'] === '1' ? '1' : '0';
        update_option('vgt_omega_enable_honeypot', $enable_honeypot);

        wp_send_json_success(['message' => esc_html__('Configuration updated successfully.', 'vgt-omega-vault')]);
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

// System Activation
VGT_Omega_Bootstrapper::ignite();
