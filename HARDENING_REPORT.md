# VGT OMEGA VAULT — Security Hardening Report

**Target:** 6.0.0 `main` branch  
**Replacement:** 7.0.0 hardened package  
**Classification:** PLATIN  
**Review scope:** request handling, forms, uploads, cryptography, key custody, database, administration, frontend rendering, deletion, retention, and Sentinel integration.

## Findings before hardening

### CRITICAL

None confirmed as unauthenticated remote-code execution.

### HIGH

1. **Upload authorization was not bound to the server-side form schema.**  
   All `$_FILES` entries were processed before validating whether their field names existed as configured `file` fields.

2. **Accepted files were delegated to public WordPress upload storage.**  
   The encrypted submission contained a URL, while the physical file remained outside the encrypted database payload.

3. **The master key was stored below `wp-content/uploads`.**  
   Protection depended partly on web-server-specific access-control files.

4. **Anonymous request tokens were reusable within a time bucket.**  
   The token was not server-side stateful or consumed atomically.

5. **The key generator contained a weak entropy fallback.**  
   Failure of `random_bytes()` fell back to `uniqid()`-derived material instead of failing closed.

### MEDIUM

1. A global PHP error handler changed behavior for WordPress core and unrelated plugins.
2. Form JSON was decoded but not enforced as a strict server-side schema.
3. Rate limiting used non-atomic transient read/modify/write operations.
4. Proxy trust and client-IP attribution were not based on an operator-defined CIDR boundary.
5. Administrative access used a broad generic capability.
6. File validation relied on upload handling that did not provide encrypted private storage.
7. Existing UI language claimed end-to-end encryption although encryption occurred on the server.
8. Error-to-HTTP-status mapping depended on matching human-readable message text.
9. Deletion did not provide a unified lifecycle for encrypted database records and physical uploaded files.
10. Global response headers were emitted directly instead of being integrated through WordPress response handling.

## Implemented controls

### Request boundary

- POST-only enforcement
- Content-Type allowlist
- declared and measured body-size boundaries
- field-count limit
- same-origin verification using Origin/Referer and Fetch Metadata
- WordPress nonce
- random 256-bit one-time request token
- token hash stored server-side
- token bound to form and validated client identity
- atomic single-use token consumption
- atomic database rate-limit bucket
- opaque error identifiers

### Form boundary

- strict top-level and settings-property allowlists
- strict field-property allowlist
- unique field IDs
- reserved control-name rejection
- fixed field-type enumeration
- field-count and configuration-size limits
- strict select/radio option membership
- numeric finite-range checks
- UTF-8 and control-character validation
- same-origin HTTPS-only media URLs

### Upload boundary

- uploads disabled by default
- unknown `$_FILES` keys rejected before scanning
- file fields required to exist in the current server-side schema
- one file per field; nested upload arrays rejected
- standard WordPress upload-prefilter invocation retained for Sentinel Airlock
- unchanged temporary-file identity required after external prefilters
- `is_uploaded_file()` verification
- actual `filesize()` boundary
- Fileinfo MIME detection
- MIME-to-extension mapping
- image `IMAGETYPE_*` cross-check
- maximum dimensions and decoded-pixel budget
- GD decode/re-encode
- restrictive optional PDF/text routes
- SHA-256 content digest
- temporary-file cleanup on all paths

### Storage boundary

- no `wp_handle_upload()` call
- no public file URL
- vault outside `ABSPATH` and `WP_CONTENT_DIR`
- path-jail checks
- random 192-bit file identifiers
- two-character directory sharding
- AES-256-GCM authenticated file containers
- per-file HKDF key derivation
- context-bound AAD
- atomic writes
- `0700` directories
- `0600` files
- capability- and nonce-protected downloads
- download CSP sandbox and `nosniff`
- lifecycle cleanup on submission failure, deletion, and retention purge

### Cryptographic boundary

- `random_bytes()` only; entropy failure is fatal
- AES-256-GCM
- 96-bit random IVs
- 128-bit GCM tags
- HKDF-separated data, file, integrity, token, and rate-limit keys
- authenticated key/context/form binding
- versioned envelope with key identifier
- v7 key derivation is independent of mutable WordPress authentication salts and site-domain changes
- constant-time integrity comparisons
- external key path
- restrictive permission validation
- safe parsing of the legacy key file without executing it
- transactional legacy-ciphertext migration into the stable v3 envelope

### Administration and presentation

- dedicated capability
- nonce and same-origin checks
- strict JSON form editor
- all payload output escaped
- no user data in `innerHTML`
- no external CDN
- paginated submission access
- notifications contain metadata only, never submission content
- configurable retention
- explicit destructive-uninstall gate

## Residual risks

1. **Server compromise:** This remains server-side encryption. A compromised WordPress/PHP process can observe plaintext during submission or authorized decryption.
2. **Site-wide CSP:** A plugin cannot safely impose a strict nonce-based CSP over an unknown theme/plugin stack without compatibility testing. Enforce CSP at the reverse proxy after inventorying all scripts.
3. **Key management:** The default is a protected filesystem key, not an HSM/KMS. High-assurance deployments should inject the key from a dedicated secret system.
4. **Legacy public files:** Existing public files are not automatically deleted or migrated.
5. **Malware classification:** Internal scanners and Sentinel reduce risk but are not a full sandbox/CDR pipeline.
6. **Availability:** Distributed denial of service must also be handled at the CDN/reverse proxy/network layer.
7. **Live integration:** Static checks and isolated security tests do not replace staging tests against the exact WordPress, database, PHP-FPM, Sentinel, web-server, and filesystem deployment.

## Release gate

Do not deploy directly over production. Required sequence:

1. Offline backup and restore test.
2. Staging activation.
3. Key migration verification.
4. Existing-ciphertext read test.
5. Text-only submission test.
6. Unauthorized multipart-field regression test.
7. Authorized image test with Airlock enabled.
8. File download and deletion test.
9. Retention-job test.
10. Load and concurrency test.
11. Production rollout with immediate rollback package available.
