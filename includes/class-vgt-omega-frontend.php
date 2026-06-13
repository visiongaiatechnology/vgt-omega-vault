<?php
/**
 * VGT OMEGA VAULT: Frontend Formular & Shortcode Generator
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT SECURE ZONE: DIRECT ACCESS FORBIDDEN');
}

final class VGT_Omega_Frontend {

    /**
     * LEGACY COMLINK: static single-form encrypted intake.
     */
    public static function render_shortcode(): string {
        $form1 = VGT_Omega_DB::get_form(1);
        if ($form1) {
            return self::render_form_shortcode(['id' => 1]);
        }

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
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        </div>
                        <input type="text" name="vgt_domain" class="vgt-fe-input" required placeholder="https://domain.com oder 192.168.1.XXX">
                    </div>
                </div>

                <div class="vgt-fe-group">
                    <label class="vgt-fe-label">Operative Auth <span>(E-Mail)</span></label>
                    <div class="vgt-input-wrapper">
                        <div class="vgt-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        </div>
                        <input type="email" name="vgt_email" class="vgt-fe-input" required placeholder="operative@visiongaiatechnology.de">
                    </div>
                </div>

                <div class="vgt-fe-group">
                    <label class="vgt-fe-label">Threat Vector <span>(Subject)</span></label>
                    <div class="vgt-input-wrapper">
                        <div class="vgt-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
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

    /**
     * DYNAMIC BUILDER SHORTCODE RENDERER.
     * Supports multiple steps, backgrounds, media files, and GCM encryption.
     */
    public static function render_form_shortcode(array $atts): string {
        $id = isset($atts['id']) ? (int)$atts['id'] : 0;
        if ($id <= 0) {
            return '<div class="vgt-fe-wrapper" style="border-color: red; padding: 2rem; color: #ef4444;">VGT SECURE ZONE: Missing Form ID parameters.</div>';
        }

        $form = VGT_Omega_DB::get_form($id);
        if (!$form) {
            return '<div class="vgt-fe-wrapper" style="border-color: red; padding: 2rem; color: #ef4444;">VGT SECURE ZONE: Specified Safe-Vault Form not found.</div>';
        }

        $config = json_decode($form->config, true);
        if (!is_array($config) || empty($config['fields'])) {
            return '<div class="vgt-fe-wrapper" style="border-color: red; padding: 2rem; color: #ef4444;">VGT SECURE ZONE: Form has no active fields configured.</div>';
        }

        // Group fields into Steps (split on step_break)
        $steps = [];
        $current_step_index = 0;
        foreach ($config['fields'] as $field) {
            if ($field['type'] === 'step_break') {
                $current_step_index++;
                continue;
            }
            $steps[$current_step_index][] = $field;
        }

        $total_steps = count($steps);
        $nonce = wp_create_nonce('vgt_omega_comlink_action');
        $stateless_token = VGT_Omega_API::generate_stateless_token();
        $ajax_url = admin_url('admin-ajax.php');

        // Extract style configuration
        $settings = $config['settings'] ?? [];
        $theme = $settings['theme'] ?? 'dark';
        $bg_color = $settings['background_color'] ?? '#030303';
        $bg_image = $settings['background_image'] ?? '';
        $gold_color = $settings['gold_accent'] ?? '#d4af37';
        $button_text = $settings['button_text'] ?? esc_html__('Initialize Encryption', 'vgt-omega-vault');
        $border_radius = $settings['border_radius'] ?? '8px';
        $padding = $settings['padding'] ?? '3rem';
        $width = $settings['width'] ?? '780px';
        $subtitle = $settings['subtitle'] ?? ($form->type === 'funnel' ? 'Secure Step Tunnel' : 'End-to-End Encrypted Tunnel');
        $text_color = $settings['text_color'] ?? '#f9fafb';
        $title_color = $settings['title_color'] ?? '';
        $subtitle_color = $settings['subtitle_color'] ?? '';
        $gdpr_enabled = !empty($settings['gdpr_enabled']);
        $gdpr_text = $settings['gdpr_text'] ?? esc_html__('Ich stimme der verschlüsselten Speicherung meiner eingegebenen Daten sowie meiner IP-Adresse zur Verarbeitung dieser Anfrage zu.', 'vgt-omega-vault');

        // Compute styling rules
        $inline_style = '';
        if ($bg_image) {
            $inline_style .= "background-image: url('" . esc_url($bg_image) . "'); background-size: cover; background-position: center;";
        } else {
            $inline_style .= "background: " . esc_attr($bg_color) . ";";
        }
        $inline_style .= " --vgt-radius: " . esc_attr($border_radius) . ";";
        $inline_style .= " --vgt-padding: " . esc_attr($padding) . ";";
        $inline_style .= " --vgt-width: " . esc_attr($width) . ";";
        $inline_style .= " --vgt-text: " . esc_attr($text_color) . ";";

        ob_start();
        ?>
        <style>
            .vgt-fe-wrapper {
                --vgt-bg: #030303;
                --vgt-surface: rgba(12, 12, 12, 0.85);
                --vgt-border: rgba(255, 255, 255, 0.08);
                --vgt-border-focus: rgba(212, 175, 55, 0.5);
                --vgt-gold: <?php echo esc_attr($gold_color); ?>;
                --vgt-gold-glow: rgba(212, 175, 55, 0.4);
                --vgt-text: <?php echo esc_attr($text_color); ?>;
                --vgt-text-muted: #6b7280;
                --vgt-icon: #9ca3af;
                --vgt-error: #ef4444;
                --vgt-success: #10b981;
                
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
                background: var(--vgt-bg);
                color: var(--vgt-text);
                padding: var(--vgt-padding, 3rem);
                border-radius: var(--vgt-radius, 16px);
                border: 1px solid var(--vgt-border);
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8), inset 0 0 0 1px rgba(255,255,255,0.02);
                max-width: var(--vgt-width, 780px);
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
                width: 100%;
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
                border-radius: calc(var(--vgt-radius, 8px) * 0.7); 
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
                border-radius: calc(var(--vgt-radius, 8px) * 0.7); 
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
                border-radius: calc(var(--vgt-radius, 8px) * 0.7); 
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

            /* Light theme overrides */
            .vgt-fe-wrapper.vgt-theme-light {
                --vgt-bg: #ffffff;
                --vgt-surface: rgba(245, 245, 245, 0.95);
                --vgt-border: rgba(0, 0, 0, 0.08);
                --vgt-border-focus: rgba(212, 175, 55, 0.7);
                --vgt-text: #111827;
                --vgt-text-muted: #6b7280;
                --vgt-icon: #4b5563;
                --vgt-error: #dc2626;
                --vgt-success: #059669;
            }
            .vgt-fe-wrapper.vgt-theme-light .vgt-fe-input {
                background: rgba(0,0,0,0.03);
            }
            .vgt-fe-wrapper.vgt-theme-light .vgt-radio-label {
                background: rgba(0,0,0,0.02);
            }

            /* Cyberpunk theme overrides */
            .vgt-fe-wrapper.vgt-theme-cyberpunk {
                --vgt-bg: #0b0716;
                --vgt-surface: rgba(18, 11, 36, 0.9);
                --vgt-border: rgba(0, 242, 254, 0.2);
                --vgt-border-focus: rgba(255, 0, 127, 0.8);
                --vgt-text: #00f2fe;
                --vgt-text-muted: #7138b5;
                --vgt-icon: #ff007f;
                --vgt-error: #ff0055;
                --vgt-success: #00ffaa;
            }

            .vgt-progress-container {
                margin-bottom: 2rem;
                background: rgba(255,255,255,0.05);
                height: 6px;
                border-radius: 3px;
                position: relative;
                overflow: visible;
            }
            .vgt-progress-bar {
                background: var(--vgt-gold);
                height: 100%;
                border-radius: 3px;
                box-shadow: 0 0 10px var(--vgt-gold);
                transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .vgt-progress-label {
                position: absolute;
                right: 0;
                top: -20px;
                font-size: 0.65rem;
                font-family: 'JetBrains Mono', monospace;
                color: var(--vgt-text-muted);
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .vgt-form-step {
                animation: vgtStepFade 0.3s ease-out forwards;
            }

            @keyframes vgtStepFade {
                from { opacity: 0; transform: translateX(10px); }
                to { opacity: 1; transform: translateX(0); }
            }

            .vgt-radio-group {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                width: 100%;
            }
            .vgt-radio-label {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                background: rgba(0,0,0,0.4);
                border: 1px solid var(--vgt-border);
                padding: 0.85rem 1rem;
                border-radius: calc(var(--vgt-radius, 8px) * 0.7);
                cursor: pointer;
                transition: all 0.3s ease;
                color: var(--vgt-text);
                margin: 0;
            }
            .vgt-radio-label:hover {
                border-color: var(--vgt-border-focus);
                background: rgba(255,255,255,0.02);
            }
            .vgt-radio-label input[type="radio"] {
                appearance: none;
                width: 16px;
                height: 16px;
                border: 1px solid var(--vgt-border-focus);
                border-radius: 50%;
                display: grid;
                place-content: center;
                cursor: pointer;
                background: transparent;
                margin: 0;
            }
            .vgt-radio-label input[type="radio"]::before {
                content: "";
                width: 8px;
                height: 8px;
                border-radius: 50%;
                transform: scale(0);
                transition: 120ms transform ease-in-out;
                background-color: var(--vgt-gold);
                box-shadow: 0 0 8px var(--vgt-gold);
            }
            .vgt-radio-label input[type="radio"]:checked::before {
                transform: scale(1);
            }
            .vgt-radio-label input[type="radio"]:checked {
                border-color: var(--vgt-gold);
            }

            .vgt-select-arrow {
                position: absolute;
                right: 1.25rem;
                pointer-events: none;
                font-size: 0.65rem;
                color: var(--vgt-icon);
            }

            .vgt-step-navigation {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 2.5rem;
                gap: 1rem;
            }

            .vgt-video-responsive {
                overflow: hidden;
                padding-bottom: 56.25%;
                position: relative;
                height: 0;
                border-radius: 8px;
                margin: 1rem 0;
            }
            .vgt-video-responsive iframe {
                left: 0;
                top: 0;
                height: 100%;
                width: 100%;
                position: absolute;
            }
            .vgt-file-upload {
                border-radius: calc(var(--vgt-radius, 8px) * 0.7);
            }
            .vgt-media-img, .vgt-media-video {
                border-radius: calc(var(--vgt-radius, 8px) * 0.8);
            }
        </style>

        <div class="vgt-fe-wrapper vgt-theme-<?php echo esc_attr($theme); ?>" style="<?php echo $inline_style; ?> --vgt-gold: <?php echo esc_attr($gold_color); ?>;">
            <div class="vgt-fe-header">
                <h2 class="vgt-fe-title" style="<?php echo $title_color ? 'color: ' . esc_attr($title_color) . ';' : ''; ?>"><?php echo esc_html($form->title); ?></h2>
                <div class="vgt-fe-subtitle" style="<?php echo $subtitle_color ? 'color: ' . esc_attr($subtitle_color) . ';' : ''; ?>"><?php echo esc_html($subtitle); ?></div>
            </div>

            <!-- Progress Bar if multi-step -->
            <?php if ($total_steps > 1) : ?>
                <div class="vgt-progress-container">
                    <div class="vgt-progress-bar" id="vgt-progress-bar-<?php echo $id; ?>" style="width: <?php echo (100 / $total_steps); ?>%;"></div>
                    <div class="vgt-progress-label" id="vgt-progress-lbl-<?php echo $id; ?>">
                        <?php echo sprintf(esc_html__('Step 1 of %d', 'vgt-omega-vault'), $total_steps); ?>
                    </div>
                </div>
            <?php endif; ?>

            <form id="vgt-omega-form-<?php echo $id; ?>" class="vgt-dynamic-form" autocomplete="off" enctype="multipart/form-data">
                <input type="hidden" name="action" value="vgt_submit_builder_form">
                <input type="hidden" name="form_id" value="<?php echo $id; ?>">
                <input type="hidden" name="vgt_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="vgt_stateless_token" value="<?php echo esc_attr($stateless_token); ?>">
                
                <div class="vgt-fe-honeypot">
                    <input type="text" name="vgt_full_name" tabindex="-1" autocomplete="new-password">
                </div>

                <?php 
                $step_count = 0;
                foreach ($steps as $step_idx => $step_fields) : 
                    $active_class = ($step_count === 0) ? 'active' : '';
                    $display_style = ($step_count === 0) ? '' : 'display: none;';
                ?>
                    <div class="vgt-form-step <?php echo $active_class; ?>" data-step="<?php echo $step_count + 1; ?>" style="<?php echo $display_style; ?>">
                        
                        <?php foreach ($step_fields as $field) : 
                            $fid = esc_attr($field['id']);
                            $label = esc_html($field['label'] ?? '');
                            $placeholder = esc_attr($field['placeholder'] ?? '');
                            $req = !empty($field['required']) ? 'required' : '';
                            $type = $field['type'] ?? 'text';
                            if ($type === 'heading') : ?>
                                <h3 class="vgt-fe-heading" style="color: <?php echo !empty($field['text_color']) ? esc_attr($field['text_color']) : 'var(--vgt-text)'; ?>; font-weight: 800; font-size: 1.55rem; margin: 1.5rem 0 1rem 0; font-family: inherit; border: none; background: transparent; text-align: left;"><?php echo $label; ?></h3>
                            <?php elseif ($type === 'paragraph') : ?>
                                <p class="vgt-fe-paragraph" style="color: <?php echo !empty($field['text_color']) ? esc_attr($field['text_color']) : 'var(--vgt-text)'; ?>; opacity: 0.85; font-size: 0.95rem; line-height: 1.6; margin: 0.75rem 0 1.25rem 0; font-family: inherit; border: none; background: transparent; text-align: left;"><?php echo $label; ?></p>
                            <?php else : ?>
                                <div class="vgt-fe-group">
                                    <?php if ($type !== 'image' && $type !== 'video') : ?>
                                        <label class="vgt-fe-label" for="<?php echo $fid; ?>" style="<?php echo !empty($field['text_color']) ? 'color: ' . esc_attr($field['text_color']) . ';' : ''; ?>">
                                            <?php echo $label; ?> <?php if ($req) : ?><span style="color: var(--vgt-gold);">*</span><?php endif; ?>
                                        </label>
                                    <?php endif; ?>

                                    <div class="vgt-input-wrapper">
                                        <?php if ($type === 'email') : ?>
                                            <div class="vgt-input-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                            </div>
                                            <input type="email" id="<?php echo $fid; ?>" name="<?php echo $fid; ?>" class="vgt-fe-input" <?php echo $req; ?> placeholder="<?php echo $placeholder; ?>">

                                        <?php elseif ($type === 'number') : ?>
                                            <input type="number" id="<?php echo $fid; ?>" name="<?php echo $fid; ?>" class="vgt-fe-input" <?php echo $req; ?> placeholder="<?php echo $placeholder; ?>" style="padding-left: 1rem;">

                                        <?php elseif ($type === 'textarea') : ?>
                                            <textarea id="<?php echo $fid; ?>" name="<?php echo $fid; ?>" class="vgt-fe-input vgt-fe-textarea" <?php echo $req; ?> placeholder="<?php echo $placeholder; ?>" style="padding-left: 1rem;"></textarea>

                                        <?php elseif ($type === 'select') : ?>
                                            <select id="<?php echo $fid; ?>" name="<?php echo $fid; ?>" class="vgt-fe-input" <?php echo $req; ?> style="padding-left: 1rem; appearance: none;">
                                                <option value=""><?php echo esc_html__('Bitte wählen...', 'vgt-omega-vault'); ?></option>
                                                <?php 
                                                $options = isset($field['options']) ? explode(',', $field['options']) : [];
                                                foreach ($options as $opt) : 
                                                    $opt_clean = trim($opt);
                                                ?>
                                                    <option value="<?php echo esc_attr($opt_clean); ?>"><?php echo esc_html($opt_clean); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="vgt-select-arrow">▼</div>

                                        <?php elseif ($type === 'radio') : ?>
                                            <div class="vgt-radio-group">
                                                <?php 
                                                $options = isset($field['options']) ? explode(',', $field['options']) : [];
                                                foreach ($options as $r_idx => $opt) : 
                                                    $opt_clean = trim($opt);
                                                    $rid = $fid . '_' . $r_idx;
                                                ?>
                                                    <label class="vgt-radio-label" for="<?php echo $rid; ?>">
                                                        <input type="radio" id="<?php echo $rid; ?>" name="<?php echo $fid; ?>" value="<?php echo esc_attr($opt_clean); ?>" <?php echo $req; ?>>
                                                        <span><?php echo esc_html($opt_clean); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>

                                        <?php elseif ($type === 'file') : ?>
                                            <input type="file" id="<?php echo $fid; ?>" name="<?php echo $fid; ?>" class="vgt-fe-input vgt-file-upload" <?php echo $req; ?> style="padding: 0.8rem 1rem;">

                                        <?php elseif ($type === 'image') : ?>
                                            <img src="<?php echo esc_url($field['media_url'] ?? ''); ?>" alt="<?php echo esc_attr($label); ?>" class="vgt-media-img" style="max-width:100%; border-radius:8px; margin: 0.5rem 0; display:block;">

                                        <?php elseif ($type === 'video') : ?>
                                            <?php 
                                            $vurl = $field['media_url'] ?? '';
                                            if (strpos($vurl, 'youtube.com') !== false || strpos($vurl, 'youtu.be') !== false) {
                                                preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $vurl, $match);
                                                $yid = $match[1] ?? '';
                                                echo '<div class="vgt-video-responsive"><iframe src="https://www.youtube.com/embed/' . esc_attr($yid) . '" frameborder="0" allowfullscreen></iframe></div>';
                                            } else {
                                                echo '<video src="' . esc_url($vurl) . '" controls class="vgt-media-video" style="width:100%; border-radius:8px; margin: 0.5rem 0; display:block;"></video>';
                                            }
                                            ?>

                                        <?php else : ?>
                                            <div class="vgt-input-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                            </div>
                                            <input type="text" id="<?php echo $fid; ?>" name="<?php echo $fid; ?>" class="vgt-fe-input" <?php echo $req; ?> placeholder="<?php echo $placeholder; ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($step_count === $total_steps - 1 && $gdpr_enabled) : ?>
                            <div class="vgt-fe-group vgt-gdpr-group" style="margin-top: 1.5rem; margin-bottom: 1.5rem;">
                                <label class="vgt-radio-label vgt-gdpr-label" style="display: flex; align-items: center; gap: 0.75rem; background: rgba(0,0,0,0.4); border: 1px solid var(--vgt-border); padding: 0.85rem 1rem; border-radius: calc(var(--vgt-radius, 8px) * 0.7); cursor: pointer; color: var(--vgt-text); margin: 0; width: 100%; box-sizing: border-box;">
                                    <input type="checkbox" name="vgt_gdpr_consent" value="1" required style="margin: 0; appearance: auto;">
                                    <span style="font-size: 0.8rem; line-height: 1.4; display: inline-block; text-align: left; text-transform: none; letter-spacing: normal; font-family: inherit; font-weight: normal; color: inherit;"><?php echo esc_html($gdpr_text); ?></span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <!-- Step Navigation Controls -->
                        <div class="vgt-step-navigation">
                            <?php if ($step_count > 0) : ?>
                                <button type="button" class="vgt-fe-btn prev-step" style="background: rgba(255,255,255,0.05); color: #fff; width: auto; padding: 0.8rem 1.5rem; margin-right: auto;"><?php echo esc_html__('Back', 'vgt-omega-vault'); ?></button>
                            <?php endif; ?>

                            <?php if ($step_count < $total_steps - 1) : ?>
                                <button type="button" class="vgt-fe-btn next-step" style="width: auto; padding: 0.8rem 2rem; margin-left: auto;"><?php echo esc_html__('Continue', 'vgt-omega-vault'); ?></button>
                            <?php else : ?>
                                <button type="submit" class="vgt-fe-btn vgt-submit-btn" style="width: auto; padding: 0.8rem 2rem; margin-left: auto;">
                                    <span class="vgt-fe-loader"></span>
                                    <span class="btn-text"><?php echo esc_html($button_text); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php 
                    $step_count++;
                endforeach; 
                ?>

                <div class="vgt-fe-msg" id="vgt-response-msg-<?php echo $id; ?>"></div>
            </form>
        </div>

        <script>
        (function() {
            const formId = <?php echo $id; ?>;
            const totalSteps = <?php echo $total_steps; ?>;
            const form = document.getElementById('vgt-omega-form-' + formId);
            const msgBox = document.getElementById('vgt-response-msg-' + formId);
            const progressBar = document.getElementById('vgt-progress-bar-' + formId);
            const progressLbl = document.getElementById('vgt-progress-lbl-' + formId);

            let currentStep = 1;

            const updateStepView = () => {
                const steps = form.querySelectorAll('.vgt-form-step');
                steps.forEach(step => {
                    if (parseInt(step.getAttribute('data-step')) === currentStep) {
                        step.style.display = 'block';
                        setTimeout(() => step.classList.add('active'), 20);
                    } else {
                        step.style.display = 'none';
                        step.classList.remove('active');
                    }
                });

                if (progressBar) {
                    const pct = (currentStep / totalSteps) * 100;
                    progressBar.style.width = pct + '%';
                    progressLbl.innerText = `Step ${currentStep} of ${totalSteps}`;
                }
            };

            const validateStepFields = (stepNum) => {
                const stepContainer = form.querySelector(`.vgt-form-step[data-step="${stepNum}"]`);
                if (!stepContainer) return true;
                const fields = stepContainer.querySelectorAll('input[required], textarea[required], select[required]');
                let valid = true;
                fields.forEach(field => {
                    if (field.type === 'radio') {
                        const name = field.name;
                        const checked = form.querySelector(`input[name="${name}"]:checked`);
                        if (!checked) valid = false;
                    } else if (field.type === 'checkbox') {
                        if (!field.checked) {
                            valid = false;
                            const labelWrap = field.closest('.vgt-gdpr-label');
                            if (labelWrap) {
                                labelWrap.style.borderColor = 'var(--vgt-error, #ef4444)';
                                field.addEventListener('change', () => {
                                    labelWrap.style.borderColor = '';
                                }, { once: true });
                            }
                        }
                    } else {
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = 'var(--vgt-error, #ef4444)';
                            field.addEventListener('input', () => {
                                field.style.borderColor = '';
                            }, { once: true });
                        }
                    }
                });
                return valid;
            };

            // Setup Next Buttons
            form.querySelectorAll('.next-step').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (validateStepFields(currentStep)) {
                        if (currentStep < totalSteps) {
                            currentStep++;
                            updateStepView();
                        }
                    } else {
                        msgBox.innerText = 'SYSTEM WARNING: Bitte füllen Sie alle erforderlichen Felder aus.';
                        msgBox.className = 'vgt-fe-msg error';
                        msgBox.style.display = 'block';
                        setTimeout(() => {
                            msgBox.style.display = 'none';
                        }, 4000);
                    }
                });
            });

            // Setup Prev Buttons
            form.querySelectorAll('.prev-step').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (currentStep > 1) {
                        currentStep--;
                        updateStepView();
                    }
                });
            });

            // Form Submit Handler
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                if (!validateStepFields(currentStep)) return;

                const submitBtn = form.querySelector('.vgt-submit-btn');
                const loader = submitBtn.querySelector('.vgt-fe-loader');
                const btnText = submitBtn.querySelector('.btn-text');

                submitBtn.disabled = true;
                loader.style.display = 'inline-block';
                const originalBtnText = btnText.innerText;
                btnText.innerText = 'ENCRYPTING PAYLOAD...';
                msgBox.className = 'vgt-fe-msg';
                msgBox.style.display = 'none';

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
                        msgBox.style.display = 'block';
                        form.reset();
                        currentStep = 1;
                        updateStepView();
                    } else {
                        msgBox.innerText = 'SYSTEM HALT: ' + (result.data.message || 'Unknown Security Exception');
                        msgBox.className = 'vgt-fe-msg error';
                        msgBox.style.display = 'block';
                    }
                } catch (error) {
                    msgBox.innerText = 'SYSTEM HALT: Network Transmission Architecture Failure.';
                    msgBox.className = 'vgt-fe-msg error';
                    msgBox.style.display = 'block';
                } finally {
                    submitBtn.disabled = false;
                    loader.style.display = 'none';
                    btnText.innerText = originalBtnText;
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}