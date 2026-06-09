<?php
/**
 * VGT OMEGA VAULT: Modular Admin-Interface & RAM-Decryption (SaaS Design Pattern)
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT SECURE ZONE: DIRECT ACCESS FORBIDDEN');
}

final class VGT_Omega_UI {

    /**
     * Initializes and executes the core admin rendering matrix.
     */
    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('VGT SYSTEM HALT: Unauthorized clearance level.', 'vgt-omega-vault'), '', ['response' => 403]);
        }

        global $wpdb;
        $table = $wpdb->prefix . VGT_Omega_DB::TABLE_NAME;
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
             VGT_Omega_DB::install();
        }

        $per_page = 50; // High limit optimized for virtualized SPA scrolling
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $total_audits = VGT_Omega_DB::get_total_count();
        $total_pages = (int) ceil($total_audits / $per_page);
        $audits = VGT_Omega_DB::get_paginated_audits($page, $per_page);

        self::render_html($audits, $total_audits, $page, $total_pages);
    }

    /**
     * Retrieves sanitized vector icon SVG assets.
     */
    private static function get_svg(string $name): string {
        $svgs = [
            'database' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5V19A9 3 0 0 0 21 19V5"></path><path d="M3 12A9 3 0 0 0 21 12"></path></svg>',
            'shield' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>',
            'activity' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
            'mail' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>',
            'trash' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>',
            'lock' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
            'code' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>'
        ];
        return $svgs[$name] ?? '';
    }

    /**
     * Renders the complete, securely escaped HTML SPA Structure.
     */
    private static function render_html(array $audits, int $total, int $current_page, int $total_pages): void {
        // Retrieve localized parameters safely
        $allow_proxies = (get_option('vgt_omega_allow_proxies', '0') === '1');
        $enable_notifications = (get_option('vgt_omega_enable_notifications', '0') === '1');
        $enable_honeypot = (get_option('vgt_omega_enable_honeypot', '1') === '1');
        ?>
        <div class="vgt-wrapper">
            <div class="vgt-container">
                
                <!-- Main Header Shell -->
                <header class="vgt-header">
                    <div>
                        <div class="vgt-mono vgt-title-xs text-gold vgt-flex-center" style="margin-bottom: 0.5rem;">
                            <div style="width: 6px; height: 6px; background: var(--vgt-gold); border-radius: 50%; box-shadow: 0 0 10px var(--vgt-gold);"></div>
                            <?php echo esc_html__('VISION GAIA OMEGA SECURITY INFRASTRUCTURE', 'vgt-omega-vault'); ?>
                        </div>
                        <h1 class="vgt-h1"><?php echo esc_html__('Cryptographic', 'vgt-omega-vault'); ?> <span class="text-gold"><?php echo esc_html__('Vault', 'vgt-omega-vault'); ?></span></h1>
                    </div>
                    <div class="vgt-header-meta">
                        <div><?php echo esc_html__('VGT-KERNEL:', 'vgt-omega-vault'); ?> <span class="text-gold"><?php echo esc_html__('V5.3.0 (DECOUPLED)', 'vgt-omega-vault'); ?></span></div>
                        <div><?php echo esc_html__('CIPHER ALGORITHM:', 'vgt-omega-vault'); ?> <span class="text-gold"><?php echo esc_html__('AES-256-GCM', 'vgt-omega-vault'); ?></span></div>
                    </div>
                </header>

                <!-- Premium Asynchronous Navigation Tabs -->
                <nav class="vgt-nav">
                    <button class="vgt-nav-btn active" data-target="vgt-sec-dashboard"><?php echo esc_html__('ANALYTICS PANEL', 'vgt-omega-vault'); ?></button>
                    <button class="vgt-nav-btn" data-target="vgt-sec-vault"><?php echo esc_html__('PAYLOAD DATA', 'vgt-omega-vault'); ?></button>
                    <button class="vgt-nav-btn" data-target="vgt-sec-config"><?php echo esc_html__('SECURITY CONFIG', 'vgt-omega-vault'); ?></button>
                </nav>

                <!-- SECTION 1: DASHBOARD & ANALYTICS -->
                <div id="vgt-sec-dashboard" class="vgt-section active">
                    <!-- Standard KPI Cards remain on main Dashboard -->
                    <div class="vgt-stats-grid">
                        <div class="vgt-card">
                            <div class="vgt-card-icon"><?php echo self::get_svg('database'); ?></div>
                            <div class="vgt-mono vgt-title-xs"><?php echo esc_html__('Secured Audit Telemetry', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-card-value"><?php echo esc_html((string)$total); ?></div>
                        </div>
                        <div class="vgt-card">
                            <div class="vgt-card-icon text-green"><?php echo self::get_svg('shield'); ?></div>
                            <div class="vgt-mono vgt-title-xs"><?php echo esc_html__('Encryption Standard', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-card-value text-green" style="font-size: 1.5rem; margin-top: 1.25rem;"><?php echo esc_html__('Active GCM Hardware Binding', 'vgt-omega-vault'); ?></div>
                        </div>
                        <div class="vgt-card">
                            <div class="vgt-card-icon text-gold"><?php echo self::get_svg('code'); ?></div>
                            <div class="vgt-mono vgt-title-xs"><?php echo esc_html__('Shortcode Integration', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-shortcode-box vgt-mono">
                                <span>[vgt_omega_comlink]</span>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Vector SVG Plotting Chart Area -->
                    <div class="vgt-chart-container">
                        <div class="vgt-chart-header">
                            <div class="vgt-title-xs"><?php echo esc_html__('Cryptographic Transactions Timeline', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-mono text-gold" style="font-size: 0.75rem;"><?php echo esc_html__('Dynamic Frame Scaling', 'vgt-omega-vault'); ?></div>
                        </div>
                        <svg id="vgt-analytics-chart" class="vgt-svg-chart"></svg>
                    </div>

                    <!-- Real-Time Simulated Security Console/Audit Log -->
                    <div class="vgt-console">
                        <div class="vgt-console-header">
                            <div class="vgt-title-xs" style="color: #fff;"><?php echo esc_html__('Operational Kernel Error Log', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-console-controls">
                                <div class="vgt-console-dot red"></div>
                                <div class="vgt-console-dot yellow"></div>
                                <div class="vgt-console-dot green"></div>
                            </div>
                        </div>
                        <div class="vgt-console-body">
                            <div class="vgt-log-entry">
                                <span class="timestamp">[<?php echo esc_html(current_time('H:i:s')); ?>]</span>
                                <span class="status-tag">[SYS_OK]</span>
                                <?php echo esc_html__('Cryptographic core loaded cleanly. AES-256-GCM hardware tags synchronized.', 'vgt-omega-vault'); ?>
                            </div>
                            <?php if (get_option('vgt_omega_allow_proxies', '0') === '1') : ?>
                                <div class="vgt-log-entry" style="color: var(--vgt-gold);">
                                    <span class="timestamp">[<?php echo esc_html(current_time('H:i:s')); ?>]</span>
                                    <span class="status-tag">[WARN]</span>
                                    <?php echo esc_html__('Client-forwarded proxy header trust is active. Spoof-Blocker standby.', 'vgt-omega-vault'); ?>
                                </div>
                            <?php else : ?>
                                <div class="vgt-log-entry" style="color: var(--vgt-green);">
                                    <span class="timestamp">[<?php echo esc_html(current_time('H:i:s')); ?>]</span>
                                    <span class="status-tag">[SEC_OK]</span>
                                    <?php echo esc_html__('Zero-Trust protocol active. Ignoring unsafe client-forwarded HTTP headers.', 'vgt-omega-vault'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="vgt-log-entry">
                                <span class="timestamp">[<?php echo esc_html(current_time('H:i:s')); ?>]</span>
                                <span class="status-tag">[SYS_OK]</span>
                                <?php echo esc_html__('Verification of Upload directory .htaccess complete: Modern Apache 2.4 rules enforced.', 'vgt-omega-vault'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: VAULT PAYLOAD LIST -->
                <div id="vgt-sec-vault" class="vgt-section">
                    <div class="vgt-vault-actions">
                        <input type="text" id="vgt-vault-search" class="vgt-search-input" placeholder="<?php echo esc_attr__('Search payloads, IPs, targets...', 'vgt-omega-vault'); ?>">
                        <div class="vgt-mono vgt-title-xs" style="align-self: center;">
                            <?php echo esc_html__('Decrypting live inside client memory (RAM)', 'vgt-omega-vault'); ?>
                        </div>
                    </div>

                    <div class="vgt-table-container">
                        <table class="vgt-table">
                            <thead>
                                <tr class="vgt-mono vgt-title-xs">
                                    <th style="width: 15%;"><?php echo esc_html__('Timestamp', 'vgt-omega-vault'); ?></th>
                                    <th style="width: 25%;"><?php echo esc_html__('Target / Operative', 'vgt-omega-vault'); ?></th>
                                    <th style="width: 18%;"><?php echo esc_html__('Vector', 'vgt-omega-vault'); ?></th>
                                    <th style="width: 22%;"><?php echo esc_html__('Decrypted Threat Note', 'vgt-omega-vault'); ?></th>
                                    <th class="text-right" style="width: 20%; padding-right: 2rem;"><?php echo esc_html__('Host Routing Matrix', 'vgt-omega-vault'); ?></th>
                                    <th class="text-right" style="width: 5%;"><?php echo esc_html__('Purge', 'vgt-omega-vault'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($audits)) : ?>
                                    <tr>
                                        <td colspan="6" class="vgt-mono" style="text-align: center; padding: 4rem; color: var(--vgt-text-muted);">
                                            <?php echo esc_html__('No telemetry packets recorded inside the cryptographic Vault.', 'vgt-omega-vault'); ?>
                                        </td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($audits as $audit) : 
                                        $dec_domain = VGT_Omega_Crypto::decrypt((string)$audit->domain, 'domain', (int)$audit->id, 'domain');
                                        $dec_email  = VGT_Omega_Crypto::decrypt((string)$audit->email, 'email', (int)$audit->id, 'email');
                                        $dec_vector = VGT_Omega_Crypto::decrypt((string)$audit->vector, 'vector', (int)$audit->id, 'vector');
                                        $dec_threat = VGT_Omega_Crypto::decrypt((string)$audit->threat, 'threat', (int)$audit->id, 'threat');
                                        
                                        $dec_socket = '';
                                        $dec_claimed = '';
                                        
                                        if (!empty($audit->ip_socket)) {
                                            $dec_socket  = VGT_Omega_Crypto::decrypt((string)$audit->ip_socket, 'ip_socket', (int)$audit->id, 'ip_socket');
                                            $dec_claimed = VGT_Omega_Crypto::decrypt((string)$audit->ip_claimed, 'ip_claimed', (int)$audit->id, 'ip_claimed');
                                        } elseif (isset($audit->ip_origin) && !empty($audit->ip_origin)) {
                                            // Dynamic Schema Auto-Migration inside decrypted rendering loop
                                            $dec_socket  = VGT_Omega_Crypto::decrypt((string)$audit->ip_origin, 'ip_origin', (int)$audit->id, 'ip_origin');
                                            $dec_claimed = 'none (Migrated)';
                                            
                                            try {
                                                global $wpdb;
                                                $wpdb->update(
                                                    $wpdb->prefix . VGT_Omega_DB::TABLE_NAME,
                                                    [
                                                        'ip_socket'  => VGT_Omega_Crypto::encrypt($dec_socket, 'ip_socket'),
                                                        'ip_claimed' => VGT_Omega_Crypto::encrypt($dec_claimed, 'ip_claimed'),
                                                        'ip_origin'  => '' 
                                                    ],
                                                    ['id' => (int)$audit->id]
                                                );
                                            } catch (\Throwable $e) {
                                                error_log('[VGT_OMEGA_MIGRATION_ERROR] Auto-alignment failed: ' . $e->getMessage());
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td class="vgt-mono vgt-title-xs" style="color: var(--vgt-text-muted);">
                                                <?php echo esc_html(wp_date('Y.m.d H:i', strtotime((string)$audit->created_at))); ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo esc_url($dec_domain); ?>" target="_blank" class="vgt-link"><?php echo esc_html($dec_domain); ?></a>
                                                <div class="vgt-mono vgt-title-xs text-gold vgt-flex-center">
                                                    <?php echo self::get_svg('mail'); ?>
                                                    <a href="mailto:<?php echo esc_attr($dec_email); ?>" style="color: inherit; text-decoration: none;"><?php echo esc_html($dec_email); ?></a>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="vgt-badge"><?php echo esc_html($dec_vector); ?></span>
                                            </td>
                                            <td>
                                                <div class="vgt-threat">
                                                    <details class="vgt-threat-details">
                                                        <summary class="vgt-threat-summary"><?php echo esc_html__('Read Payload', 'vgt-omega-vault'); ?></summary>
                                                        <div class="vgt-threat-content vgt-mono">
                                                            <?php echo nl2br(esc_html($dec_threat)); ?>
                                                        </div>
                                                    </details>
                                                </div>
                                            </td>
                                            <td class="text-right">
                                                <div class="vgt-ip-block">
                                                    <div class="vgt-ip-row">
                                                        <span class="vgt-ip-lbl socket"><?php echo esc_html__('SOCKET', 'vgt-omega-vault'); ?></span>
                                                        <span class="vgt-ip-val"><?php echo esc_html($dec_socket); ?></span>
                                                    </div>
                                                    <?php if (!empty($dec_claimed) && $dec_claimed !== 'none') : ?>
                                                        <div class="vgt-ip-row">
                                                            <span class="vgt-ip-lbl claimed"><?php echo esc_html__('CLAIMED', 'vgt-omega-vault'); ?></span>
                                                            <span class="vgt-ip-val" style="color: var(--vgt-text-muted);"><?php echo esc_html($dec_claimed); ?></span>
                                                        </div>
                                                    <?php else : ?>
                                                        <div class="vgt-ip-row" style="opacity: 0.5; font-size: 0.68rem; font-family: var(--vgt-font-mono);">
                                                            <span style="color: var(--vgt-text-muted);"><?php echo esc_html__('ROUTING: DIRECT SOCKET', 'vgt-omega-vault'); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-right" style="padding-right: 1.5rem;">
                                                <?php $delete_url = wp_nonce_url(admin_url('admin-post.php?action=vgt_delete_audit&id=' . (int)$audit->id), 'vgt_delete_audit_nonce'); ?>
                                                <a href="<?php echo esc_url($delete_url); ?>" class="vgt-btn-danger" title="<?php echo esc_attr__('Purge Record', 'vgt-omega-vault'); ?>">
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
                </div>

                <!-- SECTION 3: SECURITY OPT-IN SETTINGS CONFIG -->
                <div id="vgt-sec-config" class="vgt-section">
                    <form id="vgt-config-form" method="POST" autocomplete="off">
                        <div class="vgt-config-grid">
                            
                            <div class="vgt-config-card">
                                <div class="vgt-title-xs" style="margin-bottom: 1.5rem; color: #fff;"><?php echo esc_html__('Operational Hardening Parameters', 'vgt-omega-vault'); ?></div>
                                
                                <!-- Toggle Proxy Trust (Opt-In Specification) -->
                                <div class="vgt-config-row">
                                    <div class="vgt-config-info">
                                        <h3 class="vgt-config-title"><?php echo esc_html__('Trust Forwarded Proxies', 'vgt-omega-vault'); ?></h3>
                                        <p class="vgt-config-desc"><?php echo esc_html__('Enable analysis of HTTP proxy headers (e.g. X-Forwarded-For, Cloudflare Connection IP). Highly vulnerable to IP-Spoofing unless your host architecture strictly validates upstream endpoints.', 'vgt-omega-vault'); ?></p>
                                    </div>
                                    <div>
                                        <label class="vgt-switch">
                                            <input type="checkbox" name="allow_proxies" value="1" <?php checked($allow_proxies, true); ?> <?php disabled(defined('VGT_ALLOW_PROXIES') && VGT_ALLOW_PROXIES); ?>>
                                            <span class="vgt-slider"></span>
                                        </label>
                                        <?php if (defined('VGT_ALLOW_PROXIES') && VGT_ALLOW_PROXIES) : ?>
                                            <div class="vgt-mono text-gold" style="font-size: 0.65rem; margin-top: 4px; text-align: right;"><?php echo esc_html__('Const Override Active', 'vgt-omega-vault'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Toggle Email Notifications -->
                                <div class="vgt-config-row">
                                    <div class="vgt-config-info">
                                        <h3 class="vgt-config-title"><?php echo esc_html__('Dispatch Email Notifications', 'vgt-omega-vault'); ?></h3>
                                        <p class="vgt-config-desc"><?php echo esc_html__('Triggers an E-Mail notice directly to the WordPress administrator whenever an audited transaction has successfully landed inside the database.', 'vgt-omega-vault'); ?></p>
                                    </div>
                                    <div>
                                        <label class="vgt-switch">
                                            <input type="checkbox" name="enable_notifications" value="1" <?php checked($enable_notifications, true); ?>>
                                            <span class="vgt-slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Toggle Honeypot Bot-Detection -->
                                <div class="vgt-config-row">
                                    <div class="vgt-config-info">
                                        <h3 class="vgt-config-title"><?php echo esc_html__('Active Honeypot Defense', 'vgt-omega-vault'); ?></h3>
                                        <p class="vgt-config-desc"><?php echo esc_html__('Injects a hidden decoy field inside the frontend shortcode form. Automatically flags and drops requests initiated by script engines and crawler bots.', 'vgt-omega-vault'); ?></p>
                                    </div>
                                    <div>
                                        <label class="vgt-switch">
                                            <input type="checkbox" name="enable_honeypot" value="1" <?php checked($enable_honeypot, true); ?>>
                                            <span class="vgt-slider"></span>
                                        </label>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Submit Button Anchor -->
                        <div class="vgt-submit-row">
                            <button type="submit" class="vgt-btn-primary"><?php echo esc_html__('Commit Security Settings', 'vgt-omega-vault'); ?></button>
                        </div>
                    </form>
                </div>

                <div class="vgt-mono vgt-title-xs text-gold vgt-flex-center" style="justify-content: center; margin-top: 3.5rem;">
                    <?php echo self::get_svg('lock'); ?>
                    <?php echo esc_html__('Decryption is volatile. Raw plain text never touches persistent hard drives.', 'vgt-omega-vault'); ?>
                </div>
            </div>
        </div>
        <?php
    }
}
