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

    /**
     * Erstellt/Aktualisiert die Tabellenstruktur.
     * Verwendet performante, indizierbare VARCHAR-Spalten (Issue 2 gelöst).
     * Unterstützt das neue Dual-IP-Konzept (V5.3.0) für fälschungssichere Datenerfassung.
     * Härtung in V5.4.1: Erzwungenes physikalisches Spalten-Altering bei Legacy-Datenbanken.
     */
    public static function install(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(512) NOT NULL,
            email varchar(255) NOT NULL,
            vector varchar(255) NOT NULL,
            threat text NOT NULL,
            ip_origin varchar(255) DEFAULT '' NOT NULL,
            ip_socket varchar(255) DEFAULT '' NOT NULL,
            ip_claimed varchar(255) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // EXTRA SECURE ALIGNMENT: Einige SQL-Server-Konfigurationen ignorieren dbDelta-Updates.
        // Wir prüfen die Struktur direkt ab und erzwingen die Existenz der Spalten und optimierten Typen.
        $columns = $wpdb->get_col("DESCRIBE $table", 0);
        
        if (!empty($columns)) {
            // 1. Neue Dual-IP-Spalten physisch erzwingen falls nicht existent
            if (!in_array('ip_socket', $columns, true)) {
                $wpdb->query("ALTER TABLE $table ADD ip_socket varchar(255) DEFAULT '' NOT NULL AFTER ip_origin");
            }
            if (!in_array('ip_claimed', $columns, true)) {
                $wpdb->query("ALTER TABLE $table ADD ip_claimed varchar(255) DEFAULT '' NOT NULL AFTER ip_socket");
            }

            // 2. Performance-Tuning: Umwandlung von TEXT-Spalten in VARCHARs für ältere Versionen
            $detailed_columns = $wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A);
            foreach ($detailed_columns as $col) {
                $name = $col['Field'];
                $type = strtolower($col['Type']);
                
                if ($name === 'domain' && strpos($type, 'text') !== false) {
                    $wpdb->query("ALTER TABLE $table MODIFY domain varchar(512) NOT NULL");
                }
                if ($name === 'email' && strpos($type, 'text') !== false) {
                    $wpdb->query("ALTER TABLE $table MODIFY email varchar(255) NOT NULL");
                }
                if ($name === 'vector' && strpos($type, 'text') !== false) {
                    $wpdb->query("ALTER TABLE $table MODIFY vector varchar(255) NOT NULL");
                }
            }
        }
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
}