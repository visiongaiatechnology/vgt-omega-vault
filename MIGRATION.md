# Migration Runbook: 6.x → 7.0.0

## 1. Freeze and back up

- Put the form into maintenance mode or block its AJAX actions.
- Export the WordPress database.
- Copy `wp-content/uploads/vgt_keys/.vgt_core_secret.php` separately.
- Inventory existing `file_upload` payloads and their public URLs.
- Verify that both database and key backups can be restored.

## 2. Prepare private storage

Create a directory outside the web root, owned by the PHP-FPM account:

```bash
install -d -m 0700 -o <php-user> -g <php-group> /srv/vgt-private
```

Add to `wp-config.php`:

```php
// STATUS: PLATIN
define('VGT_OMEGA_STORAGE_ROOT', '/srv/vgt-private/omega');
define('VGT_OMEGA_KEY_FILE', '/srv/vgt-private/omega/keys/master.key');
```

Do not create `master.key` manually unless importing a validated 32-byte key encoded as 64 hexadecimal characters.

## 3. Stage the replacement

- Replace the plugin directory in staging.
- Activate 7.0.0.
- Confirm the legacy key was copied and the old PHP key file removed.
- Confirm the new file is mode `0600` and directories are `0700`.
- Confirm the activation log reports no migration error ID.
- Open several old submissions to prove the transactional v3 re-encryption completed.
- Export form configurations and correct any properties rejected by the strict schema.

## 4. Regression tests

- Normal text-only submission succeeds.
- Replayed one-time token fails.
- Cross-origin POST fails.
- Unknown text field fails.
- Unknown file field fails before the upload scanner.
- Authorized image succeeds only when uploads are enabled and a `file` field exists.
- Airlock rejection still blocks the upload.
- The encrypted file exists only in the private vault.
- The downloaded file hash matches the submission hash.
- Deleting the submission deletes the private file.

## 5. Legacy public files

7.0.0 does not automatically remove historical public upload files.

For each legacy `file_upload` object:

1. Verify the URL belongs to the site's own upload base URL.
2. Resolve it to a canonical path under the upload base directory.
3. Reject symlinks and path escapes.
4. Re-scan the physical file.
5. Store it through the private encrypted vault.
6. Update the encrypted submission payload transactionally.
7. Verify decryption and file download.
8. Delete the public source.
9. Purge CDN and page caches.
10. Record the old URL and new opaque file ID in an offline migration log.

Never bulk-delete by filename pattern alone.

## 6. Production rollout

- Repeat the storage preparation.
- Freeze submissions.
- Take a final backup.
- Deploy the tested ZIP.
- Activate and run the security gate.
- Verify old records and one new record.
- Re-enable submissions.
- Monitor application logs, HTTP 403/413/415/422/429/500 rates, disk capacity, and database token/rate tables.

## Rollback

Rollback requires the pre-upgrade 6.x database snapshot, the 6.x plugin, and the legacy-compatible key. New 7.x encrypted file containers are not understood by 6.x. A database-only rollback can orphan private vault files; preserve the private vault until reconciliation is complete.
