<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGT_Omega_Frontend
{
    private static bool $localized = false;

    public static function render_shortcode(): string
    {
        return self::render_form_shortcode(['id' => 1]);
    }

    /**
     * @param array<string,mixed> $atts
     */
    public static function render_form_shortcode(array $atts): string
    {
        if (!VGT_Omega_Config::isReady()) {
            return '<div class="vgt-omega-error">' .
                esc_html__('Secure intake is temporarily unavailable.', 'vgt-omega-vault') .
                '</div>';
        }

        $id = isset($atts['id']) && is_scalar($atts['id']) ? absint((string) $atts['id']) : 0;
        if ($id <= 0) {
            return '<div class="vgt-omega-error">' . esc_html__('Invalid form identifier.', 'vgt-omega-vault') . '</div>';
        }

        $form = VGT_Omega_DB::get_form($id);
        if ($form === null || !is_string($form->config)) {
            return '<div class="vgt-omega-error">' . esc_html__('Form not found.', 'vgt-omega-vault') . '</div>';
        }

        try {
            $config = VGT_Omega_Schema::decodeAndSanitize($form->config, $id);
        } catch (\Throwable $e) {
            error_log('[VGT OMEGA RENDER] ' . $e->getMessage());
            return '<div class="vgt-omega-error">' . esc_html__('Form configuration rejected.', 'vgt-omega-vault') . '</div>';
        }

        self::enqueueAssets();

        $settings = $config['settings'];
        $theme = $settings['theme'] === 'light' ? 'light' : 'dark';
        $hasFile = false;
        foreach ($config['fields'] as $field) {
            if ($field['type'] === 'file') {
                $hasFile = true;
                break;
            }
        }

        $steps = self::buildSteps($config['fields']);
        $formDomId = 'vgt-omega-form-' . $id . '-' . wp_unique_id();

        ob_start();
        ?>
        <section class="vgt-omega-shell vgt-omega-theme-<?php echo esc_attr($theme); ?>">
            <header class="vgt-omega-header">
                <h2><?php echo esc_html((string) $config['title']); ?></h2>
                <p><?php echo esc_html((string) $settings['subtitle']); ?></p>
            </header>

            <form
                id="<?php echo esc_attr($formDomId); ?>"
                class="vgt-omega-form"
                method="post"
                action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                <?php echo $hasFile ? 'enctype="multipart/form-data"' : ''; ?>
                data-vgt-form-id="<?php echo esc_attr((string) $id); ?>"
                novalidate
            >
                <input type="hidden" name="action" value="vgt_submit_builder_form">
                <input type="hidden" name="form_id" value="<?php echo esc_attr((string) $id); ?>">
                <input type="hidden" name="vgt_nonce" value="">
                <input type="hidden" name="vgt_request_token" value="">

                <div class="vgt-omega-honeypot" aria-hidden="true">
                    <label>
                        <?php echo esc_html__('Leave this field empty', 'vgt-omega-vault'); ?>
                        <input type="text" name="vgt_full_name" value="" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                <?php foreach ($steps as $stepIndex => $stepFields): ?>
                    <fieldset
                        class="vgt-omega-step"
                        data-vgt-step="<?php echo esc_attr((string) $stepIndex); ?>"
                        <?php echo $stepIndex === 0 ? '' : 'hidden'; ?>
                    >
                        <legend class="screen-reader-text">
                            <?php
                            echo esc_html(
                                sprintf(
                                    __('Step %1$d of %2$d', 'vgt-omega-vault'),
                                    $stepIndex + 1,
                                    count($steps)
                                )
                            );
                            ?>
                        </legend>

                        <?php foreach ($stepFields as $field): ?>
                            <?php self::renderField($field); ?>
                        <?php endforeach; ?>

                        <?php if (count($steps) > 1): ?>
                            <div class="vgt-omega-step-actions">
                                <?php if ($stepIndex > 0): ?>
                                    <button type="button" class="vgt-omega-secondary" data-vgt-back>
                                        <?php echo esc_html__('Back', 'vgt-omega-vault'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($stepIndex < count($steps) - 1): ?>
                                    <button type="button" class="vgt-omega-primary" data-vgt-next>
                                        <?php echo esc_html__('Next', 'vgt-omega-vault'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </fieldset>
                <?php endforeach; ?>

                <?php if (!empty($settings['consent_required'])): ?>
                    <label class="vgt-omega-consent">
                        <input type="checkbox" name="vgt_consent" value="1" required>
                        <span>
                            <?php echo esc_html__('I consent to server-side encrypted storage of my submission and security metadata.', 'vgt-omega-vault'); ?>
                        </span>
                    </label>
                <?php endif; ?>

                <button type="submit" class="vgt-omega-submit">
                    <?php echo esc_html((string) $settings['button_text']); ?>
                </button>

                <p class="vgt-omega-status" role="status" aria-live="polite"></p>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param list<array<string,mixed>> $fields
     * @return list<list<array<string,mixed>>>
     */
    private static function buildSteps(array $fields): array
    {
        $steps = [[]];
        foreach ($fields as $field) {
            if ($field['type'] === 'step_break') {
                if ($steps[array_key_last($steps)] !== []) {
                    $steps[] = [];
                }
                continue;
            }
            $steps[array_key_last($steps)][] = $field;
        }

        $steps = array_values(array_filter(
            $steps,
            static fn(array $step): bool => $step !== []
        ));

        return $steps !== [] ? $steps : [[]];
    }

    /**
     * @param array<string,mixed> $field
     */
    private static function renderField(array $field): void
    {
        $id = (string) $field['id'];
        $type = (string) $field['type'];
        $label = (string) $field['label'];
        $required = !empty($field['required']);

        if ($type === 'heading') {
            echo '<h3 class="vgt-omega-heading">' . esc_html($label) . '</h3>';
            return;
        }
        if ($type === 'paragraph') {
            echo '<p class="vgt-omega-paragraph">' . esc_html($label) . '</p>';
            return;
        }
        if ($type === 'image') {
            echo '<figure class="vgt-omega-media"><img loading="lazy" decoding="async" src="' .
                esc_url((string) $field['media_url']) . '" alt="' . esc_attr($label) . '"></figure>';
            return;
        }
        if ($type === 'video') {
            echo '<figure class="vgt-omega-media"><video controls preload="metadata" src="' .
                esc_url((string) $field['media_url']) . '"></video></figure>';
            return;
        }

        echo '<div class="vgt-omega-field">';
        if ($type !== 'radio') {
            echo '<label for="' . esc_attr($id) . '">' . esc_html($label);
            if ($required) {
                echo ' <span aria-hidden="true">*</span>';
            }
            echo '</label>';
        }

        $common = ' id="' . esc_attr($id) . '" name="' . esc_attr($id) . '"';
        $common .= $required ? ' required' : '';
        $common .= ' data-vgt-max-length="' . esc_attr((string) $field['max_length']) . '"';

        if ($type === 'textarea') {
            echo '<textarea' . $common . ' maxlength="' . esc_attr((string) $field['max_length']) . '" placeholder="' .
                esc_attr((string) $field['placeholder']) . '"></textarea>';
        } elseif ($type === 'select') {
            echo '<select' . $common . '>';
            echo '<option value="">' . esc_html__('Select', 'vgt-omega-vault') . '</option>';
            foreach ($field['options'] as $option) {
                echo '<option value="' . esc_attr((string) $option) . '">' . esc_html((string) $option) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'radio') {
            echo '<fieldset class="vgt-omega-radio-group">';
            echo '<legend>' . esc_html($label) . ($required ? ' *' : '') . '</legend>';
            foreach ($field['options'] as $optionIndex => $option) {
                $radioId = $id . '_' . $optionIndex;
                echo '<label for="' . esc_attr($radioId) . '">';
                echo '<input type="radio" id="' . esc_attr($radioId) . '" name="' . esc_attr($id) . '" value="' .
                    esc_attr((string) $option) . '"' . ($required ? ' required' : '') . '>';
                echo '<span>' . esc_html((string) $option) . '</span></label>';
            }
            echo '</fieldset>';
        } elseif ($type === 'file') {
            $accept = implode(',', array_map('sanitize_mime_type', $field['allowed_mimes']));
            echo '<input type="file"' . $common . ' accept="' . esc_attr($accept) .
                '" data-vgt-max-bytes="' . esc_attr((string) $field['max_bytes']) . '">';
            echo '<small>' . esc_html(
                sprintf(
                    __('Maximum size: %s', 'vgt-omega-vault'),
                    size_format((int) $field['max_bytes'])
                )
            ) . '</small>';
        } else {
            $htmlType = $type === 'email' ? 'email' : ($type === 'number' ? 'number' : 'text');
            echo '<input type="' . esc_attr($htmlType) . '"' . $common .
                ' maxlength="' . esc_attr((string) $field['max_length']) . '"' .
                ' placeholder="' . esc_attr((string) $field['placeholder']) . '"';
            if ($type === 'number' && $field['min'] !== null) {
                echo ' min="' . esc_attr((string) $field['min']) . '"';
            }
            if ($type === 'number' && $field['max'] !== null) {
                echo ' max="' . esc_attr((string) $field['max']) . '"';
            }
            echo '>';
        }

        echo '</div>';
    }

    private static function enqueueAssets(): void
    {
        wp_enqueue_style('vgt-omega-frontend');
        wp_enqueue_script('vgt-omega-frontend');

        if (self::$localized) {
            return;
        }

        wp_localize_script('vgt-omega-frontend', 'vgtOmegaRuntime', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'tokenAction' => 'vgt_omega_issue_token',
            'messages' => [
                'tokenError' => __('Could not initialize the secure request token.', 'vgt-omega-vault'),
                'networkError' => __('The secure request could not be completed.', 'vgt-omega-vault'),
                'fileTooLarge' => __('A selected file exceeds the configured size limit.', 'vgt-omega-vault'),
                'sending' => __('Encrypting and transmitting…', 'vgt-omega-vault'),
            ],
        ]);
        self::$localized = true;
    }
}
