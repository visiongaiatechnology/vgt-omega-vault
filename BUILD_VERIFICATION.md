# Build verification

<!-- STATUS: PLATIN -->

Build: VGT OMEGA VAULT 7.0.0  
Verification date: 2026-07-31

## Automated gates

- PHP syntax validation for every PHP source file
- JavaScript syntax validation with `node --check`
- Isolated security regression gate in `tests/security-gate.php`
- Forbidden-pattern scan over executable PHP and JavaScript
- SHA-256 manifest verification after a fresh ZIP extraction

## Covered security invariants

- Unknown multipart file fields are rejected before scanning or persistence.
- File size is obtained from the temporary file, never trusted from client metadata.
- MIME and decoded image type are cross-checked.
- Accepted files are re-encoded and encrypted outside the public web root.
- Guest submission tokens are single-use, expiring and server-side persisted.
- Security and storage exception details remain server-side.
- Legacy encrypted records are transactionally migrated before old key removal.
- Form deletion changes state before batched removal to close submission races.

## Scope boundary

These gates do not replace a staging deployment against the exact production
WordPress, PHP-FPM, database, reverse-proxy and filesystem configuration.
See `HARDENING_REPORT.md` and `MIGRATION.md`.
