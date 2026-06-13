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
        
        // Ensure tables are installed
        $table = $wpdb->prefix . VGT_Omega_DB::TABLE_NAME;
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
             VGT_Omega_DB::install();
        }

        $per_page = 50;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $total_audits = VGT_Omega_DB::get_total_count();
        $total_pages = (int) ceil($total_audits / $per_page);
        $audits = VGT_Omega_DB::get_paginated_audits($page, $per_page);

        // Fetch new forms directory
        $forms = VGT_Omega_DB::get_all_forms();

        self::render_html($audits, $total_audits, $page, $total_pages, $forms);
    }

    /**
     * Retrieves sanitized vector icon SVG assets.
     */
    private static function get_svg(string $name): string {
        $svgs = [
            'database' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5V19A9 3 0 0 0 21 19V5"></path><path d="M3 12A9 3 0 0 0 21 12"></path></svg>',
            'shield' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>',
            'activity' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
            'mail' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>',
            'trash' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>',
            'lock' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
            'code' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
            'plus' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
            'edit' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
            'arrow-left' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>',
            'eye' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
        ];
        return $svgs[$name] ?? '';
    }

    /**
     * Renders the complete HTML dashboard interface.
     */
    private static function render_html(array $audits, int $total, int $current_page, int $total_pages, array $forms): void {
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
                            <div class="vgt-pulse-dot"></div>
                            <?php echo esc_html__('VISION GAIA OMEGA SECURITY INFRASTRUCTURE', 'vgt-omega-vault'); ?>
                        </div>
                        <h1 class="vgt-h1"><?php echo esc_html__('Omega', 'vgt-omega-vault'); ?> <span class="text-gold"><?php echo esc_html__('Funnels', 'vgt-omega-vault'); ?></span></h1>
                    </div>
                    <div class="vgt-header-meta">
                        <div><?php echo esc_html__('BUILDER KERNEL:', 'vgt-omega-vault'); ?> <span class="text-gold"><?php echo esc_html__('V6.0.0 (D&D ENGINE)', 'vgt-omega-vault'); ?></span></div>
                        <div><?php echo esc_html__('CRYPTOGRAPHY:', 'vgt-omega-vault'); ?> <span class="text-gold"><?php echo esc_html__('AES-256-GCM + AAD', 'vgt-omega-vault'); ?></span></div>
                    </div>
                </header>

                <!-- Premium Navigation Tabs -->
                <nav class="vgt-nav">
                    <button class="vgt-nav-btn active" data-target="vgt-sec-dashboard"><?php echo esc_html__('ANALYTICS', 'vgt-omega-vault'); ?></button>
                    <button class="vgt-nav-btn" data-target="vgt-sec-forms"><?php echo esc_html__('FORMS & FUNNELS', 'vgt-omega-vault'); ?></button>
                    <button class="vgt-nav-btn" data-target="vgt-sec-config"><?php echo esc_html__('SECURITY CONFIG', 'vgt-omega-vault'); ?></button>
                </nav>

                <!-- SECTION 1: DASHBOARD & ANALYTICS -->
                <div id="vgt-sec-dashboard" class="vgt-section active">
                    <div class="vgt-stats-grid">
                        <div class="vgt-card">
                            <div class="vgt-card-icon"><?php echo self::get_svg('database'); ?></div>
                            <div class="vgt-mono vgt-title-xs"><?php echo esc_html__('Secured Forms / Funnels', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-card-value"><?php echo count($forms); ?></div>
                        </div>
                        <div class="vgt-card">
                            <div class="vgt-card-icon text-green"><?php echo self::get_svg('shield'); ?></div>
                            <div class="vgt-mono vgt-title-xs"><?php echo esc_html__('Hardware GCM Encryption', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-card-value text-green" style="font-size: 1.4rem; margin-top: 1.25rem;"><?php echo esc_html__('Domain & Form Bindings', 'vgt-omega-vault'); ?></div>
                        </div>
                        <div class="vgt-card">
                            <div class="vgt-card-icon text-gold"><?php echo self::get_svg('code'); ?></div>
                            <div class="vgt-mono vgt-title-xs"><?php echo esc_html__('Shortcode Deployer', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-shortcode-box vgt-mono">
                                <span>[vgt_omega_form id="X"]</span>
                            </div>
                        </div>
                    </div>

                    <!-- SVG Timeline Plotting Chart -->
                    <div class="vgt-chart-container">
                        <div class="vgt-chart-header">
                            <div class="vgt-title-xs"><?php echo esc_html__('Cryptographic Transactions Timeline', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-mono text-gold" style="font-size: 0.75rem;"><?php echo esc_html__('Dynamic Scaling Frame', 'vgt-omega-vault'); ?></div>
                        </div>
                        <svg id="vgt-analytics-chart" class="vgt-svg-chart"></svg>
                    </div>

                    <!-- Operational Error Log & Console -->
                    <div class="vgt-console">
                        <div class="vgt-console-header">
                            <div class="vgt-title-xs" style="color: #fff;"><?php echo esc_html__('Operational Kernel Log', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-console-controls">
                                <div class="vgt-console-dot red"></div>
                                <div class="vgt-console-dot yellow"></div>
                                <div class="vgt-console-dot green"></div>
                            </div>
                        </div>
                        <div class="vgt-console-body">
                            <div class="vgt-log-entry"><span class="timestamp">[<?php echo esc_html(current_time('H:i:s')); ?>]</span> <span class="status-tag">[SYS_OK]</span> D&D Funnel Engine loaded.</div>
                            <div class="vgt-log-entry"><span class="timestamp">[<?php echo esc_html(current_time('H:i:s')); ?>]</span> <span class="status-tag">[SYS_OK]</span> Dual-Defense CSRF-Shield initialized.</div>
                            <?php if ($allow_proxies) : ?>
                                <div class="vgt-log-entry" style="color: var(--vgt-gold);"><span class="timestamp">[<?php echo esc_html(current_time('H:i:s')); ?>]</span> <span class="status-tag">[WARN]</span> Forwarded proxy headers trusted. Cloudflare verification standby.</div>
                            <?php else : ?>
                                <div class="vgt-log-entry" style="color: var(--vgt-green);"><span class="timestamp">[<?php echo esc_html(current_time('H:i:s')); ?>]</span> <span class="status-tag">[SEC_OK]</span> Zero-Trust Proxy Protocol active. Socket IP ground-truth enforced.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Collapsible Legacy Audits Section -->
                    <div class="vgt-legacy-audits-card">
                        <details class="vgt-threat-details">
                            <summary class="vgt-threat-summary vgt-mono vgt-title-xs" style="color: var(--vgt-text-muted); cursor: pointer;"><?php echo esc_html__('Show Legacy Comlink Audits', 'vgt-omega-vault'); ?> (<?php echo $total; ?>)</summary>
                            <div class="vgt-table-container" style="margin-top: 1rem;">
                                <table class="vgt-table">
                                    <thead>
                                        <tr class="vgt-mono vgt-title-xs">
                                            <th style="width: 15%;"><?php echo esc_html__('Timestamp', 'vgt-omega-vault'); ?></th>
                                            <th style="width: 25%;"><?php echo esc_html__('Target / Operative', 'vgt-omega-vault'); ?></th>
                                            <th style="width: 18%;"><?php echo esc_html__('Vector', 'vgt-omega-vault'); ?></th>
                                            <th style="width: 22%;"><?php echo esc_html__('Decrypted Threat Note', 'vgt-omega-vault'); ?></th>
                                            <th class="text-right" style="width: 20%;"><?php echo esc_html__('IP Socket Routing', 'vgt-omega-vault'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($audits)) : ?>
                                            <tr><td colspan="5" class="vgt-mono" style="text-align: center; color: var(--vgt-text-muted); padding: 2rem;">No legacy logs found.</td></tr>
                                        <?php else : ?>
                                            <?php foreach ($audits as $audit) : 
                                                $dec_domain = VGT_Omega_Crypto::decrypt((string)$audit->domain, 'domain');
                                                $dec_email  = VGT_Omega_Crypto::decrypt((string)$audit->email, 'email');
                                                $dec_vector = VGT_Omega_Crypto::decrypt((string)$audit->vector, 'vector');
                                                $dec_threat = VGT_Omega_Crypto::decrypt((string)$audit->threat, 'threat');
                                                $dec_socket = VGT_Omega_Crypto::decrypt((string)$audit->ip_socket, 'ip_socket');
                                            ?>
                                                <tr>
                                                    <td class="vgt-mono vgt-title-xs"><?php echo esc_html(wp_date('m.d H:i', strtotime((string)$audit->created_at))); ?></td>
                                                    <td>
                                                        <span class="vgt-mono"><?php echo esc_html($dec_domain); ?></span><br/>
                                                        <span class="vgt-mono text-gold" style="font-size: 0.7rem;"><?php echo esc_html($dec_email); ?></span>
                                                    </td>
                                                    <td><span class="vgt-badge"><?php echo esc_html($dec_vector); ?></span></td>
                                                    <td><div style="max-height:60px; overflow-y:auto; font-family: monospace; font-size: 0.75rem;"><?php echo esc_html($dec_threat); ?></div></td>
                                                    <td class="text-right vgt-mono"><?php echo esc_html($dec_socket); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- SECTION 2: FORMS & FUNNELS DIRECTORY -->
                <div id="vgt-sec-forms" class="vgt-section">
                    <div class="vgt-forms-header-row">
                        <div class="vgt-mono vgt-title-xs"><?php echo esc_html__('Vault Directory', 'vgt-omega-vault'); ?></div>
                        <button type="button" class="vgt-btn-primary vgt-flex-center" id="vgt-btn-create-form" style="width: auto; gap: 0.5rem; margin-top: 0;">
                            <?php echo self::get_svg('plus'); ?>
                            <?php echo esc_html__('Create New Safe-Vault', 'vgt-omega-vault'); ?>
                        </button>
                    </div>

                    <div class="vgt-table-container">
                        <table class="vgt-table">
                            <thead>
                                <tr class="vgt-mono vgt-title-xs">
                                    <th style="width: 5%;">ID</th>
                                    <th style="width: 25%;"><?php echo esc_html__('Title', 'vgt-omega-vault'); ?></th>
                                    <th style="width: 15%;"><?php echo esc_html__('Type', 'vgt-omega-vault'); ?></th>
                                    <th style="width: 25%;"><?php echo esc_html__('Intake Shortcode', 'vgt-omega-vault'); ?></th>
                                    <th style="width: 15%;" class="text-center"><?php echo esc_html__('Submissions', 'vgt-omega-vault'); ?></th>
                                    <th style="width: 15%;" class="text-right"><?php echo esc_html__('Actions', 'vgt-omega-vault'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($forms)) : ?>
                                    <tr>
                                        <td colspan="6" class="vgt-mono" style="text-align: center; padding: 4rem; color: var(--vgt-text-muted);">
                                            <?php echo esc_html__('No Safe-Vault funnels or forms created yet.', 'vgt-omega-vault'); ?>
                                        </td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($forms as $f) : 
                                        $sub_count = VGT_Omega_DB::get_total_submissions_count((int)$f->id);
                                    ?>
                                        <tr data-form-row-id="<?php echo $f->id; ?>">
                                            <td class="vgt-mono text-gold"><?php echo $f->id; ?></td>
                                            <td class="vgt-mono font-bold"><?php echo esc_html($f->title); ?></td>
                                            <td>
                                                <span class="vgt-badge <?php echo $f->type === 'funnel' ? 'funnel-badge' : ''; ?>">
                                                    <?php echo strtoupper(esc_html($f->type)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="vgt-shortcode-copy-wrapper" title="<?php echo esc_attr__('Click to copy shortcode', 'vgt-omega-vault'); ?>" data-shortcode='[vgt_omega_form id="<?php echo $f->id; ?>"]'>
                                                    <code class="vgt-mono">[vgt_omega_form id="<?php echo $f->id; ?>"]</code>
                                                </div>
                                            </td>
                                            <td class="text-center vgt-mono">
                                                <span class="text-green"><?php echo $sub_count; ?></span>
                                            </td>
                                            <td class="text-right">
                                                <div class="vgt-row-actions" style="display:flex; justify-content: flex-end; gap:0.5rem;">
                                                    <button type="button" class="vgt-btn-row-action view-subs" data-id="<?php echo $f->id; ?>" data-title="<?php echo esc_attr($f->title); ?>" title="<?php echo esc_attr__('View Decrypted Submissions', 'vgt-omega-vault'); ?>">
                                                        <?php echo self::get_svg('eye'); ?>
                                                    </button>
                                                    <button type="button" class="vgt-btn-row-action edit-form" data-id="<?php echo $f->id; ?>" data-config="<?php echo esc_attr($f->config); ?>" data-title="<?php echo esc_attr($f->title); ?>" data-type="<?php echo esc_attr($f->type); ?>" title="<?php echo esc_attr__('Open Builder Workspace', 'vgt-omega-vault'); ?>">
                                                        <?php echo self::get_svg('edit'); ?>
                                                    </button>
                                                    <?php if ((int)$f->id === 1) : ?>
                                                        <button type="button" class="vgt-btn-row-action text-muted" style="cursor: not-allowed; opacity: 0.3;" title="<?php echo esc_attr__('System-Standard (Kann nicht gelöscht werden)', 'vgt-omega-vault'); ?>">
                                                            <?php echo self::get_svg('trash'); ?>
                                                        </button>
                                                    <?php else : ?>
                                                        <button type="button" class="vgt-btn-row-action delete-form text-red" data-id="<?php echo $f->id; ?>" title="<?php echo esc_attr__('Purge Form Structure', 'vgt-omega-vault'); ?>">
                                                            <?php echo self::get_svg('trash'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECTION 3: SECURITY OPT-IN SETTINGS CONFIG -->
                <div id="vgt-sec-config" class="vgt-section">
                    <form id="vgt-config-form" method="POST" autocomplete="off">
                        <div class="vgt-config-grid">
                            <div class="vgt-config-card">
                                <div class="vgt-title-xs" style="margin-bottom: 1.5rem; color: #fff;"><?php echo esc_html__('Operational Hardening Parameters', 'vgt-omega-vault'); ?></div>
                                
                                <div class="vgt-config-row">
                                    <div class="vgt-config-info">
                                        <h3 class="vgt-config-title"><?php echo esc_html__('Trust Forwarded Proxies', 'vgt-omega-vault'); ?></h3>
                                        <p class="vgt-config-desc"><?php echo esc_html__('Enable analysis of HTTP proxy headers (e.g. X-Forwarded-For, Cloudflare Connection IP). Includes automatic Cloudflare range checking.', 'vgt-omega-vault'); ?></p>
                                    </div>
                                    <div>
                                        <label class="vgt-switch">
                                            <input type="checkbox" name="allow_proxies" value="1" <?php checked($allow_proxies, true); ?> <?php disabled(defined('VGT_ALLOW_PROXIES') && VGT_ALLOW_PROXIES); ?>>
                                            <span class="vgt-slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="vgt-config-row">
                                    <div class="vgt-config-info">
                                        <h3 class="vgt-config-title"><?php echo esc_html__('Dispatch Email Notifications', 'vgt-omega-vault'); ?></h3>
                                        <p class="vgt-config-desc"><?php echo esc_html__('Triggers a secure, empty alert notice directly to the admin mail whenever a form has been submitted and GCM-encrypted.', 'vgt-omega-vault'); ?></p>
                                    </div>
                                    <div>
                                        <label class="vgt-switch">
                                            <input type="checkbox" name="enable_notifications" value="1" <?php checked($enable_notifications, true); ?>>
                                            <span class="vgt-slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="vgt-config-row">
                                    <div class="vgt-config-info">
                                        <h3 class="vgt-config-title"><?php echo esc_html__('Active Honeypot Defense', 'vgt-omega-vault'); ?></h3>
                                        <p class="vgt-config-desc"><?php echo esc_html__('Injects a hidden decoy field inside shortcodes to instantly block crawler engines.', 'vgt-omega-vault'); ?></p>
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

                        <div class="vgt-submit-row">
                            <button type="submit" class="vgt-btn-primary"><?php echo esc_html__('Commit Settings', 'vgt-omega-vault'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- SECTION 4: DRAG-AND-DROP BUILDER WORKSPACE (DYNAMIC WORKSPACE) -->
                <div id="vgt-sec-builder" class="vgt-section">
                    <div class="vgt-workspace-header">
                        <button type="button" class="vgt-btn-back vgt-flex-center" id="vgt-btn-builder-back">
                            <?php echo self::get_svg('arrow-left'); ?>
                            <span><?php echo esc_html__('Back to Directory', 'vgt-omega-vault'); ?></span>
                        </button>
                        <div class="vgt-workspace-title-input-wrapper">
                            <input type="text" id="vgt-builder-title" placeholder="<?php echo esc_attr__('Form/Funnel Title...', 'vgt-omega-vault'); ?>" value="" class="vgt-search-input" style="font-size: 1.1rem; width: 300px; padding: 0.5rem 1rem;">
                            <select id="vgt-builder-type" class="vgt-select-input" style="background:#111; color:#fff; border:1px solid #222; border-radius:4px; padding:0.5rem;">
                                <option value="form"><?php echo esc_html__('Standard Form', 'vgt-omega-vault'); ?></option>
                                <option value="funnel"><?php echo esc_html__('Multi-Step Funnel', 'vgt-omega-vault'); ?></option>
                            </select>
                        </div>
                        <button type="button" class="vgt-btn-primary" id="vgt-btn-builder-save" style="width: auto; margin-top: 0; padding: 0.6rem 2rem;">
                            <?php echo esc_html__('Save Configuration', 'vgt-omega-vault'); ?>
                        </button>
                    </div>

                    <div class="vgt-builder-workspace-grid">
                        
                        <!-- Left Panel: Draggable Field Components -->
                        <div class="vgt-builder-sidebar left">
                            <div class="vgt-title-xs" style="margin-bottom:1rem; color:#fff; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:0.5rem;"><?php echo esc_html__('Field Modules', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-draggable-list">
                                <div class="vgt-drag-module" draggable="true" data-type="text">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Text Input', 'vgt-omega-vault'); ?></span>
                                </div>
                                <div class="vgt-drag-module" draggable="true" data-type="email">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Email Input', 'vgt-omega-vault'); ?></span>
                                </div>
                                <div class="vgt-drag-module" draggable="true" data-type="number">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Number Input', 'vgt-omega-vault'); ?></span>
                                </div>
                                <div class="vgt-drag-module" draggable="true" data-type="textarea">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Textarea Input', 'vgt-omega-vault'); ?></span>
                                </div>
                                <div class="vgt-drag-module" draggable="true" data-type="select">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Dropdown Select', 'vgt-omega-vault'); ?></span>
                                </div>
                                <div class="vgt-drag-module" draggable="true" data-type="radio">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Radio Group', 'vgt-omega-vault'); ?></span>
                                </div>
                                <div class="vgt-drag-module" draggable="true" data-type="file">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('File Upload', 'vgt-omega-vault'); ?></span>
                                </div>
                            </div>

                            <div class="vgt-title-xs" style="margin-top:1.5rem; margin-bottom:1rem; color:#fff; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:0.5rem;"><?php echo esc_html__('Layout / Media', 'vgt-omega-vault'); ?></div>
                            <div class="vgt-draggable-list">
                                <div class="vgt-drag-module step-break-module" draggable="true" data-type="step_break">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Trichter-Schritt (Step)', 'vgt-omega-vault'); ?></span>
                                </div>
                                <div class="vgt-drag-module" draggable="true" data-type="heading">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Heading Block', 'vgt-omega-vault'); ?></span>
                                </div>
                                <div class="vgt-drag-module" draggable="true" data-type="paragraph">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Text Paragraph', 'vgt-omega-vault'); ?></span>
                                </div>
                                <div class="vgt-drag-module" draggable="true" data-type="image">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Image Block', 'vgt-omega-vault'); ?></span>
                                </div>
                                <div class="vgt-drag-module" draggable="true" data-type="video">
                                    <span class="vgt-drag-icon">⠿</span>
                                    <span><?php echo esc_html__('Video Block', 'vgt-omega-vault'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Center Panel: The Drop Canvas Workspace (Live Preview Wrapper) -->
                        <div class="vgt-builder-canvas-container">
                            <div class="vgt-fe-wrapper" id="vgt-builder-preview-wrapper" style="max-width: 780px; margin: 0 auto; width: 100%;">
                                <div class="vgt-fe-header">
                                    <h2 class="vgt-fe-title" id="vgt-preview-title" contenteditable="true"><?php echo esc_html__('Form Title', 'vgt-omega-vault'); ?></h2>
                                    <div class="vgt-fe-subtitle" id="vgt-preview-subtitle" contenteditable="true"><?php echo esc_html__('End-to-End Encrypted Tunnel', 'vgt-omega-vault'); ?></div>
                                </div>
                                
                                <div class="vgt-progress-container" id="vgt-preview-progress-container" style="display: none;">
                                    <div class="vgt-progress-bar" id="vgt-preview-progress-bar" style="width: 50%;"></div>
                                    <div class="vgt-progress-label" id="vgt-preview-progress-label">Step 1 of 2</div>
                                </div>

                                <div class="vgt-builder-canvas" id="vgt-canvas">
                                    <!-- Steps and fields are injected here dynamically by JS -->
                                </div>

                                <div class="vgt-canvas-placeholder" id="vgt-canvas-placeholder-text">
                                    <?php echo esc_html__('Drag und Drop Module hierher ziehen oder Trichter-Schritt hinzufügen...', 'vgt-omega-vault'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Configurations & Styles -->
                        <div class="vgt-builder-sidebar right">
                            <div class="vgt-sidebar-tabs">
                                <button type="button" class="vgt-sidebar-tab-btn active" data-tab="field-props"><?php echo esc_html__('Field config', 'vgt-omega-vault'); ?></button>
                                <button type="button" class="vgt-sidebar-tab-btn" data-tab="design-props"><?php echo esc_html__('Design', 'vgt-omega-vault'); ?></button>
                            </div>

                            <!-- Tab 1: Field Properties -->
                            <div id="vgt-sidebar-tab-field-props" class="vgt-sidebar-tab-content active">
                                <div id="vgt-no-field-selected" class="vgt-mono" style="color:var(--vgt-text-muted); text-align:center; padding-top:2rem;">
                                    <?php echo esc_html__('Klicken Sie auf ein Feld im Workspace, um die Optionen anzupassen.', 'vgt-omega-vault'); ?>
                                </div>
                                <div id="vgt-field-properties-form" style="display:none;">
                                    <div class="vgt-fe-group">
                                        <label class="vgt-fe-label"><?php echo esc_html__('Label / Text', 'vgt-omega-vault'); ?></label>
                                        <input type="text" id="prop-label" class="vgt-fe-input" style="padding-left:1rem;">
                                    </div>
                                    <div class="vgt-fe-group prop-text-color-wrap">
                                        <label class="vgt-fe-label"><?php echo esc_html__('Text Color', 'vgt-omega-vault'); ?></label>
                                        <input type="color" id="prop-text-color" class="vgt-fe-input" style="padding:0; height:40px; background:transparent;" value="#f9fafb">
                                    </div>
                                    <div class="vgt-fe-group prop-placeholder-wrap">
                                        <label class="vgt-fe-label"><?php echo esc_html__('Placeholder Text', 'vgt-omega-vault'); ?></label>
                                        <input type="text" id="prop-placeholder" class="vgt-fe-input" style="padding-left:1rem;">
                                    </div>
                                    <div class="vgt-fe-group prop-required-wrap">
                                        <label class="vgt-radio-label" style="background:transparent; border:none; padding:0;">
                                            <input type="checkbox" id="prop-required">
                                            <span><?php echo esc_html__('Erforderliches Feld', 'vgt-omega-vault'); ?></span>
                                        </label>
                                    </div>
                                    <div class="vgt-fe-group prop-options-wrap" style="display:none;">
                                        <label class="vgt-fe-label"><?php echo esc_html__('Optionen (Kommagetrennt)', 'vgt-omega-vault'); ?></label>
                                        <textarea id="prop-options" class="vgt-fe-input vgt-fe-textarea" style="padding-left:1rem; height:80px; min-height:80px;" placeholder="Option 1, Option 2..."></textarea>
                                    </div>
                                    <div class="vgt-fe-group prop-media-url-wrap" style="display:none;">
                                        <label class="vgt-fe-label"><?php echo esc_html__('Medien-URL (Image / Video / YouTube)', 'vgt-omega-vault'); ?></label>
                                        <input type="text" id="prop-media-url" class="vgt-fe-input" style="padding-left:1rem;" placeholder="https://...">
                                    </div>
                                </div>
                            </div>

                            <!-- Tab 2: Design Options -->
                            <div id="vgt-sidebar-tab-design-props" class="vgt-sidebar-tab-content">
                                <div class="vgt-fe-group">
                                    <label class="vgt-fe-label"><?php echo esc_html__('Style Theme', 'vgt-omega-vault'); ?></label>
                                    <select id="design-theme" class="vgt-fe-input" style="padding-left:1rem; appearance: none; background:#111;">
                                        <option value="dark"><?php echo esc_html__('Gold / Carbon (Default)', 'vgt-omega-vault'); ?></option>
                                        <option value="light"><?php echo esc_html__('Clean Light', 'vgt-omega-vault'); ?></option>
                                        <option value="cyberpunk"><?php echo esc_html__('Cyberpunk Neon', 'vgt-omega-vault'); ?></option>
                                    </select>
                                </div>
                                <div class="vgt-fe-group">
                                    <label class="vgt-fe-label"><?php echo esc_html__('Gold Accent Color', 'vgt-omega-vault'); ?></label>
                                    <input type="color" id="design-gold-accent" class="vgt-fe-input" style="padding:0; height:40px; background:transparent;" value="#d4af37">
                                </div>
                                <div class="vgt-fe-group">
                                    <label class="vgt-fe-label"><?php echo esc_html__('Background Color', 'vgt-omega-vault'); ?></label>
                                    <input type="color" id="design-bg-color" class="vgt-fe-input" style="padding:0; height:40px; background:transparent;" value="#030303">
                                </div>
                                <div class="vgt-fe-group">
                                    <label class="vgt-fe-label"><?php echo esc_html__('Text Color', 'vgt-omega-vault'); ?></label>
                                    <input type="color" id="design-text-color" class="vgt-fe-input" style="padding:0; height:40px; background:transparent;" value="#f9fafb">
                                </div>
                                <div class="vgt-fe-group">
                                    <label class="vgt-fe-label"><?php echo esc_html__('Background Image (URL)', 'vgt-omega-vault'); ?></label>
                                    <input type="text" id="design-bg-image" class="vgt-fe-input" style="padding-left:1rem;" placeholder="https://...">
                                </div>
                                <div class="vgt-fe-group">
                                    <label class="vgt-fe-label"><?php echo esc_html__('Border Corner Radius', 'vgt-omega-vault'); ?></label>
                                    <select id="design-border-radius" class="vgt-fe-input" style="padding-left:1rem; appearance: none; background:#111;">
                                        <option value="8px"><?php echo esc_html__('Slightly Rounded (8px)', 'vgt-omega-vault'); ?></option>
                                        <option value="0px"><?php echo esc_html__('Angular (0px)', 'vgt-omega-vault'); ?></option>
                                        <option value="24px"><?php echo esc_html__('Fully Rounded (24px)', 'vgt-omega-vault'); ?></option>
                                    </select>
                                </div>
                                <div class="vgt-fe-group">
                                    <label class="vgt-fe-label"><?php echo esc_html__('Form Padding', 'vgt-omega-vault'); ?></label>
                                    <select id="design-padding" class="vgt-fe-input" style="padding-left:1rem; appearance: none; background:#111;">
                                        <option value="3rem"><?php echo esc_html__('Comfortable (3rem)', 'vgt-omega-vault'); ?></option>
                                        <option value="1.5rem"><?php echo esc_html__('Compact (1.5rem)', 'vgt-omega-vault'); ?></option>
                                        <option value="4.5rem"><?php echo esc_html__('Spacious (4.5rem)', 'vgt-omega-vault'); ?></option>
                                    </select>
                                </div>
                                <div class="vgt-fe-group">
                                    <label class="vgt-fe-label"><?php echo esc_html__('Form Width (max-width)', 'vgt-omega-vault'); ?></label>
                                    <input type="text" id="design-width" class="vgt-fe-input" style="padding-left:1rem;" value="780px" placeholder="e.g. 780px or 100%">
                                </div>
                                <div class="vgt-fe-group">
                                    <label class="vgt-fe-label"><?php echo esc_html__('Button CTA text', 'vgt-omega-vault'); ?></label>
                                    <input type="text" id="design-btn-text" class="vgt-fe-input" style="padding-left:1rem;" value="Initialize Encryption">
                                </div>
                                <div class="vgt-fe-group">
                                    <label class="vgt-radio-label" style="background:transparent; border:none; padding:0;">
                                        <input type="checkbox" id="design-gdpr-enabled">
                                        <span><?php echo esc_html__('DS-GVO Einverständnis aktivieren', 'vgt-omega-vault'); ?></span>
                                    </label>
                                </div>
                                <div class="vgt-fe-group" id="design-gdpr-text-wrap" style="display:none;">
                                    <label class="vgt-fe-label"><?php echo esc_html__('Zustimmungstext', 'vgt-omega-vault'); ?></label>
                                    <textarea id="design-gdpr-text" class="vgt-fe-input vgt-fe-textarea" style="padding-left:1rem; height:80px; min-height:80px;"></textarea>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 5: SUBMISSIONS DATA VIEW WORKSPACE -->
                <div id="vgt-sec-submissions" class="vgt-section">
                    <div class="vgt-workspace-header">
                        <button type="button" class="vgt-btn-back vgt-flex-center" id="vgt-btn-subs-back">
                            <?php echo self::get_svg('arrow-left'); ?>
                            <span><?php echo esc_html__('Back to Directory', 'vgt-omega-vault'); ?></span>
                        </button>
                        <h2 class="vgt-mono vgt-title-xs text-gold" id="vgt-subs-title-header" style="margin:0;">
                            <?php echo esc_html__('Form Submissions', 'vgt-omega-vault'); ?>
                        </h2>
                        <div class="vgt-mono vgt-title-xs" style="color:var(--vgt-text-muted);">
                            <?php echo esc_html__('Volatile memory (RAM) decryption', 'vgt-omega-vault'); ?>
                        </div>
                    </div>

                    <div class="vgt-table-container">
                        <table class="vgt-table" id="vgt-subs-table">
                            <thead>
                                <tr class="vgt-mono vgt-title-xs" id="vgt-subs-table-headers">
                                    <!-- Dynamic headers injected by JS based on form configuration -->
                                </tr>
                            </thead>
                            <tbody id="vgt-subs-table-body">
                                <!-- Dynamic entries injected by JS -->
                            </tbody>
                        </table>
                    </div>

                    <div class="vgt-pagination" id="vgt-subs-pagination">
                        <!-- Dynamic pagination buttons -->
                    </div>
                </div>

                <div class="vgt-mono vgt-title-xs text-gold vgt-flex-center" style="justify-content: center; margin-top: 3.5rem;">
                    <?php echo self::get_svg('lock'); ?>
                    <?php echo esc_html__('Decryption is volatile. Raw plain text never touches persistent hard drives.', 'vgt-omega-vault'); ?>
                </div>

            </div>
        </div>
        
        <!-- Toast Notification Alert -->
        <div id="vgt-toast-alert" class="vgt-toast"></div>
        <?php
    }
}
