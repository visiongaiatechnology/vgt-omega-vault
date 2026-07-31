# VGT OMEGA VAULT 7.0.0 — Hardened Replacement

**Status: PLATIN**

VGT OMEGA VAULT 7 is a security-first replacement for the 6.x plugin. It keeps the existing WordPress tables and shortcodes, migrates the legacy master key out of `wp-content/uploads`, can decrypt existing 6.x ciphertext, and replaces public WordPress file uploads with an encrypted private vault outside the web root.

## Security properties

- Server-side form schemas are authoritative.
- Every submitted POST field is checked against an explicit allowlist.
- Every `$_FILES` key must map to a configured field whose type is exactly `file`.
- Unknown file fields are rejected before Sentinel, the internal scanner, or storage.
- File uploads are globally disabled by default.
- Authorized uploads pass through the standard `wp_handle_upload_prefilter` hook so VGT Sentinel Airlock and other WordPress upload gates remain active.
- Actual temporary-file size is read with `filesize()`; browser-declared file size and MIME metadata are ignored.
- Images are MIME/type cross-checked, decoded through GD, dimension-checked, memory-preflighted, and re-encoded.
- Files are encrypted with AES-256-GCM and written with random opaque IDs, path-jail checks, atomic rename, `0700` directories, and `0600` files.
- The key and file vault are outside `ABSPATH` and `WP_CONTENT_DIR`.
- Anonymous submission authorization uses short-lived, IP-bound, database-backed one-time tokens plus WordPress nonces and same-origin checks.
- Rate-limit increments are atomic in the database.
- Administrative operations require the dedicated `manage_vgt_omega_vault` capability, a nonce, and same-origin validation.
- Submission ciphertext includes authenticated context binding and a keyed integrity digest.
- Security and storage exceptions are logged with opaque error IDs; internal details are not returned to clients.
- Retention cleanup removes database records and corresponding encrypted files.
- No CDN, PHP session, public upload URL, or executable file is used.

This is **server-side encrypted storage**, not end-to-end encryption. The web server receives plaintext over TLS before encrypting it.

## Requirements

- WordPress 6.4 or newer
- PHP 8.1 or newer
- OpenSSL with AES-256-GCM
- Fileinfo
- Mbstring
- GD when image uploads are enabled
- InnoDB
- A writable private directory outside the web root
- HTTPS in production

Activation fails closed when required primitives or private storage boundaries are unavailable.

## Installation

1. Back up the database, plugin directory, and the legacy key file:

   `wp-content/uploads/vgt_keys/.vgt_core_secret.php`

2. Create a private parent directory owned by the PHP-FPM user. Do not place it under the website document root.

3. Prefer explicit paths in `wp-config.php`:

```php
// STATUS: PLATIN
define('VGT_OMEGA_STORAGE_ROOT', '/srv/vgt-private/omega');
define('VGT_OMEGA_KEY_FILE', '/srv/vgt-private/omega/keys/master.key');
```

The parent `/srv/vgt-private` must already exist and must not be writable by unrelated system users.

4. Install the ZIP as a replacement for the old plugin and activate it in staging first.

5. Confirm:

```bash
wp plugin status vgt-omega-vault
wp eval 'VGT_Omega_Crypto::verify_vault_integrity(); echo "vault-ok\n";'
php wp-content/plugins/vgt-omega-vault/tests/security-gate.php
```

6. Submit a text-only test form and verify it appears in **VGT Vault → Submissions**.

7. Repeat the rogue multipart PoC. The expected response is HTTP 403 with the generic message:

   `Request rejected for security reasons.`

8. Enable uploads only after an explicit `file` field has been added to a form and Sentinel Airlock is active.

## Default storage behavior

When no constants are supplied, the plugin uses:

```text
dirname(ABSPATH)/.vgt-omega/
├── .vgt-omega-root
├── keys/
│   └── master.key
└── files/
    └── <two-character shard>/
        └── <48-character random ID>.vgt
```

The default deliberately fails if the directory cannot be created securely or resolves inside the web root.

## Upload configuration

Uploads require all three conditions:

1. **VGT Vault → Settings → Enable encrypted file uploads** is enabled.
2. The form schema contains a field with `"type": "file"`.
3. The field declares MIME types permitted by the global plugin policy.

Default global MIME policy:

- `image/jpeg`
- `image/png`
- `image/webp`

Optional types must be enabled in `wp-config.php`:

```php
// STATUS: PLATIN
define('VGT_OMEGA_ALLOW_PDF_UPLOADS', true);
define('VGT_OMEGA_ALLOW_TEXT_UPLOADS', true);
```

PDF intake is conservative signature screening, not a substitute for a sandboxed content-disarm-and-reconstruction service. Keep PDFs disabled unless operationally necessary.

Example file field:

```json
{
  "id": "evidence_file",
  "type": "file",
  "label": "Evidence image",
  "required": false,
  "allowed_mimes": ["image/jpeg", "image/png", "image/webp"],
  "max_bytes": 2097152
}
```

## VGT Sentinel integration

The hardened pipeline is:

```text
Request WAF
→ same-origin and one-time-token gate
→ server-side field allowlist
→ schema-bound upload authorization
→ wp_handle_upload_prefilter (Sentinel Airlock)
→ internal MIME/image scanner
→ AES-GCM private vault
→ encrypted database payload
```

Sentinel remains defense-in-depth. Omega Vault itself owns form-field authorization and private storage.

## Proxy configuration

Proxy headers are ignored by default. To trust a reverse proxy:

```php
// STATUS: PLATIN
define('VGT_OMEGA_TRUSTED_PROXY_CIDRS', [
    '10.20.0.0/16',
    '2001:db8:1234::/48',
]);
```

Then enable proxy support in the plugin settings. Do not use broad internet CIDRs. The socket IP must fall inside the configured trusted ranges or the request fails closed.

## Migration behavior

Activation performs these safe migrations:

- Creates or upgrades the database schema.
- Copies the legacy 6.x key into the external private key file.
- Validates the new key file and permissions.
- Re-encrypts legacy audit and submission ciphertext transactionally into the v3 envelope.
- Keeps the migrated data independent of later WordPress authentication-salt or domain changes.
- Removes the legacy PHP key file from `wp-content/uploads` only after every migration step succeeds.
- Replaces the inaccurate default phrase “End-to-End Encrypted Tunnel” in stored form configuration.

Activation does **not** automatically delete or relocate files already stored as public WordPress upload URLs. Those files require a deliberate inventory and migration because deleting an unknown referenced file automatically could destroy unrelated media. Search existing decrypted submissions for payloads whose type is `file_upload`, move validated files into controlled storage, then remove the public originals and invalidate caches.

The visual drag-and-drop builder is intentionally replaced by a strict JSON configuration editor. This removes a large dynamic client-side attack surface and makes the server schema canonical.

## Uninstall behavior

Normal uninstall retains encrypted data and the master key.

Destructive purge occurs only when this explicit constant is set before uninstalling:

```php
// STATUS: PLATIN
define('VGT_OMEGA_PURGE_ON_UNINSTALL', true);
```

The uninstaller requires the private vault marker, rechecks the path jail, refuses paths inside the web root, removes database tables/options/capabilities, and clears the retention task.

## Verification

Run:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/js/frontend.js
php tests/security-gate.php
```

The security gate tests:

- AES-GCM round trip and AAD mismatch rejection
- restrictive key permissions
- encrypted private-file round trip
- absence of plaintext in the stored file container
- strict schema rejection
- unknown upload-field rejection before scanning
- Sentinel prefilter integration
- forbidden weak patterns and public upload calls

## Operational hardening outside the plugin

The plugin cannot safely own the entire site's TLS, CSP, PHP-FPM, database, backup, or operating-system policy. For the strongest deployment:

- Put TLS/HSTS and a tested nonce/hash-based CSP at the reverse proxy.
- Disable PHP execution in every writable web directory.
- Use a dedicated least-privilege database account.
- Keep database backups and the master key in separately controlled systems.
- Encrypt backups independently.
- Restrict the private vault to the PHP-FPM service account.
- Send logs to an append-only remote collector.
- Monitor free space, inode exhaustion, token-table growth, and repeated opaque error IDs.
- Stage and verify every WordPress, Sentinel, PHP, and web-server update.

## License

AGPL-3.0-or-later. Retain the repository's original license and copyright notices.
