# Security Policy

## Supported release

Security fixes target the hardened 7.x line. The 6.x architecture should be treated as migration-only.

## Reporting

Report vulnerabilities privately to the project owner. Include:

- affected version and commit
- deployment assumptions
- exact request or code path
- minimal non-destructive reproduction
- expected versus actual behavior
- impact
- logs with secrets and personal data removed

Do not include master keys, decrypted submissions, WordPress authentication cookies, or production personal data.

## Security invariants

A release is rejected when any invariant below fails:

1. `keys($_FILES)` must be a subset of configured fields whose type is `file`.
2. Unknown POST fields must fail before persistence.
3. No accepted file may be written into a publicly served directory.
4. No key path may resolve inside `ABSPATH` or `WP_CONTENT_DIR`.
5. No security exception detail may be returned to the client.
6. No entropy fallback may replace `random_bytes()`.
7. No mutable request token may be reusable.
8. No database query may concatenate unvalidated user-controlled values.
9. No user-controlled value may reach `innerHTML`.
10. Destructive uninstall must require explicit opt-in and a validated path marker.

## Key custody

- Back up the master key separately from the database.
- Restrict it to the PHP-FPM service account.
- Never put it in Git, WordPress options, the media library, a support bundle, or application logs.
- A lost key makes encrypted records unrecoverable.
- A copied key plus database/vault backup permits decryption; protect both independently.
- Test recovery on an isolated host.

## Incident response

On suspected compromise:

1. Block public form submission at the reverse proxy.
2. Preserve immutable logs and filesystem/database snapshots.
3. Rotate WordPress authentication salts and administrative credentials.
4. Treat the current Omega master key as exposed.
5. Inventory database exports, backups, and private vault copies.
6. Rebuild WordPress and plugins from known-good sources.
7. Migrate data to a new key under a controlled re-encryption procedure.
8. Re-enable intake only after a fresh unauthorized-field and file-vault regression test.

Replacing the master key without re-encrypting existing data will make that data unreadable.
