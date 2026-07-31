<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGT_Omega_API
{
    /** @var list<string> */
    private const CONTROL_FIELDS = [
        'action', 'form_id', 'vgt_nonce', 'vgt_request_token', 'vgt_consent',
        'vgt_full_name', 'security', '_wpnonce', '_wp_http_referer',
    ];

    public static function handle_issue_token(): void
    {
        self::respond(static function(): array {
            $data = self::requestData(false);
            self::assertSameOriginRequest();

            $formId = self::positiveInt($data['form_id'] ?? null, 'form_id');
            if (VGT_Omega_DB::get_form($formId) === null) {
                throw new \VGTOmegaVault\ValidationException(__('Form not found.', 'vgt-omega-vault'), 404);
            }

            $profile = self::get_ip_profile();
            $clientIp = self::clientIdentity($profile);
            VGT_Omega_Rate_Limiter::enforce(
                'issue-token|' . $formId,
                $clientIp,
                VGT_Omega_Config::RATE_MAX_TOKENS,
                VGT_Omega_Config::RATE_WINDOW_SECONDS
            );

            $token = bin2hex(random_bytes(32));
            $tokenHash = VGT_Omega_Crypto::integrityHash($token, 'request-token');
            $ipHash = VGT_Omega_Crypto::integrityHash($clientIp, 'request-ip');
            VGT_Omega_DB::createOneTimeToken(
                $tokenHash,
                $formId,
                $ipHash,
                VGT_Omega_Config::TOKEN_TTL_SECONDS
            );

            return [
                'token' => $token,
                'nonce' => wp_create_nonce('vgt_omega_submit_' . $formId),
                'expires_in' => VGT_Omega_Config::TOKEN_TTL_SECONDS,
            ];
        });
    }

    public static function handle_request(): void
    {
        self::respond(static function(): array {
            $data = self::requestData(true);
            return self::processSubmission(1, $data);
        });
    }

    public static function handle_submit_builder_form(): void
    {
        self::respond(static function(): array {
            $data = self::requestData(true);
            $formId = self::positiveInt($data['form_id'] ?? null, 'form_id');
            return self::processSubmission($formId, $data);
        });
    }

    public static function handle_save_form(): void
    {
        self::respond(static function(): array {
            $data = self::requestData(false);
            self::assertAdmin($data);

            $formId = isset($data['form_id']) && $data['form_id'] !== ''
                ? self::positiveInt($data['form_id'], 'form_id')
                : 0;
            $title = self::scalarString($data['title'] ?? '', 'title', 160);
            $type = isset($data['type']) && $data['type'] === 'funnel' ? 'funnel' : 'form';
            $rawConfig = self::scalarString($data['config'] ?? '', 'config', VGT_Omega_Config::MAX_CONFIG_BYTES);

            $config = VGT_Omega_Schema::decodeAndSanitize($rawConfig, $formId);
            $config['title'] = $title;
            $config['type'] = $type;
            $encoded = wp_json_encode(
                $config,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            if ($formId > 0) {
                if (!VGT_Omega_DB::update_form($formId, ['title' => $title, 'type' => $type, 'config' => $encoded])) {
                    throw new \VGTOmegaVault\StorageException('Form update failed.');
                }
                VGT_Omega_DB::clearMigrationWarning($formId);
                return ['form_id' => $formId];
            }

            $newId = VGT_Omega_DB::insert_form(['title' => $title, 'type' => $type, 'config' => $encoded]);
            if ($newId <= 0) {
                throw new \VGTOmegaVault\StorageException('Form creation failed.');
            }

            return ['form_id' => $newId];
        });
    }

    public static function handle_delete_form(): void
    {
        self::respond(static function(): array {
            $data = self::requestData(false);
            self::assertAdmin($data);
            $formId = self::positiveInt($data['form_id'] ?? null, 'form_id');
            self::deleteFormAndFiles($formId);
            return ['deleted' => true];
        });
    }

    public static function handle_get_submissions(): void
    {
        self::respond(static function(): array {
            $data = self::requestData(false);
            self::assertAdmin($data);

            $formId = self::positiveInt($data['form_id'] ?? null, 'form_id');
            $page = isset($data['page']) ? max(1, self::positiveInt($data['page'], 'page')) : 1;
            $perPage = 25;
            $rows = VGT_Omega_DB::get_paginated_submissions($formId, $page, $perPage);
            $result = [];

            foreach ($rows as $row) {
                $result[] = self::decryptSubmission($row);
            }

            $total = VGT_Omega_DB::get_total_submissions_count($formId);
            return [
                'submissions' => $result,
                'total' => $total,
                'pages' => (int) ceil($total / $perPage),
                'current' => $page,
            ];
        });
    }

    public static function handle_delete_submission(): void
    {
        self::respond(static function(): array {
            $data = self::requestData(false);
            self::assertAdmin($data);
            $submissionId = self::positiveInt($data['submission_id'] ?? null, 'submission_id');
            self::deleteSubmissionAndFiles($submissionId);
            return ['deleted' => true];
        });
    }

    public static function handle_download_file(): void
    {
        VGT_Omega_Config::assertReady();

        if (!current_user_can(VGT_Omega_Config::CAPABILITY)) {
            wp_die(esc_html__('Forbidden.', 'vgt-omega-vault'), '', ['response' => 403]);
        }

        $fileId = isset($_GET['file_id']) && is_string($_GET['file_id'])
            ? sanitize_text_field(wp_unslash($_GET['file_id']))
            : '';
        $nonce = isset($_GET['_wpnonce']) && is_string($_GET['_wpnonce'])
            ? sanitize_text_field(wp_unslash($_GET['_wpnonce']))
            : '';

        if (!wp_verify_nonce($nonce, 'vgt_omega_download_' . $fileId)) {
            wp_die(esc_html__('Forbidden.', 'vgt-omega-vault'), '', ['response' => 403]);
        }

        $response = \VGTOmegaVault\CoreEngine::execute(static fn(): array => VGT_Omega_File_Vault::read($fileId));
        if ($response['status'] !== 'success' || !isset($response['data']) || !is_array($response['data'])) {
            wp_die(esc_html__('File access failed.', 'vgt-omega-vault'), '', [
                'response' => \VGTOmegaVault\CoreEngine::lastHttpStatus(),
            ]);
        }

        $file = $response['data'];
        nocache_headers();
        header('Content-Type: ' . self::safeDownloadMime((string) $file['mime']));
        header('Content-Length: ' . strlen((string) $file['data']));
        header('Content-Disposition: attachment; filename="' . self::asciiFilename((string) $file['name']) . '"; filename*=UTF-8\'\'' . rawurlencode((string) $file['name']));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        echo $file['data'];
        exit;
    }

    public static function assertSameOriginRequest(): void
    {
        $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
            ? strtoupper($_SERVER['REQUEST_METHOD'])
            : '';
        if ($method !== 'POST') {
            throw new \VGTOmegaVault\SecurityException('Invalid request method.');
        }

        $fetchSite = isset($_SERVER['HTTP_SEC_FETCH_SITE']) && is_string($_SERVER['HTTP_SEC_FETCH_SITE'])
            ? strtolower(trim($_SERVER['HTTP_SEC_FETCH_SITE']))
            : '';
        if ($fetchSite === 'cross-site') {
            throw new \VGTOmegaVault\SecurityException('Cross-site request origin rejected.');
        }

        $source = '';
        if (isset($_SERVER['HTTP_ORIGIN']) && is_string($_SERVER['HTTP_ORIGIN'])) {
            $source = $_SERVER['HTTP_ORIGIN'];
        } elseif (isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER'])) {
            $source = $_SERVER['HTTP_REFERER'];
        }

        if ($source === '') {
            $allowHeaderless = defined('VGT_OMEGA_ALLOW_HEADERLESS_SUBMISSIONS')
                && VGT_OMEGA_ALLOW_HEADERLESS_SUBMISSIONS === true;
            if (!$allowHeaderless) {
                throw new \VGTOmegaVault\SecurityException('Request origin header missing.');
            }
            return;
        }

        $sourceOrigin = self::normalizeOrigin($source);
        $homeOrigin = self::normalizeOrigin(home_url('/'));
        if (!hash_equals($homeOrigin, $sourceOrigin)) {
            throw new \VGTOmegaVault\SecurityException('Request origin validation failed.');
        }
    }

    public static function get_ip_profile(?array $server_mock = null): stdClass
    {
        $server = $server_mock ?? $_SERVER;
        $profile = new stdClass();
        $socket = isset($server['REMOTE_ADDR']) && is_string($server['REMOTE_ADDR'])
            ? $server['REMOTE_ADDR']
            : '';

        if (filter_var($socket, FILTER_VALIDATE_IP) === false) {
            throw new \VGTOmegaVault\SecurityException('Socket IP validation failed.');
        }

        $profile->socket = $socket;
        $profile->claimed = 'none';

        if (!VGT_Omega_Config::proxiesEnabled()) {
            return $profile;
        }

        $trustedCidrs = VGT_Omega_Config::trustedProxyCidrs();
        if ($trustedCidrs === [] || !self::ipInCidrs($socket, $trustedCidrs)) {
            throw new \VGTOmegaVault\SecurityException('Proxy trust validation failed.');
        }

        $forwarded = isset($server['HTTP_X_FORWARDED_FOR']) && is_string($server['HTTP_X_FORWARDED_FOR'])
            ? $server['HTTP_X_FORWARDED_FOR']
            : '';
        if ($forwarded === '') {
            return $profile;
        }

        $chain = array_map('trim', explode(',', $forwarded));
        $chain[] = $socket;

        for ($index = count($chain) - 1; $index >= 0; --$index) {
            $candidate = $chain[$index];
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                throw new \VGTOmegaVault\SecurityException('Proxy IP chain validation failed.');
            }
            if (!self::ipInCidrs($candidate, $trustedCidrs)) {
                $profile->claimed = $candidate;
                break;
            }
        }

        return $profile;
    }

    public static function deleteSubmissionAndFiles(int $submissionId): void
    {
        $row = VGT_Omega_DB::get_submission($submissionId);
        if ($row === null) {
            throw new \VGTOmegaVault\ValidationException(__('Submission not found.', 'vgt-omega-vault'), 404);
        }

        $payload = self::decryptPayloadFromRow($row);
        $fileIds = VGT_Omega_File_Vault::collectFileIds($payload);

        VGT_Omega_DB::begin();
        try {
            if (!VGT_Omega_DB::delete_submission($submissionId)) {
                throw new \VGTOmegaVault\StorageException('Submission deletion failed.');
            }
            VGT_Omega_DB::commit();
        } catch (\Throwable $e) {
            VGT_Omega_DB::rollback();
            throw $e;
        }

        foreach ($fileIds as $fileId) {
            VGT_Omega_File_Vault::delete($fileId);
        }
    }

    public static function deleteFormAndFiles(int $formId): void
    {
        if ($formId === 1) {
            throw new \VGTOmegaVault\ValidationException(
                __('The system default form cannot be deleted.', 'vgt-omega-vault'),
                409
            );
        }
        if (VGT_Omega_DB::get_form($formId) === null) {
            throw new \VGTOmegaVault\ValidationException(__('Form not found.', 'vgt-omega-vault'), 404);
        }

        VGT_Omega_DB::markFormDeleting($formId);

        do {
            $rows = VGT_Omega_DB::getSubmissionBatchForForm($formId, 100);
            if ($rows === []) {
                break;
            }

            $rowIds = [];
            foreach ($rows as $row) {
                $payload = self::decryptPayloadFromRow($row);
                foreach (VGT_Omega_File_Vault::collectFileIds($payload) as $fileId) {
                    VGT_Omega_File_Vault::delete($fileId);
                }
                $rowIds[] = (int) $row->id;
            }

            VGT_Omega_DB::begin();
            try {
                foreach ($rowIds as $rowId) {
                    if (!VGT_Omega_DB::delete_submission($rowId)) {
                        throw new \VGTOmegaVault\StorageException('Form submission purge failed.');
                    }
                }
                VGT_Omega_DB::commit();
            } catch (\Throwable $e) {
                VGT_Omega_DB::rollback();
                throw $e;
            }
        } while (true);

        if (!VGT_Omega_DB::delete_form($formId)) {
            throw new \VGTOmegaVault\StorageException('Form deletion failed.');
        }
    }

    /**
     * @param array<string,mixed> $postData
     * @return array{message:string,request_id:string}
     */
    private static function processSubmission(int $formId, array $postData): array
    {
        self::assertSameOriginRequest();

        $form = VGT_Omega_DB::get_form($formId);
        if ($form === null || !is_string($form->config)) {
            throw new \VGTOmegaVault\ValidationException(__('Form not found.', 'vgt-omega-vault'), 404);
        }
        if (!isset($form->status) || !is_string($form->status) || !hash_equals('active', $form->status)) {
            throw new \VGTOmegaVault\ValidationException(__('Form is unavailable.', 'vgt-omega-vault'), 409);
        }

        $config = VGT_Omega_Schema::decodeAndSanitize($form->config, $formId);
        $fields = VGT_Omega_Schema::inputFieldsById($config);
        $ipProfile = self::get_ip_profile();

        $clientIp = self::clientIdentity($ipProfile);
        VGT_Omega_Rate_Limiter::enforce(
            'submit|' . $formId,
            $clientIp,
            VGT_Omega_Config::RATE_MAX_SUBMISSIONS,
            VGT_Omega_Config::RATE_WINDOW_SECONDS
        );

        self::verifySubmissionTokens($postData, $formId, $clientIp);

        if (VGT_Omega_Config::honeypotEnabled()
            && isset($postData['vgt_full_name'])
            && is_scalar($postData['vgt_full_name'])
            && trim((string) $postData['vgt_full_name']) !== ''
        ) {
            throw new \VGTOmegaVault\SecurityException('Honeypot anomaly detected.');
        }

        if (!empty($config['settings']['consent_required'])
            && (!isset($postData['vgt_consent']) || !in_array((string) $postData['vgt_consent'], ['1', 'on', 'yes'], true))
        ) {
            throw new \VGTOmegaVault\ValidationException(
                __('Consent to encrypted storage is required.', 'vgt-omega-vault'),
                422
            );
        }

        self::rejectUnknownInputFields($postData, $fields);
        $payload = [];
        $storedFileIds = [];
        $sanitizedTempPaths = [];
        $submissionCommitted = false;

        try {
            self::processFiles($fields, $formId, $payload, $storedFileIds, $sanitizedTempPaths);

            foreach ($fields as $fieldId => $field) {
                if ($field['type'] === 'file') {
                    if (!empty($field['required']) && !isset($payload[$fieldId])) {
                        throw new \VGTOmegaVault\ValidationException(
                            sprintf(__('The field "%s" is required.', 'vgt-omega-vault'), $field['label']),
                            422
                        );
                    }
                    continue;
                }

                $raw = $postData[$fieldId] ?? null;
                if (is_array($raw) || is_object($raw)) {
                    throw new \VGTOmegaVault\SecurityException('Nested input validation failed.');
                }
                $value = $raw === null ? '' : (string) $raw;

                if (!empty($field['required']) && trim($value) === '') {
                    throw new \VGTOmegaVault\ValidationException(
                        sprintf(__('The field "%s" is required.', 'vgt-omega-vault'), $field['label']),
                        422
                    );
                }

                if ($value === '') {
                    $payload[$fieldId] = '';
                    continue;
                }

                $payload[$fieldId] = self::validateFieldValue($value, $field);
            }

            $requestId = wp_generate_uuid4();
            $payloadJson = wp_json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            $encryptedPayload = VGT_Omega_Crypto::encrypt($payloadJson, 'submission_payload', $formId);
            $encryptedSocket = VGT_Omega_Crypto::encrypt($ipProfile->socket, 'ip_socket', $formId);
            $encryptedClaimed = VGT_Omega_Crypto::encrypt($ipProfile->claimed, 'ip_claimed', $formId);

            VGT_Omega_DB::begin();
            try {
                VGT_Omega_DB::assertFormActiveForUpdate($formId);
                $inserted = VGT_Omega_DB::insert_submission([
                    'form_id' => $formId,
                    'request_id' => $requestId,
                    'payload' => $encryptedPayload,
                    'payload_hash' => VGT_Omega_Crypto::integrityHash($encryptedPayload, 'submission-payload'),
                    'ip_socket' => $encryptedSocket,
                    'ip_claimed' => $encryptedClaimed,
                ]);

                if (!$inserted) {
                    throw new \VGTOmegaVault\StorageException('Submission write failed.');
                }
                VGT_Omega_DB::commit();
                $submissionCommitted = true;
            } catch (\Throwable $e) {
                VGT_Omega_DB::rollback();
                throw $e;
            }

            try {
                self::dispatchNotification($formId, $requestId);
            } catch (\Throwable $notificationError) {
                error_log('[VGT OMEGA NOTIFICATION] ' . $notificationError->getMessage());
            }

            return [
                'message' => __('Submission stored securely.', 'vgt-omega-vault'),
                'request_id' => $requestId,
            ];
        } catch (\Throwable $e) {
            if (!$submissionCommitted) {
                foreach ($storedFileIds as $fileId) {
                    try {
                        VGT_Omega_File_Vault::delete($fileId);
                    } catch (\Throwable $cleanupError) {
                        error_log('[VGT OMEGA CLEANUP] ' . $cleanupError->getMessage());
                    }
                }
            }
            throw $e;
        } finally {
            foreach ($sanitizedTempPaths as $tempPath) {
                if (is_file($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }
    }

    /**
     * @param array<string,array<string,mixed>> $fields
     * @param array<string,mixed> $payload
     * @param list<string> $storedFileIds
     * @param list<string> $sanitizedTempPaths
     */
    private static function processFiles(
        array $fields,
        int $formId,
        array &$payload,
        array &$storedFileIds,
        array &$sanitizedTempPaths
    ): void {
        $fileFields = array_filter(
            $fields,
            static fn(array $field): bool => $field['type'] === 'file'
        );

        if ($_FILES === []) {
            return;
        }

        if ($fileFields === []) {
            throw new \VGTOmegaVault\SecurityException('Unauthorized upload field rejected.');
        }

        foreach ($_FILES as $fieldName => $fileArray) {
            if (!is_string($fieldName) || !isset($fileFields[$fieldName])) {
                throw new \VGTOmegaVault\SecurityException('Unauthorized upload field rejected.');
            }
        }

        if (!VGT_Omega_Config::uploadsEnabled()) {
            throw new \VGTOmegaVault\ValidationException(__('File uploads are disabled.', 'vgt-omega-vault'), 403);
        }

        foreach ($_FILES as $fieldName => $fileArray) {
            if (!is_array($fileArray)) {
                throw new \VGTOmegaVault\SecurityException('Unauthorized upload field rejected.');
            }

            if (is_array($fileArray['name'] ?? null)
                || is_array($fileArray['tmp_name'] ?? null)
                || is_array($fileArray['error'] ?? null)
            ) {
                throw new \VGTOmegaVault\SecurityException('Multiple upload field validation failed.');
            }

            if (($fileArray['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $prefiltered = apply_filters('wp_handle_upload_prefilter', $fileArray);
            if (!is_array($prefiltered)
                || !isset($prefiltered['tmp_name'])
                || !is_string($prefiltered['tmp_name'])
                || !hash_equals((string) ($fileArray['tmp_name'] ?? ''), $prefiltered['tmp_name'])
            ) {
                throw new \VGTOmegaVault\SecurityException('External upload prefilter integrity validation failed.');
            }
            $prefilterError = $prefiltered['error'] ?? '';
            if (is_array($prefilterError) || is_object($prefilterError)) {
                throw new \VGTOmegaVault\SecurityException('External upload prefilter error validation failed.');
            }
            $prefilterError = trim((string) $prefilterError);
            if ($prefilterError !== '' && $prefilterError !== '0') {
                throw new \VGTOmegaVault\SecurityException(
                    'External upload security gate rejected field ' . $fieldName . ': ' . $prefilterError
                );
            }

            do_action('vgt_omega_upload_authorized', $fieldName, $formId, $fileFields[$fieldName]);
            $validated = VGT_Omega_Scanner::scan_and_sanitize($fileArray, $fileFields[$fieldName]);
            if ($validated['path'] !== $validated['original_tmp']) {
                $sanitizedTempPaths[] = $validated['path'];
            }

            $stored = VGT_Omega_File_Vault::store($validated, $formId, $fieldName);
            $payload[$fieldName] = $stored;
            $storedFileIds[] = $stored['id'];
        }
    }

    /**
     * @param array<string,mixed> $field
     */
    private static function validateFieldValue(string $value, array $field): string
    {
        if (!mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            throw new \VGTOmegaVault\SecurityException('Text encoding validation failed.');
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $maxLength = (int) ($field['max_length'] ?? 2048);
        if (mb_strlen($value, 'UTF-8') > $maxLength || strlen($value) > VGT_Omega_Config::MAX_TEXT_BYTES * 4) {
            throw new \VGTOmegaVault\ValidationException(
                sprintf(__('The field "%s" is too long.', 'vgt-omega-vault'), $field['label']),
                422
            );
        }

        $type = $field['type'];
        if ($type === 'email') {
            $email = sanitize_email(trim($value));
            if ($email === '' || !is_email($email) || strlen($email) > 320) {
                throw new \VGTOmegaVault\ValidationException(__('Invalid email address.', 'vgt-omega-vault'), 422);
            }
            return $email;
        }

        if ($type === 'number') {
            if (preg_match('/^-?(?:\d+|\d*\.\d+)$/D', trim($value)) !== 1) {
                throw new \VGTOmegaVault\ValidationException(__('Invalid number.', 'vgt-omega-vault'), 422);
            }
            $number = (float) $value;
            if (is_nan($number) || is_infinite($number)) {
                throw new \VGTOmegaVault\ValidationException(__('Invalid number.', 'vgt-omega-vault'), 422);
            }
            if ($field['min'] !== null && $number < (float) $field['min']) {
                throw new \VGTOmegaVault\ValidationException(__('Number is below the minimum.', 'vgt-omega-vault'), 422);
            }
            if ($field['max'] !== null && $number > (float) $field['max']) {
                throw new \VGTOmegaVault\ValidationException(__('Number exceeds the maximum.', 'vgt-omega-vault'), 422);
            }
            return trim($value);
        }

        if (in_array($type, ['select', 'radio'], true)) {
            if (!in_array($value, $field['options'], true)) {
                throw new \VGTOmegaVault\SecurityException('Submitted option validation failed.');
            }
            return $value;
        }

        if ($type === 'text') {
            return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');
        }

        if ($type === 'textarea') {
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        }

        throw new \VGTOmegaVault\SecurityException('Field type validation failed.');
    }

    /**
     * @param array<string,mixed> $postData
     * @param array<string,array<string,mixed>> $fields
     */
    private static function rejectUnknownInputFields(array $postData, array $fields): void
    {
        $allowed = array_fill_keys(array_merge(array_keys($fields), self::CONTROL_FIELDS), true);

        foreach (array_keys($postData) as $key) {
            if (!is_string($key) || !isset($allowed[$key])) {
                throw new \VGTOmegaVault\SecurityException('Unknown submission field rejected.');
            }
        }
    }

    /**
     * @param array<string,mixed> $postData
     */
    private static function verifySubmissionTokens(array $postData, int $formId, string $socketIp): void
    {
        $nonce = isset($postData['vgt_nonce']) && is_string($postData['vgt_nonce'])
            ? $postData['vgt_nonce']
            : '';
        if (!wp_verify_nonce($nonce, 'vgt_omega_submit_' . $formId)) {
            throw new \VGTOmegaVault\SecurityException('CSRF nonce validation failed.');
        }

        $token = isset($postData['vgt_request_token']) && is_string($postData['vgt_request_token'])
            ? strtolower(trim($postData['vgt_request_token']))
            : '';
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            throw new \VGTOmegaVault\SecurityException('One-time token validation failed.');
        }

        $tokenHash = VGT_Omega_Crypto::integrityHash($token, 'request-token');
        $ipHash = VGT_Omega_Crypto::integrityHash($socketIp, 'request-ip');
        if (!VGT_Omega_DB::consumeOneTimeToken($tokenHash, $formId, $ipHash)) {
            throw new \VGTOmegaVault\SecurityException('One-time token validation failed.');
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function assertAdmin(array $data): void
    {
        self::assertSameOriginRequest();
        if (!current_user_can(VGT_Omega_Config::CAPABILITY)) {
            throw new \VGTOmegaVault\SecurityException('Administrative authorization failed.');
        }

        $nonce = isset($data['security']) && is_string($data['security']) ? $data['security'] : '';
        if (!wp_verify_nonce($nonce, 'vgt_omega_admin')) {
            throw new \VGTOmegaVault\SecurityException('Administrative CSRF token validation failed.');
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function requestData(bool $allowMultipart): array
    {
        VGT_Omega_Config::assertReady();

        $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
            ? strtoupper($_SERVER['REQUEST_METHOD'])
            : '';
        if ($method !== 'POST') {
            throw new \VGTOmegaVault\SecurityException('Invalid request method.');
        }

        $contentLength = isset($_SERVER['CONTENT_LENGTH']) && is_numeric($_SERVER['CONTENT_LENGTH'])
            ? (int) $_SERVER['CONTENT_LENGTH']
            : 0;
        if ($contentLength < 0 || $contentLength > VGT_Omega_Config::MAX_REQUEST_BYTES) {
            throw new \VGTOmegaVault\ValidationException(__('Request body is too large.', 'vgt-omega-vault'), 413);
        }

        $contentType = isset($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])
            ? strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'], 2)[0]))
            : '';

        $allowed = ['application/x-www-form-urlencoded'];
        if ($allowMultipart) {
            $allowed[] = 'multipart/form-data';
        }
        if (!in_array($contentType, $allowed, true)) {
            throw new \VGTOmegaVault\SecurityException('Content-Type validation failed.');
        }

        $data = wp_unslash($_POST);
        if (!is_array($data)) {
            throw new \VGTOmegaVault\SecurityException('Request payload validation failed.');
        }
        if (count($data) > (VGT_Omega_Config::MAX_FIELDS + count(self::CONTROL_FIELDS))) {
            throw new \VGTOmegaVault\SecurityException('Request field-count validation failed.');
        }
        self::assertActualRequestBudget($data);
        return $data;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function assertActualRequestBudget(array $data): void
    {
        $bytes = 0;
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new \VGTOmegaVault\SecurityException('Request key validation failed.');
            }
            $bytes += strlen($key);
            if (is_scalar($value)) {
                $bytes += strlen((string) $value);
            } elseif ($value !== null) {
                throw new \VGTOmegaVault\SecurityException('Nested request value validation failed.');
            }
            if ($bytes > VGT_Omega_Config::MAX_REQUEST_BYTES) {
                throw new \VGTOmegaVault\ValidationException(__('Request body is too large.', 'vgt-omega-vault'), 413);
            }
        }

        foreach ($_FILES as $fileArray) {
            if (!is_array($fileArray) || is_array($fileArray['tmp_name'] ?? null)) {
                continue;
            }
            $tmp = $fileArray['tmp_name'] ?? '';
            if (is_string($tmp) && $tmp !== '' && is_file($tmp)) {
                $size = filesize($tmp);
                if ($size === false) {
                    throw new \VGTOmegaVault\StorageException('Upload size read failed.');
                }
                $bytes += $size;
                if ($bytes > VGT_Omega_Config::MAX_REQUEST_BYTES) {
                    throw new \VGTOmegaVault\ValidationException(__('Request body is too large.', 'vgt-omega-vault'), 413);
                }
            }
        }
    }

    private static function clientIdentity(stdClass $profile): string
    {
        $claimed = isset($profile->claimed) && is_string($profile->claimed) ? $profile->claimed : 'none';
        $socket = isset($profile->socket) && is_string($profile->socket) ? $profile->socket : '';
        $candidate = $claimed !== 'none' ? $claimed : $socket;

        if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
            throw new \VGTOmegaVault\SecurityException('Client IP validation failed.');
        }
        return $candidate;
    }

    /**
     * @return array<string,mixed>
     */
    private static function decryptSubmission(object $row): array
    {
        $payload = self::decryptPayloadFromRow($row);
        return [
            'id' => (int) $row->id,
            'form_id' => (int) $row->form_id,
            'request_id' => (string) $row->request_id,
            'payload' => $payload,
            'ip_socket' => VGT_Omega_Crypto::decrypt((string) $row->ip_socket, 'ip_socket', null, null, (int) $row->form_id),
            'ip_claimed' => VGT_Omega_Crypto::decrypt((string) $row->ip_claimed, 'ip_claimed', null, null, (int) $row->form_id),
            'created_at' => (string) $row->created_at,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function decryptPayloadFromRow(object $row): array
    {
        $ciphertext = (string) $row->payload;
        if (isset($row->payload_hash) && is_string($row->payload_hash) && $row->payload_hash !== '') {
            $expected = VGT_Omega_Crypto::integrityHash($ciphertext, 'submission-payload');
            if (!hash_equals($expected, $row->payload_hash)) {
                throw new \VGTOmegaVault\SecurityException('Submission integrity validation failed.');
            }
        }

        $plaintext = VGT_Omega_Crypto::decrypt(
            $ciphertext,
            'submission_payload',
            null,
            null,
            (int) $row->form_id
        );

        try {
            $payload = json_decode($plaintext, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \VGTOmegaVault\SecurityException('Submission payload validation failed.');
        }

        if (!is_array($payload)) {
            throw new \VGTOmegaVault\SecurityException('Submission payload validation failed.');
        }
        return $payload;
    }

    private static function dispatchNotification(int $formId, string $requestId): void
    {
        if (!VGT_Omega_Config::notificationsEnabled()) {
            return;
        }

        $recipient = get_option('admin_email');
        if (!is_string($recipient) || !is_email($recipient)) {
            return;
        }

        wp_mail(
            $recipient,
            sprintf('[VGT Omega] New encrypted submission for form %d', $formId),
            "A new encrypted submission is available.\nRequest ID: " . $requestId . "\nNo submission content is included in this email.",
            ['Content-Type: text/plain; charset=UTF-8']
        );
    }

    /**
     * @param callable():mixed $operation
     */
    private static function respond(callable $operation): void
    {
        nocache_headers();
        $response = \VGTOmegaVault\CoreEngine::execute($operation);
        $status = \VGTOmegaVault\CoreEngine::lastHttpStatus();

        if ($response['status'] === 'success') {
            wp_send_json_success($response['data'], $status);
        }

        $data = ['message' => $response['message']];
        if (isset($response['error_id'])) {
            $data['error_id'] = $response['error_id'];
        }
        wp_send_json_error($data, $status);
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        if (is_array($value) || is_object($value) || !is_numeric($value)) {
            throw new \VGTOmegaVault\ValidationException(
                sprintf(__('Invalid %s.', 'vgt-omega-vault'), $field),
                422
            );
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \VGTOmegaVault\ValidationException(
                sprintf(__('Invalid %s.', 'vgt-omega-vault'), $field),
                422
            );
        }
        return $int;
    }

    private static function scalarString(mixed $value, string $field, int $maxBytes): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > $maxBytes || !mb_check_encoding($value, 'UTF-8')) {
            throw new \VGTOmegaVault\ValidationException(
                sprintf(__('Invalid %s.', 'vgt-omega-vault'), $field),
                422
            );
        }
        return $value;
    }

    private static function normalizeOrigin(string $url): string
    {
        $parts = wp_parse_url($url);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !is_string($parts['scheme'])
            || !is_string($parts['host'])
        ) {
            throw new \VGTOmegaVault\SecurityException('Request origin validation failed.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (!in_array($scheme, ['https', 'http'], true) || $port < 1 || $port > 65535) {
            throw new \VGTOmegaVault\SecurityException('Request origin validation failed.');
        }

        return $scheme . '://' . $host . ':' . $port;
    }

    /**
     * @param list<string> $cidrs
     */
    private static function ipInCidrs(string $ip, array $cidrs): bool
    {
        $packedIp = inet_pton($ip);
        if ($packedIp === false) {
            return false;
        }

        foreach ($cidrs as $cidr) {
            [$network, $prefixRaw] = explode('/', $cidr, 2);
            $packedNetwork = inet_pton($network);
            if ($packedNetwork === false || strlen($packedNetwork) !== strlen($packedIp)) {
                continue;
            }

            $prefix = (int) $prefixRaw;
            $maxBits = strlen($packedIp) * 8;
            if ($prefix < 0 || $prefix > $maxBits) {
                continue;
            }

            $wholeBytes = intdiv($prefix, 8);
            $remainingBits = $prefix % 8;

            if ($wholeBytes > 0 && substr($packedIp, 0, $wholeBytes) !== substr($packedNetwork, 0, $wholeBytes)) {
                continue;
            }
            if ($remainingBits === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
            if ((ord($packedIp[$wholeBytes]) & $mask) === (ord($packedNetwork[$wholeBytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    private static function safeDownloadMime(string $mime): string
    {
        return in_array($mime, VGT_Omega_Config::globallyAllowedMimes(), true)
            ? $mime
            : 'application/octet-stream';
    }

    private static function asciiFilename(string $filename): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'download.bin';
        return $ascii !== '' ? substr($ascii, 0, 180) : 'download.bin';
    }
}
