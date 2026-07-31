<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGT_Omega_DB
{
    public const TABLE_NAME = 'vgt_omega_audits';
    public const FORMS_TABLE = 'vgt_omega_forms';
    public const SUBMISSIONS_TABLE = 'vgt_omega_submissions';
    public const TOKENS_TABLE = 'vgt_omega_tokens';
    public const RATE_TABLE = 'vgt_omega_rate_limits';

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $audits = self::table(self::TABLE_NAME);
        $forms = self::table(self::FORMS_TABLE);
        $submissions = self::table(self::SUBMISSIONS_TABLE);
        $tokens = self::table(self::TOKENS_TABLE);
        $rates = self::table(self::RATE_TABLE);

        dbDelta("CREATE TABLE {$audits} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            domain varchar(2048) NOT NULL,
            email varchar(1024) NOT NULL,
            vector varchar(1024) NOT NULL,
            threat longtext NOT NULL,
            ip_origin varchar(1024) NOT NULL DEFAULT '',
            ip_socket varchar(1024) NOT NULL DEFAULT '',
            ip_claimed varchar(1024) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB {$charset};");

        dbDelta("CREATE TABLE {$forms} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'form',
            status varchar(16) NOT NULL DEFAULT 'active',
            config longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status_updated (status, updated_at),
            KEY idx_updated_at (updated_at)
        ) ENGINE=InnoDB {$charset};");

        dbDelta("CREATE TABLE {$submissions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            request_id char(36) NULL DEFAULT NULL,
            payload longtext NOT NULL,
            payload_hash char(64) NOT NULL DEFAULT '',
            ip_socket varchar(2048) NOT NULL DEFAULT '',
            ip_claimed varchar(2048) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_form_created (form_id, created_at),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB {$charset};");

        dbDelta("CREATE TABLE {$tokens} (
            token_hash char(64) NOT NULL,
            form_id bigint(20) unsigned NOT NULL,
            ip_hash char(64) NOT NULL,
            expires_at datetime NOT NULL,
            used_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (token_hash),
            KEY idx_expires_at (expires_at),
            KEY idx_form_ip (form_id, ip_hash)
        ) ENGINE=InnoDB {$charset};");

        dbDelta("CREATE TABLE {$rates} (
            bucket_key char(64) NOT NULL,
            window_start bigint(20) unsigned NOT NULL,
            counter int(10) unsigned NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (bucket_key),
            KEY idx_updated_at (updated_at)
        ) ENGINE=InnoDB {$charset};");

        self::normalizeRequestIdsAndEnsureIndex();
        self::assertTransactionalTables([
            $audits,
            $forms,
            $submissions,
            $tokens,
            $rates,
        ]);
        self::maybePopulateLegacyForm();
        self::migrateLegacyForms();
        self::migrateLegacyCiphertexts();
    }

    public static function maybe_populate_legacy_comlink_form(): void
    {
        self::maybePopulateLegacyForm();
    }

    public static function maybePopulateLegacyForm(): void
    {
        global $wpdb;
        $table = self::table(self::FORMS_TABLE);
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$table} WHERE id = %d", 1));
        if ($exists > 0) {
            return;
        }

        $config = [
            'id' => 1,
            'title' => 'Secure Com-Link',
            'type' => 'form',
            'schema_version' => 2,
            'fields' => [
                ['id' => 'vgt_domain', 'type' => 'text', 'label' => 'Target Architecture (Domain / IP)', 'placeholder' => 'https://domain.example', 'required' => true, 'options' => [], 'media_url' => '', 'max_length' => 2048],
                ['id' => 'vgt_email', 'type' => 'email', 'label' => 'Contact E-Mail', 'placeholder' => 'name@example.org', 'required' => true, 'options' => [], 'media_url' => '', 'max_length' => 320],
                ['id' => 'vgt_vector', 'type' => 'text', 'label' => 'Subject', 'placeholder' => 'Security audit', 'required' => true, 'options' => [], 'media_url' => '', 'max_length' => 500],
                ['id' => 'vgt_threat', 'type' => 'textarea', 'label' => 'Message', 'placeholder' => 'Describe your request', 'required' => true, 'options' => [], 'media_url' => '', 'max_length' => VGT_Omega_Config::MAX_TEXT_BYTES],
            ],
            'settings' => [
                'theme' => 'dark',
                'button_text' => 'Submit securely',
                'subtitle' => 'TLS-protected transmission with encrypted storage',
                'consent_required' => true,
            ],
        ];

        $encoded = wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $result = $wpdb->insert(
            $table,
            ['id' => 1, 'title' => 'Secure Com-Link', 'type' => 'form', 'status' => 'active', 'config' => $encoded],
            ['%d', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            throw new \VGTOmegaVault\StorageException('Default form creation failed: ' . $wpdb->last_error);
        }
    }

    private static function migrateLegacyForms(): void
    {
        global $wpdb;
        $table = self::table(self::FORMS_TABLE);
        $rows = $wpdb->get_results("SELECT id, title, type, config FROM {$table}") ?: [];
        $warnings = [];

        foreach ($rows as $row) {
            if (!is_string($row->config)) {
                $warnings[] = (int) $row->id;
                continue;
            }

            $raw = str_replace(
                'End-to-End Encrypted Tunnel',
                'TLS-protected transmission with encrypted storage',
                $row->config
            );

            try {
                $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new \VGTOmegaVault\SecurityException('Legacy form configuration validation failed.');
                }

                if (!isset($decoded['title']) && isset($row->title) && is_string($row->title)) {
                    $decoded['title'] = $row->title;
                }
                if (!isset($decoded['type']) && isset($row->type) && is_string($row->type)) {
                    $decoded['type'] = $row->type;
                }

                $migrated = VGT_Omega_Schema::migrateLegacy($decoded, (int) $row->id);
                $encoded = wp_json_encode(
                    $migrated,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                );
                $result = $wpdb->update(
                    $table,
                    [
                        'title' => $migrated['title'],
                        'type' => $migrated['type'],
                        'config' => $encoded,
                        'updated_at' => current_time('mysql', true),
                    ],
                    ['id' => (int) $row->id],
                    ['%s', '%s', '%s', '%s'],
                    ['%d']
                );
                if ($result === false) {
                    throw new \VGTOmegaVault\StorageException('Legacy form configuration write failed.');
                }
            } catch (\Throwable $e) {
                $warnings[] = (int) $row->id;
                error_log('[VGT OMEGA FORM MIGRATION][' . (int) $row->id . '] ' . $e->getMessage());
            }
        }

        update_option('vgt_omega_migration_warnings', array_values(array_unique($warnings)), false);
    }

    private static function migrateLegacyCiphertexts(): void
    {
        global $wpdb;

        $submissionTable = self::table(self::SUBMISSIONS_TABLE);
        $auditTable = self::table(self::TABLE_NAME);

        self::migrateCipherTable(
            $submissionTable,
            ['payload', 'ip_socket', 'ip_claimed'],
            static function(object $row, string $column): array {
                $formId = (int) $row->form_id;
                $context = $column === 'payload' ? 'submission_payload' : $column;
                return [$context, $formId];
            },
            static function(object $row, array $updates): array {
                if (isset($updates['payload']) && is_string($updates['payload'])) {
                    $updates['payload_hash'] = VGT_Omega_Crypto::integrityHash(
                        $updates['payload'],
                        'submission-payload'
                    );
                }
                return $updates;
            }
        );

        self::migrateCipherTable(
            $auditTable,
            ['domain', 'email', 'vector', 'threat', 'ip_origin', 'ip_socket', 'ip_claimed'],
            static fn(object $row, string $column): array => [$column, null],
            static fn(object $row, array $updates): array => $updates
        );
    }

    /**
     * @param list<string> $columns
     * @param callable(object,string):array{0:string,1:?int} $contextResolver
     * @param callable(object,array<string,string>):array<string,string> $finalizer
     */
    private static function migrateCipherTable(
        string $table,
        array $columns,
        callable $contextResolver,
        callable $finalizer
    ): void {
        global $wpdb;

        $lastId = 0;
        do {
            $selectColumns = array_merge(['id'], $columns);
            if ($table === self::table(self::SUBMISSIONS_TABLE)) {
                $selectColumns[] = 'form_id';
            }

            $columnSql = implode(', ', array_map(
                static function(string $column): string {
                    if (preg_match('/^[a-z0-9_]+$/', $column) !== 1) {
                        throw new \VGTOmegaVault\SecurityException('Migration column validation failed.');
                    }
                    return '`' . $column . '`';
                },
                array_values(array_unique($selectColumns))
            ));

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT {$columnSql} FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT 100",
                $lastId
            )) ?: [];

            if ($rows === []) {
                break;
            }

            self::begin();
            try {
                foreach ($rows as $row) {
                    $rowId = isset($row->id) ? (int) $row->id : 0;
                    if ($rowId <= $lastId) {
                        throw new \VGTOmegaVault\SecurityException('Cipher migration row-order validation failed.');
                    }

                    $updates = [];
                    foreach ($columns as $column) {
                        $ciphertext = isset($row->{$column}) && is_string($row->{$column})
                            ? $row->{$column}
                            : '';
                        if ($ciphertext === '' || VGT_Omega_Crypto::isCurrentEnvelope($ciphertext)) {
                            continue;
                        }

                        [$context, $formId] = $contextResolver($row, $column);
                        $plaintext = VGT_Omega_Crypto::decrypt(
                            $ciphertext,
                            $context,
                            null,
                            null,
                            $formId
                        );
                        $updates[$column] = VGT_Omega_Crypto::encrypt($plaintext, $context, $formId);
                    }

                    $updates = $finalizer($row, $updates);
                    if ($updates !== []) {
                        $formats = array_fill(0, count($updates), '%s');
                        $updated = $wpdb->update(
                            $table,
                            $updates,
                            ['id' => $rowId],
                            $formats,
                            ['%d']
                        );
                        if ($updated === false) {
                            throw new \VGTOmegaVault\StorageException('Cipher migration write failed.');
                        }
                    }

                    $lastId = $rowId;
                }
                self::commit();
            } catch (\Throwable $e) {
                self::rollback();
                throw $e;
            }
        } while (true);
    }

    /** @return list<object> */
    public static function get_paginated_audits(int $page = 1, int $per_page = 20): array
    {
        global $wpdb;
        $table = self::table(self::TABLE_NAME);
        $page = max(1, $page);
        $perPage = max(1, min(100, $per_page));
        $offset = ($page - 1) * $perPage;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
            $perPage,
            $offset
        )) ?: [];
    }

    public static function get_total_count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(id) FROM ' . self::table(self::TABLE_NAME));
    }

    /** @param array<string,string> $data */
    public static function insert(array $data): bool
    {
        global $wpdb;
        $allowed = ['domain', 'email', 'vector', 'threat', 'ip_origin', 'ip_socket', 'ip_claimed'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        return $wpdb->insert(
            self::table(self::TABLE_NAME),
            $filtered,
            array_fill(0, count($filtered), '%s')
        ) !== false;
    }

    public static function delete(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete(self::table(self::TABLE_NAME), ['id' => $id], ['%d']) !== false;
    }

    /** @param array{title:string,type:string,config:string} $data */
    public static function insert_form(array $data): int
    {
        global $wpdb;
        $result = $wpdb->insert(
            self::table(self::FORMS_TABLE),
            ['title' => $data['title'], 'type' => $data['type'], 'status' => 'active', 'config' => $data['config']],
            ['%s', '%s', '%s', '%s']
        );
        return $result === false ? 0 : (int) $wpdb->insert_id;
    }

    /** @param array{title:string,type:string,config:string} $data */
    public static function update_form(int $id, array $data): bool
    {
        global $wpdb;
        return $wpdb->update(
            self::table(self::FORMS_TABLE),
            [
                'title' => $data['title'],
                'type' => $data['type'],
                'config' => $data['config'],
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        ) !== false;
    }

    public static function get_form(int $id): ?stdClass
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table(self::FORMS_TABLE) . ' WHERE id = %d',
            $id
        ));
        return $row instanceof stdClass ? $row : null;
    }

    /** @return list<object> */
    public static function get_all_forms(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT * FROM ' . self::table(self::FORMS_TABLE) . ' ORDER BY updated_at DESC, id DESC'
        ) ?: [];
    }

    public static function markFormDeleting(int $id): void
    {
        global $wpdb;
        $table = self::table(self::FORMS_TABLE);
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'deleting', updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND status IN ('active', 'deleting')",
            $id
        ));
        if ($affected === false) {
            throw new \VGTOmegaVault\StorageException('Form deletion state update failed.');
        }
        if ($affected === 0) {
            $row = self::get_form($id);
            if ($row === null) {
                throw new \VGTOmegaVault\ValidationException(__('Form not found.', 'vgt-omega-vault'), 404);
            }
            throw new \VGTOmegaVault\SecurityException('Form state validation failed.');
        }
    }

    public static function assertFormActiveForUpdate(int $id): void
    {
        global $wpdb;
        $table = self::table(self::FORMS_TABLE);
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$table} WHERE id = %d FOR UPDATE",
            $id
        ));
        if (!is_string($status) || !hash_equals('active', $status)) {
            throw new \VGTOmegaVault\ValidationException(
                __('Form is unavailable.', 'vgt-omega-vault'),
                409
            );
        }
    }

    /** @return list<object> */
    public static function getSubmissionBatchForForm(int $formId, int $limit = 100): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table(self::SUBMISSIONS_TABLE) .
            ' WHERE form_id = %d ORDER BY id ASC LIMIT %d',
            $formId,
            max(1, min(500, $limit))
        )) ?: [];
    }

    public static function delete_form(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete(self::table(self::FORMS_TABLE), ['id' => $id], ['%d']) !== false;
    }

    /**
     * @param array{form_id:int,request_id:string,payload:string,payload_hash:string,ip_socket:string,ip_claimed:string} $data
     */
    public static function insert_submission(array $data): bool
    {
        global $wpdb;
        return $wpdb->insert(
            self::table(self::SUBMISSIONS_TABLE),
            $data,
            ['%d', '%s', '%s', '%s', '%s', '%s']
        ) !== false;
    }

    public static function get_submission(int $id): ?stdClass
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table(self::SUBMISSIONS_TABLE) . ' WHERE id = %d',
            $id
        ));
        return $row instanceof stdClass ? $row : null;
    }

    /** @return list<object> */
    public static function get_paginated_submissions(int $form_id, int $page = 1, int $per_page = 20): array
    {
        global $wpdb;
        $page = max(1, $page);
        $perPage = max(1, min(100, $per_page));
        $offset = ($page - 1) * $perPage;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table(self::SUBMISSIONS_TABLE) . ' WHERE form_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
            $form_id,
            $perPage,
            $offset
        )) ?: [];
    }

    /** @return list<object> */
    public static function getAllSubmissionsForForm(int $formId): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table(self::SUBMISSIONS_TABLE) . ' WHERE form_id = %d ORDER BY id ASC',
            $formId
        )) ?: [];
    }

    public static function get_total_submissions_count(int $form_id): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(id) FROM ' . self::table(self::SUBMISSIONS_TABLE) . ' WHERE form_id = %d',
            $form_id
        ));
    }

    public static function delete_submission(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete(self::table(self::SUBMISSIONS_TABLE), ['id' => $id], ['%d']) !== false;
    }

    /** @return list<object> */
    public static function getExpiredSubmissions(int $days, int $limit): array
    {
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - (max(1, $days) * DAY_IN_SECONDS));
        return $wpdb->get_results($wpdb->prepare(
            'SELECT id FROM ' . self::table(self::SUBMISSIONS_TABLE) . ' WHERE created_at < %s ORDER BY id ASC LIMIT %d',
            $cutoff,
            max(1, min(1000, $limit))
        )) ?: [];
    }

    public static function createOneTimeToken(string $tokenHash, int $formId, string $ipHash, int $ttl): void
    {
        global $wpdb;
        $expires = gmdate('Y-m-d H:i:s', time() + max(60, $ttl));
        $result = $wpdb->insert(
            self::table(self::TOKENS_TABLE),
            [
                'token_hash' => $tokenHash,
                'form_id' => $formId,
                'ip_hash' => $ipHash,
                'expires_at' => $expires,
            ],
            ['%s', '%d', '%s', '%s']
        );

        if ($result === false) {
            throw new \VGTOmegaVault\StorageException('One-time credential creation failed.');
        }
    }

    public static function consumeOneTimeToken(string $tokenHash, int $formId, string $ipHash): bool
    {
        global $wpdb;
        $table = self::table(self::TOKENS_TABLE);
        $now = gmdate('Y-m-d H:i:s');

        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET used_at = %s
             WHERE token_hash = %s
               AND form_id = %d
               AND ip_hash = %s
               AND used_at IS NULL
               AND expires_at >= %s",
            $now,
            $tokenHash,
            $formId,
            $ipHash,
            $now
        ));

        return $affected === 1;
    }

    public static function hitRateBucket(string $bucketKey, int $windowStart): int
    {
        global $wpdb;
        $table = self::table(self::RATE_TABLE);

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (bucket_key, window_start, counter, updated_at)
             VALUES (%s, %d, 1, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                counter = IF(window_start = VALUES(window_start), counter + 1, 1),
                window_start = VALUES(window_start),
                updated_at = UTC_TIMESTAMP()",
            $bucketKey,
            $windowStart
        );

        if ($wpdb->query($sql) === false) {
            throw new \VGTOmegaVault\StorageException('Rate limit state update failed.');
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT counter FROM {$table} WHERE bucket_key = %s AND window_start = %d",
            $bucketKey,
            $windowStart
        ));
    }

    public static function purgeExpiredSecurityState(): void
    {
        global $wpdb;
        $tokens = self::table(self::TOKENS_TABLE);
        $rates = self::table(self::RATE_TABLE);
        $wpdb->query("DELETE FROM {$tokens} WHERE expires_at < UTC_TIMESTAMP() OR used_at IS NOT NULL");
        $wpdb->query("DELETE FROM {$rates} WHERE updated_at < (UTC_TIMESTAMP() - INTERVAL 2 DAY)");
    }

    public static function clearMigrationWarning(int $formId): void
    {
        $warnings = get_option('vgt_omega_migration_warnings', []);
        if (!is_array($warnings)) {
            return;
        }

        $filtered = array_values(array_filter(
            $warnings,
            static fn(mixed $value): bool => (int) $value !== $formId
        ));
        update_option('vgt_omega_migration_warnings', $filtered, false);
    }

    public static function begin(): void
    {
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new \VGTOmegaVault\StorageException('Transaction start failed.');
        }
    }

    public static function commit(): void
    {
        global $wpdb;
        if ($wpdb->query('COMMIT') === false) {
            throw new \VGTOmegaVault\StorageException('Transaction commit failed.');
        }
    }

    public static function rollback(): void
    {
        global $wpdb;
        $wpdb->query('ROLLBACK');
    }

    private static function normalizeRequestIdsAndEnsureIndex(): void
    {
        global $wpdb;
        $table = self::table(self::SUBMISSIONS_TABLE);

        if ($wpdb->query("UPDATE {$table} SET request_id = NULL WHERE request_id = ''") === false) {
            throw new \VGTOmegaVault\StorageException('Submission request identifier migration failed.');
        }

        $index = $wpdb->get_var($wpdb->prepare(
            "SELECT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = %s
               AND INDEX_NAME = %s
             LIMIT 1",
            $table,
            'uq_request_id'
        ));

        if ($index === null) {
            if ($wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uq_request_id (request_id)") === false) {
                throw new \VGTOmegaVault\StorageException('Submission request identifier index creation failed.');
            }
        }
    }

    /**
     * @param list<string> $tables
     */
    private static function assertTransactionalTables(array $tables): void
    {
        global $wpdb;

        foreach ($tables as $table) {
            $engine = $wpdb->get_var($wpdb->prepare(
                "SELECT ENGINE
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = %s
                 LIMIT 1",
                $table
            ));

            if (!is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) {
                throw new \VGTOmegaVault\StorageException('Transactional database engine unavailable for ' . $table);
            }
        }
    }

    private static function table(string $suffix): string
    {
        global $wpdb;
        if (preg_match('/^[a-z0-9_]+$/', $suffix) !== 1) {
            throw new \VGTOmegaVault\SecurityException('Database table validation failed.');
        }
        return $wpdb->prefix . $suffix;
    }
}
