# Release Notes — 7.0.0

**Status: PLATIN**

## Breaking security changes

- PHP 8.1 and WordPress 6.4 are now required.
- Private storage outside the web root is mandatory.
- File uploads are disabled by default.
- Only image MIME types are enabled by default.
- Unknown POST and file fields fail closed.
- The drag-and-drop builder is replaced by a strict JSON editor.
- Anonymous requests use one-time server-side tokens.
- Public WordPress upload storage is removed.
- The inaccurate end-to-end encryption label is removed.
- Administrative access uses `manage_vgt_omega_vault`.

## Compatibility

- Existing form and submission tables are retained.
- Existing 6.x audit and submission ciphertext is transactionally re-encrypted into the stable v3 envelope using the migrated legacy key.
- Existing shortcodes remain registered.
- Existing public file uploads require a separate controlled migration.
- The legacy stateless-token API is intentionally retired.
