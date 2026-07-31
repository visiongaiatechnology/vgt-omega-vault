<?php
// STATUS: PLATIN

/**
 * Plugin Name: VGT OMEGA VAULT
 * Plugin URI: https://visiongaiatechnology.de
 * Description: Hardened encrypted intake vault with schema-bound uploads, one-time request tokens and private encrypted file storage.
 * Version: 7.0.0
 * Author: VisionGaia Technology
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * License: AGPL-3.0-or-later
 * Text Domain: vgt-omega-vault
 */

declare(strict_types=1);

namespace VGTOmegaVault {
    use ErrorException;
    use Exception;
    use Throwable;

    class AppException extends Exception {}
    class ValidationException extends AppException {} // USER-FACING: Message verbatim shown
    class SecurityException   extends AppException {} // INTERNAL: Generic message, detail to error_log
    class StorageException    extends AppException {} // INTERNAL: Generic message, detail to error_log

    final class CoreEngine
    {
        private static int $lastHttpStatus = 200;
        private static string $lastErrorId = '';

        /**
         * @return array{status:string,data?:mixed,message?:string,error_id?:string}
         */
        public static function execute(callable $operation): array
        {
            self::$lastHttpStatus = 200;
            self::$lastErrorId = '';

            $previousDisplayErrors = ini_get('display_errors');
            $previousErrorReporting = error_reporting();

            ini_set('display_errors', '0');
            error_reporting(E_ALL);
            set_error_handler(static function(int $sev, string $msg, string $file, int $line): bool {
                if (!(error_reporting() & $sev)) return false;
                throw new ErrorException($msg, 0, $sev, $file, $line);
            });

            try {
                $response = ['status' => 'success', 'data' => $operation()];
            } catch (ValidationException $e) {
                self::$lastHttpStatus = self::normalizeStatus($e->getCode(), 422);
                $response = ['status' => 'error', 'message' => $e->getMessage()];
            } catch (SecurityException $e) {
                self::$lastHttpStatus = self::normalizeStatus($e->getCode(), 403);
                self::$lastErrorId = self::errorId();
                error_log('[SEC][' . self::$lastErrorId . '] ' . $e->getMessage());
                $response = ['status' => 'error', 'message' => 'Request rejected for security reasons.'];
            } catch (StorageException $e) {
                self::$lastHttpStatus = self::normalizeStatus($e->getCode(), 500);
                self::$lastErrorId = self::errorId();
                error_log('[STORAGE][' . self::$lastErrorId . '] ' . $e->getMessage());
                $response = ['status' => 'error', 'message' => 'A server error occurred.'];
            } catch (Throwable $e) {
                self::$lastHttpStatus = 500;
                self::$lastErrorId = self::errorId();
                error_log('[FATAL][' . self::$lastErrorId . '] ' . $e->getMessage());
                $response = ['status' => 'error', 'message' => 'Critical system fault.'];
            } finally {
                restore_error_handler();
                if (is_string($previousDisplayErrors)) {
                    ini_set('display_errors', $previousDisplayErrors);
                }
                error_reporting($previousErrorReporting);
            }

            if (self::$lastErrorId !== '') {
                $response['error_id'] = self::$lastErrorId;
            }

            return $response;
        }

        public static function lastHttpStatus(): int
        {
            return self::$lastHttpStatus;
        }

        private static function normalizeStatus(int $candidate, int $fallback): int
        {
            return $candidate >= 400 && $candidate <= 599 ? $candidate : $fallback;
        }

        private static function errorId(): string
        {
            return strtoupper(bin2hex(random_bytes(8)));
        }
    }
}

namespace {
    use VGTOmegaVault\CoreEngine;
    use VGTOmegaVault\SecurityException;
    use VGTOmegaVault\StorageException;
    use VGTOmegaVault\ValidationException;

    if (!defined('ABSPATH')) {
        exit;
    }

    define('VGT_OMEGA_VERSION', '7.0.0');
    define('VGT_OMEGA_DB_VERSION', '7.0.0');
    define('VGT_OMEGA_PATH', plugin_dir_path(__FILE__));
    define('VGT_OMEGA_URL', plugin_dir_url(__FILE__));

    require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-config.php';
    require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-crypto.php';
    require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-schema.php';
    require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-db.php';
    require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-rate-limiter.php';
    require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-scanner.php';
    require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-file-vault.php';
    require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-api.php';
    require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-frontend.php';
    require_once VGT_OMEGA_PATH . 'includes/class-vgt-omega-ui.php';

    final class VGT_Omega_Bootstrapper
    {
        private const CRON_HOOK = 'vgt_omega_retention_purge';
        private const UPGRADE_LOCK_OPTION = 'vgt_omega_upgrade_lock';
        private const UPGRADE_LOCK_TTL = 900;

        public static function ignite(): void
        {
            register_activation_hook(__FILE__, [self::class, 'activate']);
            register_deactivation_hook(__FILE__, [self::class, 'deactivate']);

            add_action('plugins_loaded', [self::class, 'maybeUpgrade'], 5);
            add_action('init', [self::class, 'registerAssets']);
            add_filter('wp_headers', [self::class, 'securityHeaders']);
            add_filter('upload_mimes', [self::class, 'doNotBroadenWordPressMimes'], 999);

            add_action('admin_menu', [self::class, 'registerMenu']);
            add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminAssets']);

            add_shortcode('vgt_omega_comlink', [VGT_Omega_Frontend::class, 'render_shortcode']);
            add_shortcode('vgt_omega_form', [VGT_Omega_Frontend::class, 'render_form_shortcode']);

            add_action('wp_ajax_vgt_omega_issue_token', [VGT_Omega_API::class, 'handle_issue_token']);
            add_action('wp_ajax_nopriv_vgt_omega_issue_token', [VGT_Omega_API::class, 'handle_issue_token']);

            add_action('wp_ajax_vgt_submit_builder_form', [VGT_Omega_API::class, 'handle_submit_builder_form']);
            add_action('wp_ajax_nopriv_vgt_submit_builder_form', [VGT_Omega_API::class, 'handle_submit_builder_form']);
            add_action('wp_ajax_vgt_omega_audit_request', [VGT_Omega_API::class, 'handle_request']);
            add_action('wp_ajax_nopriv_vgt_omega_audit_request', [VGT_Omega_API::class, 'handle_request']);

            add_action('wp_ajax_vgt_save_form_builder', [VGT_Omega_API::class, 'handle_save_form']);
            add_action('wp_ajax_vgt_delete_form', [VGT_Omega_API::class, 'handle_delete_form']);
            add_action('wp_ajax_vgt_get_submissions', [VGT_Omega_API::class, 'handle_get_submissions']);
            add_action('wp_ajax_vgt_delete_submission', [VGT_Omega_API::class, 'handle_delete_submission']);

            add_action('admin_post_vgt_omega_save_form_admin', [self::class, 'saveFormAdmin']);
            add_action('admin_post_vgt_omega_delete_form_admin', [self::class, 'deleteFormAdmin']);
            add_action('admin_post_vgt_omega_delete_submission_admin', [self::class, 'deleteSubmissionAdmin']);
            add_action('admin_post_vgt_omega_save_settings', [self::class, 'saveSettings']);
            add_action('admin_post_vgt_omega_download_file', [VGT_Omega_API::class, 'handle_download_file']);

            add_action(self::CRON_HOOK, [self::class, 'purgeExpiredData']);
        }

        public static function activate(): void
        {
            VGT_Omega_Config::assertEnvironment();
            VGT_Omega_Crypto::installKey();
            VGT_Omega_File_Vault::install();
            VGT_Omega_DB::install();
            self::grantCapability();
            VGT_Omega_Crypto::finalizeLegacyMigration();

            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
            }

            update_option('vgt_omega_db_version', VGT_OMEGA_DB_VERSION, false);
            delete_option('vgt_omega_upgrade_error_id');
        }

        public static function deactivate(): void
        {
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
            if ($timestamp !== false) {
                wp_unschedule_event($timestamp, self::CRON_HOOK);
            }
        }

        public static function maybeUpgrade(): void
        {
            if (get_option('vgt_omega_db_version', '0') === VGT_OMEGA_DB_VERSION) {
                return;
            }

            $lock = self::acquireUpgradeLock();
            if ($lock === null) {
                return;
            }

            try {
                self::activate();
                delete_option('vgt_omega_upgrade_error_id');
            } catch (\Throwable $e) {
                $errorId = strtoupper(bin2hex(random_bytes(8)));
                update_option('vgt_omega_upgrade_error_id', $errorId, false);
                error_log('[VGT OMEGA UPGRADE][' . $errorId . '] ' . $e->getMessage());
            } finally {
                self::releaseUpgradeLock($lock);
            }
        }

        public static function registerAssets(): void
        {
            wp_register_style(
                'vgt-omega-frontend',
                VGT_OMEGA_URL . 'assets/css/frontend.css',
                [],
                VGT_OMEGA_VERSION
            );
            wp_register_script(
                'vgt-omega-frontend',
                VGT_OMEGA_URL . 'assets/js/frontend.js',
                [],
                VGT_OMEGA_VERSION,
                true
            );
        }

        /**
         * @param array<string,string> $headers
         * @return array<string,string>
         */
        public static function securityHeaders(array $headers): array
        {
            $headers['X-Content-Type-Options'] = 'nosniff';
            $headers['X-Frame-Options'] = 'DENY';
            $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
            $headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=(), payment=(), usb=()';
            if (is_ssl()) {
                $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
            }
            return $headers;
        }

        /**
         * This plugin never expands WordPress' global MIME allowlist.
         *
         * @param array<string,string> $mimes
         * @return array<string,string>
         */
        public static function doNotBroadenWordPressMimes(array $mimes): array
        {
            return $mimes;
        }

        public static function registerMenu(): void
        {
            add_menu_page(
                __('VGT Vault', 'vgt-omega-vault'),
                __('VGT Vault', 'vgt-omega-vault'),
                VGT_Omega_Config::CAPABILITY,
                'vgt-omega-vault',
                [VGT_Omega_UI::class, 'render'],
                'dashicons-shield',
                58
            );
        }

        public static function enqueueAdminAssets(string $hook): void
        {
            if ($hook !== 'toplevel_page_vgt-omega-vault') {
                return;
            }

            wp_enqueue_style(
                'vgt-omega-admin',
                VGT_OMEGA_URL . 'assets/css/admin.css',
                [],
                VGT_OMEGA_VERSION
            );
            nocache_headers();
        }

        public static function saveFormAdmin(): void
        {
            self::requireAdminPost('vgt_omega_save_form_admin');

            $response = CoreEngine::execute(static function(): int {
                $id = isset($_POST['form_id']) && is_scalar($_POST['form_id'])
                    ? absint((string) wp_unslash($_POST['form_id']))
                    : 0;
                $title = isset($_POST['title']) && is_string($_POST['title'])
                    ? sanitize_text_field(wp_unslash($_POST['title']))
                    : '';
                $type = isset($_POST['type']) && is_string($_POST['type']) && wp_unslash($_POST['type']) === 'funnel'
                    ? 'funnel'
                    : 'form';
                $raw = isset($_POST['config']) && is_string($_POST['config'])
                    ? wp_unslash($_POST['config'])
                    : '';

                if ($title === '' || mb_strlen($title) > 160) {
                    throw new ValidationException(__('A valid title is required.', 'vgt-omega-vault'), 422);
                }

                $config = VGT_Omega_Schema::decodeAndSanitize($raw, $id);
                $config['title'] = $title;
                $config['type'] = $type;
                $encoded = wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                if ($id > 0) {
                    if (!VGT_Omega_DB::update_form($id, ['title' => $title, 'type' => $type, 'config' => $encoded])) {
                        throw new StorageException('Form update failed.');
                    }
                    VGT_Omega_DB::clearMigrationWarning($id);
                    return $id;
                }

                $newId = VGT_Omega_DB::insert_form(['title' => $title, 'type' => $type, 'config' => $encoded]);
                if ($newId <= 0) {
                    throw new StorageException('Form insert failed.');
                }
                return $newId;
            });

            self::redirectWithResult($response, 'forms');
        }

        public static function deleteFormAdmin(): void
        {
            self::requireAdminPost('vgt_omega_delete_form_admin');

            $response = CoreEngine::execute(static function(): bool {
                $id = isset($_POST['form_id']) && is_scalar($_POST['form_id'])
                    ? absint((string) wp_unslash($_POST['form_id']))
                    : 0;
                if ($id <= 0) {
                    throw new ValidationException(__('Invalid form identifier.', 'vgt-omega-vault'), 422);
                }
                VGT_Omega_API::deleteFormAndFiles($id);
                return true;
            });

            self::redirectWithResult($response, 'forms');
        }

        public static function deleteSubmissionAdmin(): void
        {
            self::requireAdminPost('vgt_omega_delete_submission_admin');

            $response = CoreEngine::execute(static function(): bool {
                $id = isset($_POST['submission_id']) && is_scalar($_POST['submission_id'])
                    ? absint((string) wp_unslash($_POST['submission_id']))
                    : 0;
                if ($id <= 0) {
                    throw new ValidationException(__('Invalid submission identifier.', 'vgt-omega-vault'), 422);
                }
                VGT_Omega_API::deleteSubmissionAndFiles($id);
                return true;
            });

            self::redirectWithResult($response, 'submissions');
        }

        public static function saveSettings(): void
        {
            self::requireAdminPost('vgt_omega_save_settings');

            $response = CoreEngine::execute(static function(): bool {
                $retention = isset($_POST['retention_days']) && is_scalar($_POST['retention_days'])
                    ? absint((string) wp_unslash($_POST['retention_days']))
                    : 90;
                $retention = max(1, min(3650, $retention));

                $enableProxies = isset($_POST['allow_proxies']);
                if ($enableProxies && VGT_Omega_Config::trustedProxyCidrs() === []) {
                    throw new ValidationException(
                        __('Trusted proxy CIDRs must be configured before proxy support is enabled.', 'vgt-omega-vault'),
                        422
                    );
                }

                update_option('vgt_omega_retention_days', $retention, false);
                update_option('vgt_omega_enable_notifications', isset($_POST['enable_notifications']) ? '1' : '0', false);
                update_option('vgt_omega_enable_honeypot', isset($_POST['enable_honeypot']) ? '1' : '0', false);
                update_option('vgt_omega_enable_file_uploads', isset($_POST['enable_file_uploads']) ? '1' : '0', false);
                update_option('vgt_omega_allow_proxies', $enableProxies ? '1' : '0', false);
                return true;
            });

            self::redirectWithResult($response, 'settings');
        }

        public static function purgeExpiredData(): void
        {
            if (!VGT_Omega_Config::isReady()) {
                return;
            }

            $days = VGT_Omega_Config::retentionDays();
            $rows = VGT_Omega_DB::getExpiredSubmissions($days, 250);

            foreach ($rows as $row) {
                try {
                    VGT_Omega_API::deleteSubmissionAndFiles((int) $row->id);
                } catch (\Throwable $e) {
                    error_log('[VGT OMEGA RETENTION] ' . $e->getMessage());
                }
            }

            VGT_Omega_DB::purgeExpiredSecurityState();
        }

        private static function acquireUpgradeLock(): ?string
        {
            $token = time() . ':' . bin2hex(random_bytes(16));
            if (add_option(self::UPGRADE_LOCK_OPTION, $token, '', false)) {
                return $token;
            }

            $existing = get_option(self::UPGRADE_LOCK_OPTION, '');
            if (!is_string($existing) || preg_match('/^(\d+):[a-f0-9]{32}$/', $existing, $match) !== 1) {
                return null;
            }

            if ((int) $match[1] >= (time() - self::UPGRADE_LOCK_TTL)) {
                return null;
            }

            global $wpdb;
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
                self::UPGRADE_LOCK_OPTION,
                $existing
            ));
            if ($deleted !== 1 || !add_option(self::UPGRADE_LOCK_OPTION, $token, '', false)) {
                return null;
            }

            return $token;
        }

        private static function releaseUpgradeLock(string $token): void
        {
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
                self::UPGRADE_LOCK_OPTION,
                $token
            ));
        }

        private static function grantCapability(): void
        {
            $role = get_role('administrator');
            if ($role !== null) {
                $role->add_cap(VGT_Omega_Config::CAPABILITY);
            }
        }

        private static function requireAdminPost(string $action): void
        {
            if (!current_user_can(VGT_Omega_Config::CAPABILITY)) {
                wp_die(esc_html__('Forbidden.', 'vgt-omega-vault'), '', ['response' => 403]);
            }
            check_admin_referer($action);
            VGT_Omega_API::assertSameOriginRequest();
        }

        /**
         * @param array{status:string,data?:mixed,message?:string,error_id?:string} $response
         */
        private static function redirectWithResult(array $response, string $tab): void
        {
            $args = [
                'page' => 'vgt-omega-vault',
                'tab' => $tab,
                'vgt_status' => $response['status'],
            ];
            if (isset($response['error_id'])) {
                $args['vgt_error_id'] = $response['error_id'];
            }
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        }
    }

    VGT_Omega_Bootstrapper::ignite();
}
