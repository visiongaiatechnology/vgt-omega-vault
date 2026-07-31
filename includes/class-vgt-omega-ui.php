<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGT_Omega_UI
{
    public static function render(): void
    {
        if (!current_user_can(VGT_Omega_Config::CAPABILITY)) {
            wp_die(esc_html__('Forbidden.', 'vgt-omega-vault'), '', ['response' => 403]);
        }

        $tab = isset($_GET['tab']) && is_string($_GET['tab'])
            ? sanitize_key(wp_unslash($_GET['tab']))
            : 'forms';
        if (!in_array($tab, ['forms', 'submissions', 'legacy', 'settings'], true)) {
            $tab = 'forms';
        }

        echo '<div class="wrap vgt-admin">';
        echo '<h1>' . esc_html__('VGT Omega Vault', 'vgt-omega-vault') . '</h1>';
        self::renderNotice();
        self::renderNavigation($tab);

        match ($tab) {
            'submissions' => self::renderSubmissions(),
            'legacy' => self::renderLegacy(),
            'settings' => self::renderSettings(),
            default => self::renderForms(),
        };

        echo '</div>';
    }

    private static function renderNavigation(string $active): void
    {
        $tabs = [
            'forms' => __('Forms', 'vgt-omega-vault'),
            'submissions' => __('Submissions', 'vgt-omega-vault'),
            'legacy' => __('Legacy records', 'vgt-omega-vault'),
            'settings' => __('Settings', 'vgt-omega-vault'),
        ];

        echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__('Vault sections', 'vgt-omega-vault') . '">';
        foreach ($tabs as $slug => $label) {
            $url = add_query_arg(
                ['page' => 'vgt-omega-vault', 'tab' => $slug],
                admin_url('admin.php')
            );
            $class = $active === $slug ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    private static function renderNotice(): void
    {
        if (!VGT_Omega_Config::isReady()) {
            $errorId = get_option('vgt_omega_upgrade_error_id', '');
            $message = __('Security migration is incomplete. Public submission is disabled.', 'vgt-omega-vault');
            if (is_string($errorId) && $errorId !== '') {
                $message .= ' ' . sprintf(__('Error ID: %s', 'vgt-omega-vault'), $errorId);
            }
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }

        $warnings = get_option('vgt_omega_migration_warnings', []);
        if (is_array($warnings) && $warnings !== []) {
            $ids = array_values(array_filter(array_map('absint', $warnings)));
            if ($ids !== []) {
                echo '<div class="notice notice-warning"><p>' .
                    esc_html(
                        sprintf(
                            __('Strict migration requires review of form IDs: %s', 'vgt-omega-vault'),
                            implode(', ', $ids)
                        )
                    ) .
                    '</p></div>';
            }
        }

        $status = isset($_GET['vgt_status']) && is_string($_GET['vgt_status'])
            ? sanitize_key(wp_unslash($_GET['vgt_status']))
            : '';
        if ($status === '') {
            return;
        }

        $errorId = isset($_GET['vgt_error_id']) && is_string($_GET['vgt_error_id'])
            ? sanitize_text_field(wp_unslash($_GET['vgt_error_id']))
            : '';

        if ($status === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__('Operation completed.', 'vgt-omega-vault') .
                '</p></div>';
            return;
        }

        $message = __('Operation failed.', 'vgt-omega-vault');
        if ($errorId !== '') {
            $message .= ' ' . sprintf(__('Error ID: %s', 'vgt-omega-vault'), $errorId);
        }
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private static function renderForms(): void
    {
        $forms = VGT_Omega_DB::get_all_forms();
        $editId = isset($_GET['edit']) && is_scalar($_GET['edit']) ? absint((string) wp_unslash($_GET['edit'])) : 0;
        $editing = $editId > 0 ? VGT_Omega_DB::get_form($editId) : null;

        echo '<div class="vgt-grid">';
        echo '<section class="vgt-card">';
        echo '<h2>' . esc_html__('Configured forms', 'vgt-omega-vault') . '</h2>';

        if ($forms === []) {
            echo '<p>' . esc_html__('No forms exist.', 'vgt-omega-vault') . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>' . esc_html__('ID', 'vgt-omega-vault') . '</th>';
            echo '<th>' . esc_html__('Title', 'vgt-omega-vault') . '</th>';
            echo '<th>' . esc_html__('Type', 'vgt-omega-vault') . '</th>';
            echo '<th>' . esc_html__('Shortcode', 'vgt-omega-vault') . '</th>';
            echo '<th>' . esc_html__('Actions', 'vgt-omega-vault') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($forms as $form) {
                $id = (int) $form->id;
                $editUrl = add_query_arg(
                    ['page' => 'vgt-omega-vault', 'tab' => 'forms', 'edit' => $id],
                    admin_url('admin.php')
                );
                $submissionsUrl = add_query_arg(
                    ['page' => 'vgt-omega-vault', 'tab' => 'submissions', 'form_id' => $id],
                    admin_url('admin.php')
                );

                echo '<tr>';
                echo '<td>' . esc_html((string) $id) . '</td>';
                echo '<td>' . esc_html((string) $form->title) . '</td>';
                echo '<td>' . esc_html((string) $form->type) . '</td>';
                echo '<td><code>[vgt_omega_form id="' . esc_html((string) $id) . '"]</code></td>';
                echo '<td class="vgt-actions">';
                echo '<a class="button button-small" href="' . esc_url($editUrl) . '">' . esc_html__('Edit', 'vgt-omega-vault') . '</a>';
                echo '<a class="button button-small" href="' . esc_url($submissionsUrl) . '">' . esc_html__('Submissions', 'vgt-omega-vault') . '</a>';
                if ($id !== 1) {
                    self::renderDeleteFormButton($id);
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }
        echo '</section>';

        echo '<section class="vgt-card">';
        echo '<h2>' . esc_html($editing ? __('Edit form', 'vgt-omega-vault') : __('Create form', 'vgt-omega-vault')) . '</h2>';

        $config = $editing && is_string($editing->config)
            ? self::prettyJson($editing->config)
            : self::defaultConfigJson();

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vgt_omega_save_form_admin">';
        echo '<input type="hidden" name="form_id" value="' . esc_attr($editing ? (string) $editing->id : '0') . '">';
        wp_nonce_field('vgt_omega_save_form_admin');

        echo '<label for="vgt-title"><strong>' . esc_html__('Title', 'vgt-omega-vault') . '</strong></label>';
        echo '<input id="vgt-title" class="regular-text" type="text" name="title" maxlength="160" required value="' .
            esc_attr($editing ? (string) $editing->title : __('Secure Intake', 'vgt-omega-vault')) . '">';

        echo '<label for="vgt-type"><strong>' . esc_html__('Type', 'vgt-omega-vault') . '</strong></label>';
        echo '<select id="vgt-type" name="type">';
        echo '<option value="form"' . selected($editing ? (string) $editing->type : 'form', 'form', false) . '>' .
            esc_html__('Form', 'vgt-omega-vault') . '</option>';
        echo '<option value="funnel"' . selected($editing ? (string) $editing->type : 'form', 'funnel', false) . '>' .
            esc_html__('Funnel', 'vgt-omega-vault') . '</option>';
        echo '</select>';

        echo '<label for="vgt-config"><strong>' . esc_html__('Validated JSON configuration', 'vgt-omega-vault') . '</strong></label>';
        echo '<textarea id="vgt-config" class="large-text code" name="config" rows="28" spellcheck="false" required>' .
            esc_textarea($config) . '</textarea>';
        echo '<p class="description">' .
            esc_html__('Unknown properties, duplicate field IDs, unsafe media URLs and unsupported MIME types are rejected server-side.', 'vgt-omega-vault') .
            '</p>';

        submit_button($editing ? __('Save form', 'vgt-omega-vault') : __('Create form', 'vgt-omega-vault'));
        echo '</form>';
        echo '</section>';
        echo '</div>';
    }

    private static function renderSubmissions(): void
    {
        $forms = VGT_Omega_DB::get_all_forms();
        if ($forms === []) {
            echo '<div class="vgt-card"><p>' . esc_html__('No forms exist.', 'vgt-omega-vault') . '</p></div>';
            return;
        }

        $formId = isset($_GET['form_id']) && is_scalar($_GET['form_id'])
            ? absint((string) wp_unslash($_GET['form_id']))
            : (int) $forms[0]->id;
        $page = isset($_GET['paged']) && is_scalar($_GET['paged'])
            ? max(1, absint((string) wp_unslash($_GET['paged'])))
            : 1;
        $form = VGT_Omega_DB::get_form($formId);

        echo '<section class="vgt-card">';
        echo '<form method="get" class="vgt-filter">';
        echo '<input type="hidden" name="page" value="vgt-omega-vault">';
        echo '<input type="hidden" name="tab" value="submissions">';
        echo '<label for="vgt-form-filter">' . esc_html__('Form', 'vgt-omega-vault') . '</label>';
        echo '<select id="vgt-form-filter" name="form_id">';
        foreach ($forms as $candidate) {
            echo '<option value="' . esc_attr((string) $candidate->id) . '"' .
                selected($formId, (int) $candidate->id, false) . '>' .
                esc_html((string) $candidate->title) . '</option>';
        }
        echo '</select>';
        submit_button(__('Open', 'vgt-omega-vault'), 'secondary', '', false);
        echo '</form>';
        echo '</section>';

        if ($form === null) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Form not found.', 'vgt-omega-vault') . '</p></div>';
            return;
        }

        $perPage = 20;
        $rows = VGT_Omega_DB::get_paginated_submissions($formId, $page, $perPage);
        $total = VGT_Omega_DB::get_total_submissions_count($formId);

        echo '<section class="vgt-card">';
        echo '<h2>' . esc_html((string) $form->title) . '</h2>';

        if ($rows === []) {
            echo '<p>' . esc_html__('No submissions exist.', 'vgt-omega-vault') . '</p>';
        }

        foreach ($rows as $row) {
            self::renderSubmission($row);
        }

        self::renderPagination($page, (int) ceil($total / $perPage), [
            'page' => 'vgt-omega-vault',
            'tab' => 'submissions',
            'form_id' => $formId,
        ]);
        echo '</section>';
    }

    private static function renderSubmission(object $row): void
    {
        echo '<article class="vgt-submission">';
        $requestLabel = isset($row->request_id) && is_string($row->request_id) && $row->request_id !== ''
            ? $row->request_id
            : '#' . (int) $row->id;
        echo '<header><strong>' . esc_html($requestLabel) . '</strong>';
        echo '<time datetime="' . esc_attr((string) $row->created_at) . '">' .
            esc_html(get_date_from_gmt((string) $row->created_at, 'Y-m-d H:i:s')) . '</time></header>';

        try {
            $ciphertext = (string) $row->payload;
            if (isset($row->payload_hash) && is_string($row->payload_hash) && $row->payload_hash !== '') {
                $expected = VGT_Omega_Crypto::integrityHash($ciphertext, 'submission-payload');
                if (!hash_equals($expected, (string) $row->payload_hash)) {
                    throw new \VGTOmegaVault\SecurityException('Submission integrity validation failed.');
                }
            }

            $plaintext = VGT_Omega_Crypto::decrypt($ciphertext, 'submission_payload', null, null, (int) $row->form_id);
            $payload = json_decode($plaintext, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new \VGTOmegaVault\SecurityException('Submission payload validation failed.');
            }

            echo '<dl class="vgt-payload">';
            foreach ($payload as $key => $value) {
                echo '<div><dt>' . esc_html((string) $key) . '</dt><dd>';
                self::renderPayloadValue($value);
                echo '</dd></div>';
            }
            echo '</dl>';

            $socket = VGT_Omega_Crypto::decrypt((string) $row->ip_socket, 'ip_socket', null, null, (int) $row->form_id);
            $claimed = VGT_Omega_Crypto::decrypt((string) $row->ip_claimed, 'ip_claimed', null, null, (int) $row->form_id);
            echo '<p class="vgt-meta">' .
                esc_html(sprintf(__('Socket IP: %1$s · Claimed IP: %2$s', 'vgt-omega-vault'), $socket, $claimed)) .
                '</p>';
        } catch (\Throwable $e) {
            $errorId = strtoupper(bin2hex(random_bytes(8)));
            error_log('[VGT OMEGA ADMIN][' . $errorId . '] ' . $e->getMessage());
            echo '<p class="vgt-error">' .
                esc_html(sprintf(__('Record authentication failed. Error ID: %s', 'vgt-omega-vault'), $errorId)) .
                '</p>';
        }

        self::renderDeleteSubmissionButton((int) $row->id);
        echo '</article>';
    }

    private static function renderPayloadValue(mixed $value): void
    {
        if (is_array($value) && ($value['type'] ?? null) === 'encrypted_file' && is_string($value['id'] ?? null)) {
            $fileId = $value['id'];
            $url = wp_nonce_url(
                add_query_arg(
                    ['action' => 'vgt_omega_download_file', 'file_id' => $fileId],
                    admin_url('admin-post.php')
                ),
                'vgt_omega_download_' . $fileId
            );
            echo '<a class="button button-small" href="' . esc_url($url) . '">' .
                esc_html((string) ($value['name'] ?? __('Download', 'vgt-omega-vault'))) . '</a>';
            echo ' <code>' . esc_html((string) ($value['sha256'] ?? '')) . '</code>';
            return;
        }

        if (is_array($value)) {
            echo '<pre>' . esc_html(
                wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ) . '</pre>';
            return;
        }

        echo nl2br(esc_html(is_scalar($value) ? (string) $value : ''));
    }

    private static function renderLegacy(): void
    {
        $page = isset($_GET['paged']) && is_scalar($_GET['paged'])
            ? max(1, absint((string) wp_unslash($_GET['paged'])))
            : 1;
        $perPage = 20;
        $rows = VGT_Omega_DB::get_paginated_audits($page, $perPage);
        $total = VGT_Omega_DB::get_total_count();

        echo '<section class="vgt-card">';
        echo '<h2>' . esc_html__('Legacy encrypted records', 'vgt-omega-vault') . '</h2>';

        foreach ($rows as $row) {
            echo '<article class="vgt-submission">';
            echo '<header><strong>#' . esc_html((string) $row->id) . '</strong>';
            echo '<time>' . esc_html((string) $row->created_at) . '</time></header>';
            try {
                $fields = [
                    'domain' => VGT_Omega_Crypto::decrypt((string) $row->domain, 'domain'),
                    'email' => VGT_Omega_Crypto::decrypt((string) $row->email, 'email'),
                    'vector' => VGT_Omega_Crypto::decrypt((string) $row->vector, 'vector'),
                    'threat' => VGT_Omega_Crypto::decrypt((string) $row->threat, 'threat'),
                ];
                echo '<dl class="vgt-payload">';
                foreach ($fields as $key => $value) {
                    echo '<div><dt>' . esc_html($key) . '</dt><dd>' . nl2br(esc_html($value)) . '</dd></div>';
                }
                echo '</dl>';
            } catch (\Throwable $e) {
                $errorId = strtoupper(bin2hex(random_bytes(8)));
                error_log('[VGT OMEGA LEGACY][' . $errorId . '] ' . $e->getMessage());
                echo '<p class="vgt-error">' .
                    esc_html(sprintf(__('Record authentication failed. Error ID: %s', 'vgt-omega-vault'), $errorId)) .
                    '</p>';
            }
            echo '</article>';
        }

        if ($rows === []) {
            echo '<p>' . esc_html__('No legacy records exist.', 'vgt-omega-vault') . '</p>';
        }

        self::renderPagination($page, (int) ceil($total / $perPage), [
            'page' => 'vgt-omega-vault',
            'tab' => 'legacy',
        ]);
        echo '</section>';
    }

    private static function renderSettings(): void
    {
        echo '<section class="vgt-card vgt-settings">';
        echo '<h2>' . esc_html__('Security settings', 'vgt-omega-vault') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vgt_omega_save_settings">';
        wp_nonce_field('vgt_omega_save_settings');

        self::checkbox(
            'enable_honeypot',
            VGT_Omega_Config::honeypotEnabled(),
            __('Enable honeypot detection', 'vgt-omega-vault')
        );
        self::checkbox(
            'enable_notifications',
            VGT_Omega_Config::notificationsEnabled(),
            __('Send metadata-only email notifications', 'vgt-omega-vault')
        );
        self::checkbox(
            'enable_file_uploads',
            VGT_Omega_Config::uploadsEnabled(),
            __('Enable encrypted file uploads', 'vgt-omega-vault')
        );
        self::checkbox(
            'allow_proxies',
            VGT_Omega_Config::proxiesEnabled(),
            __('Trust explicitly configured reverse proxy CIDRs', 'vgt-omega-vault')
        );

        echo '<label for="vgt-retention"><strong>' . esc_html__('Retention days', 'vgt-omega-vault') . '</strong></label>';
        echo '<input id="vgt-retention" type="number" min="1" max="3650" name="retention_days" value="' .
            esc_attr((string) VGT_Omega_Config::retentionDays()) . '" required>';

        echo '<p class="description">' .
            esc_html__('File uploads remain restricted to each form field schema and are encrypted outside the web root.', 'vgt-omega-vault') .
            '</p>';

        submit_button(__('Save settings', 'vgt-omega-vault'));
        echo '</form>';

        echo '<h3>' . esc_html__('Runtime boundaries', 'vgt-omega-vault') . '</h3>';
        echo '<dl class="vgt-runtime">';
        echo '<div><dt>' . esc_html__('Storage root', 'vgt-omega-vault') . '</dt><dd><code>' .
            esc_html(VGT_Omega_Config::storageRoot()) . '</code></dd></div>';
        echo '<div><dt>' . esc_html__('Maximum request', 'vgt-omega-vault') . '</dt><dd>' .
            esc_html(size_format(VGT_Omega_Config::MAX_REQUEST_BYTES)) . '</dd></div>';
        echo '<div><dt>' . esc_html__('Maximum file', 'vgt-omega-vault') . '</dt><dd>' .
            esc_html(size_format(VGT_Omega_Config::MAX_FILE_BYTES)) . '</dd></div>';
        echo '</dl>';
        echo '</section>';
    }

    private static function renderDeleteFormButton(int $id): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vgt_omega_delete_form_admin">';
        echo '<input type="hidden" name="form_id" value="' . esc_attr((string) $id) . '">';
        wp_nonce_field('vgt_omega_delete_form_admin');
        echo '<button type="submit" class="button button-small button-link-delete">' .
            esc_html__('Delete', 'vgt-omega-vault') . '</button>';
        echo '</form>';
    }

    private static function renderDeleteSubmissionButton(int $id): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vgt_omega_delete_submission_admin">';
        echo '<input type="hidden" name="submission_id" value="' . esc_attr((string) $id) . '">';
        wp_nonce_field('vgt_omega_delete_submission_admin');
        echo '<button type="submit" class="button button-small button-link-delete">' .
            esc_html__('Delete permanently', 'vgt-omega-vault') . '</button>';
        echo '</form>';
    }

    /**
     * @param array<string,int|string> $baseArgs
     */
    private static function renderPagination(int $current, int $pages, array $baseArgs): void
    {
        if ($pages <= 1) {
            return;
        }

        $current = max(1, min($pages, $current));
        $start = max(1, $current - 3);
        $end = min($pages, $current + 3);
        if (($end - $start) < 6) {
            $start = max(1, $end - 6);
            $end = min($pages, $start + 6);
        }

        echo '<nav class="tablenav-pages" aria-label="' . esc_attr__('Pagination', 'vgt-omega-vault') . '">';
        $visible = array_values(array_unique(array_merge([1], range($start, $end), [$pages])));
        $previous = 0;
        foreach ($visible as $page) {
            if ($previous > 0 && $page > ($previous + 1)) {
                echo '<span class="vgt-pagination-gap" aria-hidden="true">…</span>';
            }
            $url = add_query_arg(array_merge($baseArgs, ['paged' => $page]), admin_url('admin.php'));
            $class = $page === $current ? 'button button-primary' : 'button';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' .
                esc_html((string) $page) . '</a>';
            $previous = $page;
        }
        echo '</nav>';
    }

    private static function checkbox(string $name, bool $checked, string $label): void
    {
        echo '<label class="vgt-checkbox"><input type="checkbox" name="' . esc_attr($name) . '" value="1"' .
            checked($checked, true, false) . '> <span>' . esc_html($label) . '</span></label>';
    }

    private static function prettyJson(string $raw): string
    {
        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            return wp_json_encode(
                $decoded,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    private static function defaultConfigJson(): string
    {
        return wp_json_encode([
            'title' => 'Secure Intake',
            'type' => 'form',
            'fields' => [
                [
                    'id' => 'email',
                    'type' => 'email',
                    'label' => 'Email',
                    'placeholder' => 'name@example.org',
                    'required' => true,
                    'options' => [],
                    'media_url' => '',
                    'max_length' => 320,
                ],
                [
                    'id' => 'message',
                    'type' => 'textarea',
                    'label' => 'Message',
                    'placeholder' => 'Your message',
                    'required' => true,
                    'options' => [],
                    'media_url' => '',
                    'max_length' => 10000,
                ],
            ],
            'settings' => [
                'theme' => 'dark',
                'button_text' => 'Submit securely',
                'subtitle' => 'TLS-protected transmission with encrypted storage',
                'consent_required' => true,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
