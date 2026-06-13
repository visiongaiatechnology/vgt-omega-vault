<?php
/**
 * VGT OMEGA VAULT: API Endpoint, Defense Shield & IP Hardening 
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT SECURE ZONE: DIRECT ACCESS FORBIDDEN');
}

class VGT_API_Exception        extends Exception {}
class VGT_Validation_Exception extends VGT_API_Exception {} // USER-FACING: Message shown verbatim
class VGT_Security_Exception   extends VGT_API_Exception {} // INTERNAL: Generic message to client, full detail to log
class VGT_Storage_Exception    extends VGT_API_Exception {} // INTERNAL: Generic message to client, full detail to log

final class VGT_Omega_API {

    /**
     * Generiert ein unmanipulierbares, strukturiertes IP-Profil.
     * Trennt REMOTE_ADDR (Socket-IP) von ungesicherten Header-Informationen.
     */
    public static function get_ip_profile(?array $server_mock = null): stdClass {
        $source = $server_mock ?: $_SERVER;
        
        $profile = new stdClass();
        // Pristine TCP-Verbindungs-IP vom Webserver-Socket (nicht fälschbar)
        $profile->socket = filter_var($source['REMOTE_ADDR'] ?? '127.0.0.1', FILTER_VALIDATE_IP) ?: '127.0.0.1';
        $profile->claimed = 'none';

        // Bestimmung des Proxy-Trust-Status (Constant Override > Database Option)
        $trust_proxies = false;
        if (defined('VGT_ALLOW_PROXIES')) {
            $trust_proxies = (bool) VGT_ALLOW_PROXIES;
        } else {
            $trust_proxies = (get_option('vgt_omega_allow_proxies', '0') === '1');
        }

        // Auswertung von Proxy-Header-Angaben nur bei explizitem Opt-In 
        if ($trust_proxies === true) {
            // Evaluierung von Cloudflare Connecting IP nur, wenn die Socket-IP ein verifizierter Cloudflare-Knoten ist
            if (!empty($source['HTTP_CF_CONNECTING_IP']) && is_string($source['HTTP_CF_CONNECTING_IP'])) {
                if (self::is_cloudflare_ip($profile->socket)) {
                    $ips = explode(',', $source['HTTP_CF_CONNECTING_IP']);
                    $first_ip = trim($ips[0]);
                    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
                    if (filter_var($first_ip, FILTER_VALIDATE_IP, $flags)) {
                        $profile->claimed = sanitize_text_field($first_ip);
                    }
                }
            }

            // Fallback auf andere Standard-Proxy-Header falls CF-IP nicht gesetzt/valide war
            if ($profile->claimed === 'none') {
                $proxy_headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'];
                foreach ($proxy_headers as $header) {
                    if (!empty($source[$header]) && is_string($source[$header])) {
                        $ips = explode(',', $source[$header]);
                        $first_ip = trim($ips[0]);
                        
                        // Filterung von privaten/reservierten Netzen (Security Hardening)
                        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
                        if (filter_var($first_ip, FILTER_VALIDATE_IP, $flags)) {
                            $profile->claimed = sanitize_text_field($first_ip);
                            break;
                        }
                    }
                }
            }
        }

        return $profile;
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
        try {
            self::execute_security_handshake();
            
            $ip_profile = self::get_ip_profile();
            self::enforce_rate_limit($ip_profile->socket);
            
            // Bot-Detection: Honeypot
            if (!empty($_POST['vgt_full_name'])) {
                throw new VGT_Security_Exception('Bot anomaly detected via honeypot.');
            }

            // Input Validation Pipeline
            $data = self::validate_payload_integrity();

            // Cryptographic Wrapping
            $payload = [
                'domain'     => VGT_Omega_Crypto::encrypt($data['domain'], 'domain'),
                'email'      => VGT_Omega_Crypto::encrypt($data['email'], 'email'),
                'vector'     => VGT_Omega_Crypto::encrypt($data['vector'], 'vector'),
                'threat'     => VGT_Omega_Crypto::encrypt($data['threat'], 'threat'),
                'ip_socket'  => VGT_Omega_Crypto::encrypt($ip_profile->socket, 'ip_socket'),
                'ip_claimed' => VGT_Omega_Crypto::encrypt($ip_profile->claimed, 'ip_claimed')
            ];

            if (!VGT_Omega_DB::insert($payload)) {
                throw new VGT_Storage_Exception('Database write fault during crypto-insertion.');
            }

            self::dispatch_notification();
            wp_send_json_success(['message' => esc_html__('Übertragung abgeschlossen. Daten gesichert.', 'vgt-omega-vault')]);

        } catch (VGT_Validation_Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        } catch (VGT_Security_Exception $e) {
            error_log('[VGT_SEC] ' . $e->getMessage());
            wp_send_json_error(['message' => esc_html__('Anfrage aus Sicherheitsgründen abgelehnt.', 'vgt-omega-vault')], 403);
        } catch (VGT_Storage_Exception $e) {
            error_log('[VGT_STORAGE] ' . $e->getMessage());
            wp_send_json_error(['message' => esc_html__('Ein Systemfehler ist aufgetreten.', 'vgt-omega-vault')], 500);
        } catch (Throwable $e) {
            error_log('[VGT_FATAL] ' . $e->getMessage());
            wp_send_json_error(['message' => esc_html__('Kritischer Systemfehler.', 'vgt-omega-vault')], 500);
        }
    }

    private static function execute_security_handshake(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new VGT_Security_Exception('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
        }

        $nonce = isset($_POST['vgt_nonce']) ? sanitize_text_field($_POST['vgt_nonce']) : '';
        $token = isset($_POST['vgt_stateless_token']) ? sanitize_text_field($_POST['vgt_stateless_token']) : '';

        $nonce_valid = wp_verify_nonce($nonce, 'vgt_omega_comlink_action');
        $token_valid = self::verify_stateless_token($token);

        if (!$nonce_valid || !$token_valid) {
            throw new VGT_Security_Exception('CSRF/Token validation failed.');
        }
    }

    private static function enforce_rate_limit(string $socket_ip): void {
        $rate_limit_key = 'vgt_rl_' . md5($socket_ip);
        if (get_transient($rate_limit_key)) {
            throw new VGT_Validation_Exception(esc_html__('Rate-Limit erreicht. Bitte warten.', 'vgt-omega-vault'));
        }
        set_transient($rate_limit_key, true, 60);
    }

    private static function validate_payload_integrity(): array {
        $raw_domain = isset($_POST['vgt_domain']) ? trim((string)wp_unslash($_POST['vgt_domain'])) : '';
        $raw_email  = isset($_POST['vgt_email']) ? trim((string)wp_unslash($_POST['vgt_email'])) : '';
        $raw_vector = isset($_POST['vgt_vector']) ? trim((string)wp_unslash($_POST['vgt_vector'])) : '';
        $raw_threat = isset($_POST['vgt_threat']) ? trim((string)wp_unslash($_POST['vgt_threat'])) : '';

        if (!is_email($raw_email) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $raw_email)) {
            throw new VGT_Validation_Exception(esc_html__('E-Mail Syntax-Fehler.', 'vgt-omega-vault'));
        }

        $domain_regex = '/^(?:https?:\/\/)?(?:[a-zA-Z0-9\-]+\.)+[a-zA-Z]{2,}(?:\/\S*)?$|^(?:https?:\/\/)?(?:\d{1,3}\.){3}(?:\d{1,3}|XXX|xxx)(?:\/\d{1,2})?$/i';
        if (!preg_match($domain_regex, $raw_domain)) {
            throw new VGT_Validation_Exception(esc_html__('Ungültiges Zielformat (Domain/IP).', 'vgt-omega-vault'));
        }

        if (preg_match('/[<>]/', $raw_threat)) {
            throw new VGT_Security_Exception('HTML/Script injection attempt in threat payload.');
        }

        return [
            'domain' => sanitize_text_field($raw_domain),
            'email'  => sanitize_email($raw_email),
            'vector' => sanitize_text_field($raw_vector),
            'threat' => sanitize_textarea_field($raw_threat)
        ];
    }

    private static function dispatch_notification(): void {
        $to = get_option('admin_email');
        if (!is_string($to) || empty($to)) return;

        $subject = esc_html__('/// VGT OMEGA: Neues Audit-Protokoll', 'vgt-omega-vault');
        $message  = "SYSTEM ALERT: Neue Audit-Anfrage empfangen.\n";
        $message .= "Verschlüsselung: AES-256-GCM\n";
        $message .= "Status: Secured in Vault\n";
        $message .= "Zeitstempel: " . current_time('mysql') . "\n";
        
        wp_mail($to, $subject, $message);
    }

    /**
     * Checks if a given IP address belongs to Cloudflare's official IP ranges.
     */
    private static function is_cloudflare_ip(string $ip): bool {
        $cloudflare_ips = [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22'
        ];

        $cloudflare_ipv6s = [
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32'
        ];

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_dec = ip2long($ip);
            if ($ip_dec === false) {
                return false;
            }
            foreach ($cloudflare_ips as $range) {
                list($subnet, $bits) = explode('/', $range);
                $subnet_dec = ip2long($subnet);
                if ($subnet_dec === false) {
                    continue;
                }
                $mask = -1 << (32 - (int)$bits);
                if (($ip_dec & $mask) === ($subnet_dec & $mask)) {
                    return true;
                }
            }
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip_bin = inet_pton($ip);
            if ($ip_bin === false) {
                return false;
            }
            foreach ($cloudflare_ipv6s as $range) {
                list($subnet, $bits) = explode('/', $range);
                $subnet_bin = inet_pton($subnet);
                if ($subnet_bin === false) {
                    continue;
                }
                $bits = (int)$bits;
                $matching = true;
                for ($i = 0; $i < 16; $i++) {
                    $bit_offset = $i * 8;
                    if ($bit_offset >= $bits) {
                        break;
                    }
                    $bits_to_check = min(8, $bits - $bit_offset);
                    if ($bits_to_check === 8) {
                        if ($ip_bin[$i] !== $subnet_bin[$i]) {
                            $matching = false;
                            break;
                        }
                    } else {
                        $mask = ~((1 << (8 - $bits_to_check)) - 1);
                        if ((ord($ip_bin[$i]) & $mask) !== (ord($subnet_bin[$i]) & $mask)) {
                            $matching = false;
                            break;
                        }
                    }
                }
                if ($matching) {
                    return true;
                }
            }
        }

        return false;
    }

    /* ==============================================================================
     * FORMS & SUBMISSIONS AJAX HANDLERS
     * ============================================================================== */

    public static function handle_save_form(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized clearance level.', 'vgt-omega-vault')], 403);
        }

        check_ajax_referer('vgt_save_config_nonce', 'security');

        $form_id = isset($_POST['form_id']) ? (int)$_POST['form_id'] : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $type = isset($_POST['type']) && $_POST['type'] === 'funnel' ? 'funnel' : 'form';
        $config_raw = isset($_POST['config']) ? wp_unslash($_POST['config']) : '';

        // Validate JSON configuration structure
        json_decode($config_raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => esc_html__('Invalid configuration payload format.', 'vgt-omega-vault')], 400);
        }

        $data = [
            'title'  => $title,
            'type'   => $type,
            'config' => $config_raw
        ];

        if ($form_id > 0) {
            $success = VGT_Omega_DB::update_form($form_id, $data);
            $inserted_id = $form_id;
        } else {
            $inserted_id = VGT_Omega_DB::insert_form($data);
            $success = $inserted_id > 0;
        }

        if ($success) {
            wp_send_json_success([
                'message' => esc_html__('Form configuration stored.', 'vgt-omega-vault'),
                'form_id' => $inserted_id
            ]);
        } else {
            wp_send_json_error(['message' => esc_html__('Failed to write form configuration to database.', 'vgt-omega-vault')], 500);
        }
    }

    public static function handle_delete_form(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized clearance level.', 'vgt-omega-vault')], 403);
        }

        check_ajax_referer('vgt_save_config_nonce', 'security');

        $form_id = isset($_POST['form_id']) ? (int)$_POST['form_id'] : 0;
        if ($form_id === 1) {
            wp_send_json_error(['message' => esc_html__('Der System-Standard-Shortcode [vgt_omega_comlink] darf nicht gelöscht werden.', 'vgt-omega-vault')], 400);
        }
        if ($form_id > 0 && VGT_Omega_DB::delete_form($form_id)) {
            wp_send_json_success(['message' => esc_html__('Form and its submissions purged.', 'vgt-omega-vault')]);
        } else {
            wp_send_json_error(['message' => esc_html__('Failed to purge form.', 'vgt-omega-vault')], 500);
        }
    }

    public static function handle_submit_builder_form(): void {
        try {
            self::execute_security_handshake();
            
            $ip_profile = self::get_ip_profile();
            self::enforce_rate_limit($ip_profile->socket);
            
            if (!empty($_POST['vgt_full_name'])) {
                throw new VGT_Security_Exception('Bot anomaly detected via honeypot.');
            }

            $form_id = isset($_POST['form_id']) ? (int)$_POST['form_id'] : 0;
            if ($form_id <= 0) {
                throw new VGT_Validation_Exception(esc_html__('Missing target form ID.', 'vgt-omega-vault'));
            }

            $form = VGT_Omega_DB::get_form($form_id);
            if (!$form) {
                throw new VGT_Validation_Exception(esc_html__('Form not found.', 'vgt-omega-vault'));
            }

            $form_config = json_decode($form->config, true);
            if (!is_array($form_config) || empty($form_config['fields'])) {
                throw new VGT_Validation_Exception(esc_html__('Form has no fields configured.', 'vgt-omega-vault'));
            }

            // Loop through fields to extract and validate submitted parameters
            $payload = [];
            
            // Handle file uploads if any
            if (!empty($_FILES)) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                
                foreach ($_FILES as $field_name => $file_info) {
                    if (!empty($file_info['name'])) {
                        $upload_overrides = ['test_form' => false];
                        $movefile = wp_handle_upload($file_info, $upload_overrides);
                        if ($movefile && !isset($movefile['error'])) {
                            $payload[$field_name] = [
                                'name' => sanitize_text_field($file_info['name']),
                                'url'  => esc_url_raw($movefile['url']),
                                'type' => 'file_upload'
                            ];
                        } else {
                            throw new VGT_Validation_Exception('Datei-Upload fehlgeschlagen: ' . ($movefile['error'] ?? 'Unbekannter Fehler'));
                        }
                    }
                }
            }

            foreach ($form_config['fields'] as $field) {
                $field_name = $field['id'] ?? '';
                if ($field_name === '') continue;
                if (isset($payload[$field_name])) continue;

                $type = $field['type'] ?? 'text';
                if (in_array($type, ['step_break', 'heading', 'paragraph', 'image', 'video'], true)) {
                    continue;
                }

                $label = $field['label'] ?? '';
                $is_required = !empty($field['required']);
                
                $raw_val = isset($_POST[$field_name]) ? wp_unslash($_POST[$field_name]) : null;

                if ($is_required && ($raw_val === null || $raw_val === '')) {
                    throw new VGT_Validation_Exception(sprintf(esc_html__('Das Feld "%s" ist erforderlich.', 'vgt-omega-vault'), esc_html($label)));
                }

                if ($raw_val !== null && $raw_val !== '') {
                    if ($type === 'email') {
                        $val = trim((string)$raw_val);
                        if (!is_email($val)) {
                            throw new VGT_Validation_Exception(sprintf(esc_html__('Ungültiges E-Mail-Format im Feld "%s".', 'vgt-omega-vault'), esc_html($label)));
                        }
                        $payload[$field_name] = sanitize_email($val);
                    } elseif ($type === 'textarea') {
                        if (preg_match('/[<>]/', (string)$raw_val)) {
                            throw new VGT_Security_Exception('Script injection attempt in dynamic payload.');
                        }
                        $payload[$field_name] = sanitize_textarea_field((string)$raw_val);
                    } elseif ($type === 'number') {
                        $payload[$field_name] = (float)$raw_val;
                    } else {
                        $payload[$field_name] = sanitize_text_field((string)$raw_val);
                    }
                } else {
                    $payload[$field_name] = '';
                }
            }

            $payload_json = json_encode($payload);
            
            // Encrypt and apply AAD lock using site domain + form_id
            $encrypted_payload = VGT_Omega_Crypto::encrypt($payload_json, 'submission_payload', $form_id);
            $encrypted_socket  = VGT_Omega_Crypto::encrypt($ip_profile->socket, 'ip_socket', $form_id);
            $encrypted_claimed = VGT_Omega_Crypto::encrypt($ip_profile->claimed, 'ip_claimed', $form_id);

            $submission_data = [
                'form_id'    => $form_id,
                'payload'    => $encrypted_payload,
                'ip_socket'  => $encrypted_socket,
                'ip_claimed' => $encrypted_claimed
            ];

            if (!VGT_Omega_DB::insert_submission($submission_data)) {
                throw new VGT_Storage_Exception('Database write fault during submission insertion.');
            }

            // Secure alert email without plaintext payload contents
            self::dispatch_notification();

            wp_send_json_success(['message' => esc_html__('Formulardaten verschlüsselt übertragen.', 'vgt-omega-vault')]);

        } catch (VGT_Validation_Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        } catch (VGT_Security_Exception $e) {
            error_log('[VGT_SEC] ' . $e->getMessage());
            wp_send_json_error(['message' => esc_html__('Anfrage aus Sicherheitsgründen abgelehnt.', 'vgt-omega-vault')], 403);
        } catch (VGT_Storage_Exception $e) {
            error_log('[VGT_STORAGE] ' . $e->getMessage());
            wp_send_json_error(['message' => esc_html__('Datenbankfehler beim Sichern.', 'vgt-omega-vault')], 500);
        } catch (Throwable $e) {
            error_log('[VGT_FATAL] ' . $e->getMessage());
            wp_send_json_error(['message' => esc_html__('Systemfehler.', 'vgt-omega-vault')], 500);
        }
    }

    public static function handle_get_submissions(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized clearance level.', 'vgt-omega-vault')], 403);
        }

        check_ajax_referer('vgt_save_config_nonce', 'security');

        global $wpdb;
        $form_id = isset($_POST['form_id']) ? (int)$_POST['form_id'] : 0;
        $page = isset($_POST['paged']) ? max(1, (int)$_POST['paged']) : 1;
        $per_page = 50;

        $submissions = VGT_Omega_DB::get_paginated_submissions($form_id, $page, $per_page);
        $total_count = VGT_Omega_DB::get_total_submissions_count($form_id);

        $decrypted_list = [];
        $table_name = $wpdb->prefix . VGT_Omega_DB::SUBMISSIONS_TABLE;

        foreach ($submissions as $sub) {
            $dec_payload = VGT_Omega_Crypto::decrypt((string)$sub->payload, 'submission_payload', (int)$sub->id, 'payload', $form_id, $table_name);
            $dec_socket  = VGT_Omega_Crypto::decrypt((string)$sub->ip_socket, 'ip_socket', (int)$sub->id, 'ip_socket', $form_id, $table_name);
            $dec_claimed = VGT_Omega_Crypto::decrypt((string)$sub->ip_claimed, 'ip_claimed', (int)$sub->id, 'ip_claimed', $form_id, $table_name);

            $decrypted_list[] = [
                'id'         => $sub->id,
                'payload'    => json_decode($dec_payload, true) ?: $dec_payload,
                'ip_socket'  => $dec_socket,
                'ip_claimed' => $dec_claimed,
                'created_at' => wp_date('Y.m.d H:i:s', strtotime((string)$sub->created_at))
            ];
        }

        wp_send_json_success([
            'submissions' => $decrypted_list,
            'total'       => $total_count,
            'pages'       => (int)ceil($total_count / $per_page),
            'current'     => $page
        ]);
    }

    public static function handle_delete_submission(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized clearance level.', 'vgt-omega-vault')], 403);
        }

        check_ajax_referer('vgt_save_config_nonce', 'security');

        $submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
        if ($submission_id > 0 && VGT_Omega_DB::delete_submission($submission_id)) {
            wp_send_json_success(['message' => esc_html__('Eintrag mathematisch gelöscht.', 'vgt-omega-vault')]);
        } else {
            wp_send_json_error(['message' => esc_html__('Fehler beim Löschen des Eintrags.', 'vgt-omega-vault')], 500);
        }
    }
}
