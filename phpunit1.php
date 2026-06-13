<?php
/**
 * VGT OMEGA VAULT: Unit Tests für den API- & IP-Hardening-Kernel
 * * Diese Suite validiert das duale IP-Erkennungsmodell, den Schutz vor
 * IP-Spoofing und die ordnungsgemäße Filterung von Proxy-Headern.
 * 
 * WICHTIG – NUR FÜR REDTEAM-TESTS IN SANDBOX-UMGEBUNGEN:
 * Dieser Code ist ausschließlich für autorisierte Sicherheitstests (Redteam) in isolierten Sandboxes bestimmt.
 * Jegliche Nutzung außerhalb dieses Rahmens, insbesondere in Produktivsystemen, ist nicht gestattet.
 * 
 * HAFTUNGSAUSSCHLUSS:
 * VisionGaiaTechnology übernimmt KEINE HAFTUNG für Schäden, die durch falschen, unbefugten oder
 * nicht bestimmungsgemäßen Einsatz dieser Tests entstehen. Der Verwender trägt das alleinige Risiko.
 * 
 * TESTZWECK:
 * Diese Suite dient ausschließlich dem Testen der Sicherheitsmechanismen der VGT Omega Vault.
 * Sie validiert das duale IP-Erkennungsmodell, den Schutz vor IP-Spoofing und die ordnungsgemäße
 * Filterung von Proxy-Headern.
 * 
 * @package vgt-omega-vault
 */

// Fallback-Funktionen für stand-alone Ausführung außerhalb des WP-Test-Bootstraps
if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        global $vgt_mock_options;
        return $vgt_mock_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value): bool {
        global $vgt_mock_options;
        $vgt_mock_options[$option] = $value;
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string {
        return strip_tags(trim($str));
    }
}

/**
 * Testklasse für VGT_Omega_API
 */
class VGT_Omega_API_Test extends PHPUnit\Framework\TestCase {

    /**
     * Setzt die Testumgebung vor jedem Testlauf zurück.
     */
    protected function setUp(): void {
        parent::setUp();
        global $vgt_mock_options;
        $vgt_mock_options = [];
        
        // Sicherstellen, dass die Plugin-Klasse geladen ist
        if (!class_exists('VGT_Omega_API')) {
            require_once dirname(__DIR__) . '/includes/class-vgt-omega-api.php';
        }
    }

    /**
     * Hilfsmethode zur Aktivierung/Deaktivierung der Proxy-Option im Mock
     */
    private function setProxyTrustOption(bool $enabled): void {
        update_option('vgt_omega_allow_proxies', $enabled ? '1' : '0');
    }

    /**
     * TEST 1: Standard-Verhalten (Zero-Trust)
     * Verifiziert, dass standardmäßig NUR REMOTE_ADDR vertraut wird und Proxy-Header ignoriert werden.
     */
    public function test_default_behavior_ignores_all_proxy_headers(): void {
        $this->setProxyTrustOption(false);

        $server_mock = [
            'REMOTE_ADDR'          => '198.51.100.42', // Echte öffentliche IP
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195', // Spoof-Versuch
            'HTTP_CLIENT_IP'       => '192.0.2.1',     // Spoof-Versuch
        ];

        $profile = VGT_Omega_API::get_ip_profile($server_mock);

        $this->assertEquals('198.51.100.42', $profile->socket, 'Das Socket-Feld muss REMOTE_ADDR entsprechen.');
        $this->assertEquals('none', $profile->claimed, 'Proxy-Daten müssen im Zero-Trust-Modus "none" sein.');
    }

    /**
     * TEST 2: Gültige Proxy-Erkennung bei explizitem Opt-In
     * Verifiziert, dass bei aktivierter Option ein valider Proxy-Header ausgelesen wird.
     */
    public function test_resolves_valid_proxy_header_when_opt_in_active(): void {
        $this->setProxyTrustOption(true);

        $server_mock = [
            'REMOTE_ADDR'          => '198.51.100.42',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195', // Valide öffentliche IPv4
        ];

        $profile = VGT_Omega_API::get_ip_profile($server_mock);

        $this->assertEquals('198.51.100.42', $profile->socket);
        $this->assertEquals('203.0.113.195', $profile->claimed, 'Der legitime Proxy-Header muss extrahiert werden.');
    }

    /**
     * TEST 3: Blockierung privater IPv4-Ranges im Proxy-Header
     * Verifiziert, dass Angreifer keine internen Netzwerke (z.B. hinter Load-Balancern) vortäuschen können.
     */
    public function test_filters_private_and_reserved_ipv4_ranges(): void {
        $this->setProxyTrustOption(true);

        $private_ips = [
            '10.0.0.1',       // RFC 1918 (Klasse A)
            '172.16.42.1',    // RFC 1918 (Klasse B)
            '192.168.178.1',  // RFC 1918 (Klasse C)
            '127.0.0.1',      // Loopback
            '169.254.1.1',    // Link-Local
            '0.0.0.0'         // Wildcard
        ];

        foreach ($private_ips as $private_ip) {
            $server_mock = [
                'REMOTE_ADDR'          => '198.51.100.42',
                'HTTP_X_FORWARDED_FOR' => $private_ip,
            ];

            $profile = VGT_Omega_API::get_ip_profile($server_mock);

            $this->assertEquals('198.51.100.42', $profile->socket);
            $this->assertEquals(
                'none', 
                $profile->claimed, 
                sprintf('Die private IP %s darf nicht als claimed IP akzeptiert werden.', $private_ip)
            );
        }
    }

    /**
     * TEST 4: Blockierung privater IPv6-Ranges im Proxy-Header
     */
    public function test_filters_private_and_reserved_ipv6_ranges(): void {
        $this->setProxyTrustOption(true);

        $private_ipv6 = [
            '::1',              // Loopback
            'fc00::1',          // Unique Local (ULA)
            'fe80::1',          // Link-Local
            '2001:db8::1'       // Dokumentations-Präfix (RFC 3849)
        ];

        foreach ($private_ipv6 as $ipv6) {
            $server_mock = [
                'REMOTE_ADDR'          => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
                'HTTP_X_FORWARDED_FOR' => $ipv6,
            ];

            $profile = VGT_Omega_API::get_ip_profile($server_mock);

            $this->assertEquals('none', $profile->claimed, "IPv6 $ipv6 hätte als privat/reserviert gefiltert werden müssen.");
        }
    }

    /**
     * TEST 5: Schutz vor kommagetrennten IP-Ketten (IP-Chains)
     * Verifiziert, dass nur das erste Glied (die echte Client-IP) ausgelesen und validiert wird.
     */
    public function test_extracts_first_valid_public_ip_from_comma_separated_chain(): void {
        $this->setProxyTrustOption(true);

        $server_mock = [
            'REMOTE_ADDR'          => '198.51.100.42',
            'HTTP_X_FORWARDED_FOR' => '192.0.2.15, 203.0.113.195, 10.0.0.1', // Erste ist öffentliche Dokumentations-IP (valide)
        ];

        $profile = VGT_Omega_API::get_ip_profile($server_mock);

        $this->assertEquals('192.0.2.15', $profile->claimed, 'Es muss das erste Element der IP-Kette evaluiert werden.');
    }

    /**
     * TEST 6: Erste IP in der Kette ist privat, zweite öffentlich
     * Der Parser muss abbrechen und "none" zurückliefern, da der unmittelbare Client privat behauptet wurde.
     */
    public function test_aborts_chain_evaluation_if_first_ip_is_private(): void {
        $this->setProxyTrustOption(true);

        $server_mock = [
            'REMOTE_ADDR'          => '198.51.100.42',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.5, 203.0.113.195', // Erste IP privat, zweite öffentlich
        ];

        $profile = VGT_Omega_API::get_ip_profile($server_mock);

        $this->assertEquals('none', $profile->claimed, 'Wenn der deklarierte Absender privat ist, darf die Kette nicht weiter geparst werden.');
    }

    /**
     * TEST 7: Abwehr von Header-Injection-Versuchen (Malicious Payload)
     * Verifiziert, dass ungültige IP-Formate, XSS-Versuche oder SQL-Injections im Header unschädlich gemacht werden.
     */
    public function test_sanitizes_malicious_or_malformed_header_inputs(): void {
        $this->setProxyTrustOption(true);

        $malicious_payloads = [
            '203.0.113.195<script>alert(1)</script>',
            '203.0.113.195; DROP TABLE wp_users',
            'not-an-ip-address',
            '203.0.113.195/bin/sh',
            '1234.56.78.90' // Ungültiges Oktett
        ];

        foreach ($malicious_payloads as $payload) {
            $server_mock = [
                'REMOTE_ADDR'          => '198.51.100.42',
                'HTTP_X_FORWARDED_FOR' => $payload,
            ];

            $profile = VGT_Omega_API::get_ip_profile($server_mock);

            $this->assertEquals('198.51.100.42', $profile->socket);
            $this->assertEquals('none', $profile->claimed, 'Manipulierte Payloads im Header müssen als ungültig abgewiesen werden.');
        }
    }

    /**
     * TEST 8: Priorisierung der PHP-Konstante (Hardening Override)
     * Führt den Test in einem isolierten PHP-Prozess aus, um eine saubere Konstanten-Definition zu garantieren.
     * * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_constant_override_takes_precedence_over_database_option(): void {
        // Option in der DB ist deaktiviert (false)
        $this->setProxyTrustOption(false);
        
        // Konstanten-Override erzwingt jedoch die Proxy-Akzeptanz (true)
        if (!defined('VGT_ALLOW_PROXIES')) {
            define('VGT_ALLOW_PROXIES', true);
        }

        $server_mock = [
            'REMOTE_ADDR'          => '198.51.100.42',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        ];

        $profile = VGT_Omega_API::get_ip_profile($server_mock);

        $this->assertEquals('203.0.113.195', $profile->claimed, 'Der Konstanten-Override muss die Datenbank-Option überschreiben.');
    }

    /**
     * TEST 9: Cloudflare IP-Validierung vertraut echten Cloudflare-IPs
     */
    public function test_cloudflare_ip_validation_trusts_legitimate_ranges(): void {
        $this->setProxyTrustOption(true);

        // IP aus dem legitimen Cloudflare-Bereich (z.B. 108.162.193.100 in 108.162.192.0/18)
        $server_mock = [
            'REMOTE_ADDR'             => '108.162.193.100', 
            'HTTP_CF_CONNECTING_IP'  => '203.0.113.195', // Behauptete Client-IP
        ];

        $profile = VGT_Omega_API::get_ip_profile($server_mock);

        $this->assertEquals('108.162.193.100', $profile->socket);
        $this->assertEquals('203.0.113.195', $profile->claimed, 'Cloudflare-Header muss bei legitimer Cloudflare-IP ausgelesen werden.');
    }

    /**
     * TEST 10: Cloudflare IP-Validierung weist gefälschte Anfragen ab
     */
    public function test_cloudflare_ip_validation_rejects_spoofed_non_cf_ranges(): void {
        $this->setProxyTrustOption(true);

        // Angreifer-IP, die nicht zu Cloudflare gehört, sendet gefälschten HTTP_CF_CONNECTING_IP-Header
        $server_mock = [
            'REMOTE_ADDR'             => '198.51.100.42', // Nicht-Cloudflare
            'HTTP_CF_CONNECTING_IP'  => '203.0.113.195', // Spoofed
        ];

        $profile = VGT_Omega_API::get_ip_profile($server_mock);

        $this->assertEquals('198.51.100.42', $profile->socket);
        $this->assertEquals('none', $profile->claimed, 'Gefälschte Cloudflare-Header von Nicht-Cloudflare-IPs müssen ignoriert werden.');
    }
}
