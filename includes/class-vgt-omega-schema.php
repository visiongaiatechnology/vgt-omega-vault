<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGT_Omega_Schema
{
    private const CONFIG_KEYS = [
        'id', 'title', 'type', 'schema_version', 'fields', 'settings',
    ];

    private const SETTINGS_KEYS = [
        'theme', 'button_text', 'subtitle', 'consent_required',
    ];

    private const FIELD_TYPES = [
        'text', 'email', 'number', 'textarea', 'select', 'radio', 'file',
        'heading', 'paragraph', 'image', 'video', 'step_break',
    ];

    private const DISPLAY_TYPES = ['heading', 'paragraph', 'image', 'video', 'step_break'];

    private const RESERVED_IDS = [
        'action', 'form_id', 'vgt_nonce', 'vgt_request_token', 'vgt_consent',
        'vgt_full_name', 'security', '_wpnonce', '_wp_http_referer',
    ];

    private const FIELD_KEYS = [
        'id', 'type', 'label', 'placeholder', 'required', 'options', 'media_url',
        'max_length', 'min', 'max', 'allowed_mimes', 'max_bytes',
    ];

    /**
     * @return array<string,mixed>
     */
    public static function decodeAndSanitize(string $raw, int $formId = 0): array
    {
        if ($raw === '' || strlen($raw) > VGT_Omega_Config::MAX_CONFIG_BYTES) {
            throw new \VGTOmegaVault\ValidationException(__('Invalid form configuration size.', 'vgt-omega-vault'), 422);
        }

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \VGTOmegaVault\ValidationException(__('Invalid form configuration JSON.', 'vgt-omega-vault'), 422);
        }

        if (!is_array($decoded)) {
            throw new \VGTOmegaVault\ValidationException(__('Invalid form configuration.', 'vgt-omega-vault'), 422);
        }

        return self::sanitize($decoded, $formId);
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public static function sanitize(array $config, int $formId = 0): array
    {
        $unknownConfigKeys = array_diff(array_keys($config), self::CONFIG_KEYS);
        if ($unknownConfigKeys !== []) {
            throw new \VGTOmegaVault\ValidationException(
                __('Form configuration contains unsupported properties.', 'vgt-omega-vault'),
                422
            );
        }

        $fields = $config['fields'] ?? null;
        if (!is_array($fields) || array_is_list($fields) === false || count($fields) < 1 || count($fields) > VGT_Omega_Config::MAX_FIELDS) {
            throw new \VGTOmegaVault\ValidationException(__('Form fields are missing or exceed the limit.', 'vgt-omega-vault'), 422);
        }

        $sanitizedFields = [];
        $seen = [];

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                throw new \VGTOmegaVault\ValidationException(
                    sprintf(__('Field %d is invalid.', 'vgt-omega-vault'), $index + 1),
                    422
                );
            }

            $unknown = array_diff(array_keys($field), self::FIELD_KEYS);
            if ($unknown !== []) {
                throw new \VGTOmegaVault\ValidationException(
                    sprintf(__('Field %d contains unsupported properties.', 'vgt-omega-vault'), $index + 1),
                    422
                );
            }

            $type = isset($field['type']) && is_string($field['type']) ? strtolower(trim($field['type'])) : '';
            if (!in_array($type, self::FIELD_TYPES, true)) {
                throw new \VGTOmegaVault\ValidationException(
                    sprintf(__('Field %d has an unsupported type.', 'vgt-omega-vault'), $index + 1),
                    422
                );
            }

            $id = isset($field['id']) && is_string($field['id']) ? strtolower(trim($field['id'])) : '';
            if ($id === '' && in_array($type, self::DISPLAY_TYPES, true)) {
                $id = $type . '_' . ($index + 1);
            }

            if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $id) !== 1 || in_array($id, self::RESERVED_IDS, true)) {
                throw new \VGTOmegaVault\ValidationException(
                    sprintf(__('Field %d has an invalid identifier.', 'vgt-omega-vault'), $index + 1),
                    422
                );
            }
            if (isset($seen[$id])) {
                throw new \VGTOmegaVault\ValidationException(__('Field identifiers must be unique.', 'vgt-omega-vault'), 422);
            }
            $seen[$id] = true;

            $label = self::cleanText($field['label'] ?? '', 200);
            $placeholder = self::cleanText($field['placeholder'] ?? '', 240);
            $required = !empty($field['required']);
            $options = self::sanitizeOptions($field['options'] ?? []);
            $mediaUrl = self::sanitizeSameOriginUrl($field['media_url'] ?? '');

            $maxLengthDefault = $type === 'textarea' ? VGT_Omega_Config::MAX_TEXT_BYTES : 2048;
            $maxLength = isset($field['max_length']) && is_numeric($field['max_length'])
                ? max(1, min(VGT_Omega_Config::MAX_TEXT_BYTES, (int) $field['max_length']))
                : $maxLengthDefault;

            $sanitized = [
                'id' => $id,
                'type' => $type,
                'label' => $label,
                'placeholder' => $placeholder,
                'required' => $required,
                'options' => $options,
                'media_url' => $mediaUrl,
                'max_length' => $maxLength,
            ];

            if ($type === 'number') {
                $sanitized['min'] = self::optionalFiniteNumber($field['min'] ?? null);
                $sanitized['max'] = self::optionalFiniteNumber($field['max'] ?? null);
                if ($sanitized['min'] !== null && $sanitized['max'] !== null && $sanitized['min'] > $sanitized['max']) {
                    throw new \VGTOmegaVault\ValidationException(__('Numeric field bounds are invalid.', 'vgt-omega-vault'), 422);
                }
            }

            if ($type === 'file') {
                $sanitized['allowed_mimes'] = self::sanitizeMimes($field['allowed_mimes'] ?? []);
                $sanitized['max_bytes'] = isset($field['max_bytes']) && is_numeric($field['max_bytes'])
                    ? max(1, min(VGT_Omega_Config::MAX_FILE_BYTES, (int) $field['max_bytes']))
                    : VGT_Omega_Config::MAX_FILE_BYTES;
            }

            if (in_array($type, ['select', 'radio'], true) && $options === []) {
                throw new \VGTOmegaVault\ValidationException(__('Select and radio fields require options.', 'vgt-omega-vault'), 422);
            }

            if (in_array($type, ['image', 'video'], true) && $mediaUrl === '') {
                throw new \VGTOmegaVault\ValidationException(__('Media fields require a URL hosted by this website.', 'vgt-omega-vault'), 422);
            }

            $sanitizedFields[] = $sanitized;
        }

        $settings = isset($config['settings']) && is_array($config['settings']) ? $config['settings'] : [];
        $unknownSettingKeys = array_diff(array_keys($settings), self::SETTINGS_KEYS);
        if ($unknownSettingKeys !== []) {
            throw new \VGTOmegaVault\ValidationException(
                __('Form settings contain unsupported properties.', 'vgt-omega-vault'),
                422
            );
        }

        $subtitle = self::cleanText($settings['subtitle'] ?? __('TLS-protected transmission with encrypted storage', 'vgt-omega-vault'), 200);
        if (strcasecmp($subtitle, 'End-to-End Encrypted Tunnel') === 0) {
            $subtitle = __('TLS-protected transmission with encrypted storage', 'vgt-omega-vault');
        }

        return [
            'id' => $formId > 0 ? $formId : 0,
            'title' => self::cleanText($config['title'] ?? __('Secure Intake', 'vgt-omega-vault'), 160),
            'type' => isset($config['type']) && $config['type'] === 'funnel' ? 'funnel' : 'form',
            'schema_version' => 2,
            'fields' => $sanitizedFields,
            'settings' => [
                'theme' => isset($settings['theme']) && $settings['theme'] === 'light' ? 'light' : 'dark',
                'button_text' => self::cleanText($settings['button_text'] ?? __('Submit securely', 'vgt-omega-vault'), 80),
                'subtitle' => $subtitle,
                'consent_required' => array_key_exists('consent_required', $settings) ? !empty($settings['consent_required']) : true,
            ],
        ];
    }

    /**
     * Normalizes the trusted legacy database representation before strict validation.
     *
     * @param array<string,mixed> $legacy
     * @return array<string,mixed>
     */
    public static function migrateLegacy(array $legacy, int $formId): array
    {
        $fields = isset($legacy['fields']) && is_array($legacy['fields']) ? $legacy['fields'] : [];
        $normalizedFields = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $normalized = array_intersect_key($field, array_flip(self::FIELD_KEYS));
            if (isset($normalized['options']) && is_string($normalized['options'])) {
                $normalized['options'] = preg_split('/(?:\R|,)/u', $normalized['options']) ?: [];
            }
            $normalizedFields[] = $normalized;
        }

        $legacySettings = isset($legacy['settings']) && is_array($legacy['settings'])
            ? $legacy['settings']
            : [];

        $consentRequired = true;
        if (array_key_exists('consent_required', $legacySettings)) {
            $consentRequired = !empty($legacySettings['consent_required']);
        } elseif (array_key_exists('gdpr_enabled', $legacySettings)) {
            $consentRequired = !empty($legacySettings['gdpr_enabled']);
        }

        $subtitle = isset($legacySettings['subtitle']) && is_scalar($legacySettings['subtitle'])
            ? (string) $legacySettings['subtitle']
            : __('TLS-protected transmission with encrypted storage', 'vgt-omega-vault');
        if (strcasecmp(trim($subtitle), 'End-to-End Encrypted Tunnel') === 0) {
            $subtitle = __('TLS-protected transmission with encrypted storage', 'vgt-omega-vault');
        }

        $candidate = [
            'id' => $formId,
            'title' => isset($legacy['title']) && is_scalar($legacy['title'])
                ? (string) $legacy['title']
                : __('Secure Intake', 'vgt-omega-vault'),
            'type' => isset($legacy['type']) && $legacy['type'] === 'funnel' ? 'funnel' : 'form',
            'schema_version' => 2,
            'fields' => $normalizedFields,
            'settings' => [
                'theme' => isset($legacySettings['theme']) && $legacySettings['theme'] === 'light'
                    ? 'light'
                    : 'dark',
                'button_text' => isset($legacySettings['button_text']) && is_scalar($legacySettings['button_text'])
                    ? (string) $legacySettings['button_text']
                    : __('Submit securely', 'vgt-omega-vault'),
                'subtitle' => $subtitle,
                'consent_required' => $consentRequired,
            ],
        ];

        return self::sanitize($candidate, $formId);
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,array<string,mixed>>
     */
    public static function inputFieldsById(array $config): array
    {
        $result = [];
        foreach ($config['fields'] as $field) {
            if (!is_array($field) || in_array($field['type'], self::DISPLAY_TYPES, true)) {
                continue;
            }
            $result[(string) $field['id']] = $field;
        }
        return $result;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function sanitizeOptions(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/(?:\R|,)/u', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $option) {
            if (!is_scalar($option)) {
                continue;
            }
            $clean = self::cleanText((string) $option, 120);
            if ($clean !== '' && !in_array($clean, $result, true)) {
                $result[] = $clean;
            }
            if (count($result) >= 50) {
                break;
            }
        }
        return $result;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function sanitizeMimes(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        if (!is_array($value)) {
            $value = [];
        }

        $global = VGT_Omega_Config::globallyAllowedMimes();
        $result = [];

        foreach ($value as $mime) {
            if (is_string($mime) && in_array($mime, $global, true) && !in_array($mime, $result, true)) {
                $result[] = $mime;
            }
        }

        if ($result === []) {
            $result = array_values(array_intersect(['image/jpeg', 'image/png', 'image/webp'], $global));
        }

        return $result;
    }

    private static function sanitizeSameOriginUrl(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return '';
        }

        $url = esc_url_raw(trim($value), ['https', 'http']);
        if ($url === '') {
            throw new \VGTOmegaVault\ValidationException(__('Media URL is invalid.', 'vgt-omega-vault'), 422);
        }

        $urlHost = wp_parse_url($url, PHP_URL_HOST);
        $homeHost = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $urlScheme = wp_parse_url($url, PHP_URL_SCHEME);

        if (!is_string($urlHost) || !is_string($homeHost) || strcasecmp($urlHost, $homeHost) !== 0) {
            throw new \VGTOmegaVault\ValidationException(__('Media URLs must use this website.', 'vgt-omega-vault'), 422);
        }
        if ($urlScheme !== 'https' && !(wp_get_environment_type() === 'local' && $urlScheme === 'http')) {
            throw new \VGTOmegaVault\ValidationException(__('Media URLs must use HTTPS.', 'vgt-omega-vault'), 422);
        }

        return $url;
    }

    private static function optionalFiniteNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new \VGTOmegaVault\ValidationException(__('Numeric field bounds must be numbers.', 'vgt-omega-vault'), 422);
        }
        $number = (float) $value;
        if (is_nan($number) || is_infinite($number)) {
            throw new \VGTOmegaVault\ValidationException(__('Numeric field bounds must be finite.', 'vgt-omega-vault'), 422);
        }
        return $number;
    }

    private static function cleanText(mixed $value, int $maxCharacters): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = sanitize_text_field((string) $value);
        return mb_substr($text, 0, $maxCharacters, 'UTF-8');
    }
}
