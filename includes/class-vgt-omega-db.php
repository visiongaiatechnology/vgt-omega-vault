<?php
/**
 * VGT OMEGA VAULT: Datenbank-Kernel & Schema-Migration
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT SECURE ZONE: DIRECT ACCESS FORBIDDEN');
}

final class VGT_Omega_DB {
    
    public const TABLE_NAME = 'vgt_omega_audits';
    public const FORMS_TABLE = 'vgt_omega_forms';
    public const SUBMISSIONS_TABLE = 'vgt_omega_submissions';

    /**
     * Erstellt/Aktualisiert die Tabellenstruktur.
     */
    public static function install(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table = $wpdb->prefix . self::TABLE_NAME;
        $forms_table = $wpdb->prefix . self::FORMS_TABLE;
        $submissions_table = $wpdb->prefix . self::SUBMISSIONS_TABLE;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. Legacy Audits Table
        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(1024) NOT NULL,
            email varchar(512) NOT NULL,
            vector varchar(512) NOT NULL,
            threat text NOT NULL,
            ip_origin varchar(255) DEFAULT '' NOT NULL,
            ip_socket varchar(255) DEFAULT '' NOT NULL,
            ip_claimed varchar(255) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // 2. Forms Table
        $sql_forms = "CREATE TABLE $forms_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            type varchar(50) DEFAULT 'form' NOT NULL,
            config longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_forms);

        // 3. Submissions Table
        $sql_subs = "CREATE TABLE $submissions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            payload longtext NOT NULL,
            ip_socket varchar(512) DEFAULT '' NOT NULL,
            ip_claimed varchar(512) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_form_id (form_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_subs);

        // EXTRA SECURE ALIGNMENT for audits table
        $columns = $wpdb->get_col("DESCRIBE $table", 0);
        if (!empty($columns)) {
            if (!in_array('ip_socket', $columns, true)) {
                $wpdb->query("ALTER TABLE $table ADD ip_socket varchar(255) DEFAULT '' NOT NULL AFTER ip_origin");
            }
            if (!in_array('ip_claimed', $columns, true)) {
                $wpdb->query("ALTER TABLE $table ADD ip_claimed varchar(255) DEFAULT '' NOT NULL AFTER ip_socket");
            }

            $detailed_columns = $wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A);
            foreach ($detailed_columns as $col) {
                $name = $col['Field'];
                $type = strtolower($col['Type']);
                if ($name === 'domain' && (strpos($type, 'text') !== false || strpos($type, 'varchar(512)') !== false)) {
                    $wpdb->query("ALTER TABLE $table MODIFY domain varchar(1024) NOT NULL");
                }
                if ($name === 'email' && (strpos($type, 'text') !== false || strpos($type, 'varchar(255)') !== false)) {
                    $wpdb->query("ALTER TABLE $table MODIFY email varchar(512) NOT NULL");
                }
                if ($name === 'vector' && (strpos($type, 'text') !== false || strpos($type, 'varchar(255)') !== false)) {
                    $wpdb->query("ALTER TABLE $table MODIFY vector varchar(512) NOT NULL");
                }
            }
        }
        self::maybe_populate_legacy_comlink_form();
    }

    /**
     * Pre-populates the database with the legacy comlink form at ID 1 if no forms exist.
     */
    public static function maybe_populate_legacy_comlink_form(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::FORMS_TABLE;
        
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table WHERE id = %d", 1));
        if ((int)$exists > 0) {
            return;
        }

        $config_arr = [
            'id' => 1,
            'title' => 'Secure Com-Link (Legacy)',
            'type' => 'form',
            'fields' => [
                [
                    'id' => 'vgt_domain',
                    'type' => 'text',
                    'label' => 'Target Architecture (Domain / IP)',
                    'placeholder' => 'https://domain.com oder 192.168.1.XXX',
                    'required' => true,
                    'options' => '',
                    'media_url' => ''
                ],
                [
                    'id' => 'vgt_email',
                    'type' => 'email',
                    'label' => 'Operative Auth (E-Mail)',
                    'placeholder' => 'operative@visiongaiatechnology.de',
                    'required' => true,
                    'options' => '',
                    'media_url' => ''
                ],
                [
                    'id' => 'vgt_vector',
                    'type' => 'text',
                    'label' => 'Threat Vector (Subject)',
                    'placeholder' => 'Security Audit, System Upgrade...',
                    'required' => true,
                    'options' => '',
                    'media_url' => ''
                ],
                [
                    'id' => 'vgt_threat',
                    'type' => 'textarea',
                    'label' => 'Payload Data (Note)',
                    'placeholder' => 'Initialisieren Sie die Parameter der Anfrage...',
                    'required' => true,
                    'options' => '',
                    'media_url' => ''
                ]
            ],
            'settings' => [
                'theme' => 'dark',
                'gold_accent' => '#d4af37',
                'background_color' => '#030303',
                'background_image' => '',
                'button_text' => 'Initialize Encryption',
                'border_radius' => '16px',
                'padding' => '3rem',
                'width' => '780px',
                'subtitle' => 'End-to-End Encrypted Tunnel'
            ]
        ];

        $wpdb->insert($table, [
            'id' => 1,
            'title' => 'Secure Com-Link (Legacy)',
            'type' => 'form',
            'config' => wp_json_encode($config_arr)
        ], ['%d', '%s', '%s', '%s']);
    }

    /* ==============================================================================
     * AUDITS CRUD (LEGACY SUPPORT)
     * ============================================================================== */
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
            array_fill(0, count($data), '%s')
        );
    }

    public static function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete($wpdb->prefix . self::TABLE_NAME, ['id' => $id], ['%d']);
    }

    /* ==============================================================================
     * FORMS & FUNNELS CRUD (NEW BUILDER)
     * ============================================================================== */
    public static function insert_form(array $data): int {
        global $wpdb;
        $table = $wpdb->prefix . self::FORMS_TABLE;
        $result = $wpdb->insert($table, $data, ['%s', '%s', '%s']);
        return $result ? (int) $wpdb->insert_id : 0;
    }

    public static function update_form(int $id, array $data): bool {
        global $wpdb;
        $table = $wpdb->prefix . self::FORMS_TABLE;
        return $wpdb->update($table, $data, ['id' => $id], ['%s', '%s', '%s'], ['%d']) !== false;
    }

    public static function get_form(int $id): ?stdClass {
        global $wpdb;
        $table = $wpdb->prefix . self::FORMS_TABLE;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        return $row ?: null;
    }

    public static function get_all_forms(): array {
        global $wpdb;
        $table = $wpdb->prefix . self::FORMS_TABLE;
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC") ?: [];
    }

    public static function delete_form(int $id): bool {
        global $wpdb;
        // Also delete submissions associated with this form
        $subs_table = $wpdb->prefix . self::SUBMISSIONS_TABLE;
        $wpdb->delete($subs_table, ['form_id' => $id], ['%d']);
        
        $table = $wpdb->prefix . self::FORMS_TABLE;
        return (bool) $wpdb->delete($table, ['id' => $id], ['%d']);
    }

    /* ==============================================================================
     * SUBMISSIONS CRUD
     * ============================================================================== */
    public static function insert_submission(array $data): bool {
        global $wpdb;
        $table = $wpdb->prefix . self::SUBMISSIONS_TABLE;
        return (bool) $wpdb->insert(
            $table, 
            $data, 
            ['%d', '%s', '%s', '%s']
        );
    }

    public static function get_paginated_submissions(int $form_id, int $page = 1, int $per_page = 20): array {
        global $wpdb;
        $table = $wpdb->prefix . self::SUBMISSIONS_TABLE;
        $offset = ($page - 1) * $per_page;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE form_id = %d ORDER BY created_at DESC LIMIT %d, %d", 
            $form_id, $offset, $per_page
        )) ?: [];
    }

    public static function get_total_submissions_count(int $form_id): int {
        global $wpdb;
        $table = $wpdb->prefix . self::SUBMISSIONS_TABLE;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table WHERE form_id = %d", $form_id));
    }

    public static function delete_submission(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . self::SUBMISSIONS_TABLE;
        return (bool) $wpdb->delete($table, ['id' => $id], ['%d']);
    }
}