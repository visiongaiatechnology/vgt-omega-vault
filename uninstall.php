<?php
// STATUS: PLATIN

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!(defined('VGT_OMEGA_PURGE_ON_UNINSTALL') && VGT_OMEGA_PURGE_ON_UNINSTALL === true)) {
    return;
}

global $wpdb;

$storageRoot = defined('VGT_OMEGA_STORAGE_ROOT') && is_string(VGT_OMEGA_STORAGE_ROOT)
    ? rtrim(VGT_OMEGA_STORAGE_ROOT, DIRECTORY_SEPARATOR)
    : dirname(rtrim(ABSPATH, DIRECTORY_SEPARATOR)) . DIRECTORY_SEPARATOR . '.vgt-omega';

$resolvedRoot = realpath($storageRoot);
$marker = $resolvedRoot !== false
    ? $resolvedRoot . DIRECTORY_SEPARATOR . '.vgt-omega-root'
    : '';

$insideWebRoot = static function(string $candidate): bool {
    foreach ([realpath(ABSPATH), defined('WP_CONTENT_DIR') ? realpath(WP_CONTENT_DIR) : false] as $webRoot) {
        if (!is_string($webRoot)) {
            continue;
        }
        $root = rtrim($webRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $path = rtrim($candidate, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return true;
        }
    }
    return false;
};

if ($resolvedRoot !== false
    && is_dir($resolvedRoot)
    && !is_link($storageRoot)
    && !$insideWebRoot($resolvedRoot)
    && is_file($marker)
    && hash_equals("VGT OMEGA VAULT 7\n", (string) file_get_contents($marker))
) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolvedRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $entry) {
        $path = $entry->getPathname();
        $parent = realpath(dirname($path));
        if ($parent === false
            || !str_starts_with(
                rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                rtrim($resolvedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            )
        ) {
            error_log('[VGT OMEGA UNINSTALL] Path jail validation failed.');
            continue;
        }

        if ($entry->isLink() || $entry->isFile()) {
            if (!unlink($path)) {
                error_log('[VGT OMEGA UNINSTALL] File removal failed: ' . $path);
            }
        } elseif ($entry->isDir() && !rmdir($path)) {
            error_log('[VGT OMEGA UNINSTALL] Directory removal failed: ' . $path);
        }
    }

    if (!rmdir($resolvedRoot)) {
        error_log('[VGT OMEGA UNINSTALL] Vault root removal failed.');
    }
}

$tableSuffixes = [
    'vgt_omega_audits',
    'vgt_omega_forms',
    'vgt_omega_submissions',
    'vgt_omega_tokens',
    'vgt_omega_rate_limits',
];

foreach ($tableSuffixes as $suffix) {
    if (preg_match('/^[a-z0-9_]+$/', $suffix) !== 1) {
        continue;
    }
    $table = $wpdb->prefix . $suffix;
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
        error_log('[VGT OMEGA UNINSTALL] Database prefix validation failed.');
        continue;
    }
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
}

$options = [
    'vgt_omega_db_version',
    'vgt_omega_retention_days',
    'vgt_omega_enable_notifications',
    'vgt_omega_enable_honeypot',
    'vgt_omega_enable_file_uploads',
    'vgt_omega_allow_proxies',
    'vgt_omega_migration_warnings',
    'vgt_omega_upgrade_error_id',
    'vgt_omega_upgrade_lock',
];

foreach ($options as $option) {
    delete_option($option);
    delete_site_option($option);
}

wp_clear_scheduled_hook('vgt_omega_retention_purge');

$roles = wp_roles();
if ($roles instanceof WP_Roles) {
    foreach (array_keys($roles->roles) as $roleName) {
        $role = get_role((string) $roleName);
        if ($role !== null) {
            $role->remove_cap('manage_vgt_omega_vault');
        }
    }
}
