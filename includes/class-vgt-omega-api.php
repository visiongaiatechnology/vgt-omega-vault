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
            $proxy_headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'];
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

        if (!$nonce_valid && !$token_valid) {
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
}
