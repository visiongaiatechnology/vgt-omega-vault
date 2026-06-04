<?php
/**
 * VGT OMEGA VAULT: Frontend Formular & Shortcode Generator
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT SECURE ZONE: DIRECT ACCESS FORBIDDEN');
}

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