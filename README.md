<div align="center">

```
 ██╗   ██╗ ██████╗ ████████╗    ██████╗ ███╗   ███╗███████╗ ██████╗  █████╗     ██╗   ██╗ █████╗ ██╗   ██╗██╗  ████████╗
 ██║   ██║██╔════╝ ╚══██╔══╝   ██╔═══██╗████╗ ████║██╔════╝██╔════╝ ██╔══██╗   ██║   ██║██╔══██╗██║   ██║██║  ╚══██╔══╝
 ██║   ██║██║  ███╗   ██║      ██║   ██║██╔████╔██║█████╗  ██║  ███╗███████║   ██║   ██║███████║██║   ██║██║     ██║
 ╚██╗ ██╔╝██║   ██║   ██║      ██║   ██║██║╚██╔╝██║██╔══╝  ██║   ██║██╔══██║   ╚██╗ ██╔╝██╔══██║██║   ██║██║     ██║
  ╚████╔╝ ╚██████╔╝   ██║      ╚██████╔╝██║ ╚═╝ ██║███████╗╚██████╔╝██║  ██║    ╚████╔╝ ██║  ██║╚██████╔╝███████╗██║
   ╚═══╝   ╚═════╝    ╚═╝       ╚═════╝ ╚═╝     ╚═╝╚══════╝ ╚═════╝ ╚═╝  ╚═╝     ╚═══╝  ╚═╝  ╚═╝ ╚═════╝ ╚══════╝╚═╝
```

# VGT OMEGA VAULT
### Cryptographic Data Vault & Secure Com-Link Endpoint for WordPress

[![License](https://img.shields.io/badge/License-AGPLv3-green?style=for-the-badge)](LICENSE)
[![Version](https://img.shields.io/badge/Version-5.3.0-brightgreen?style=for-the-badge)](#)
[![PHP](https://img.shields.io/badge/PHP-8.0+-blue?style=for-the-badge&logo=php)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-21759B?style=for-the-badge&logo=wordpress)](https://wordpress.org)
[![Encryption](https://img.shields.io/badge/Encryption-AES--256--GCM-gold?style=for-the-badge)](#)
[![Status](https://img.shields.io/badge/Status-DIAMANT_VGT_SUPREME-purple?style=for-the-badge)](#)

**OMEGA PROTOCOL ACTIVE · ZERO DISK STATE · ENCRYPTED TRANSMISSION**

</div>

---

## ⚠️ DISCLAIMER: EXPERIMENTAL R&D PROJECT

This project is a **Proof of Concept (PoC)** WordPress Security Layer. It is **not** an enterprise plugin and can be unsafe in misconfigured environments.

**Do not use this in critical production environments.** For enterprise-grade kernel-level protection, we recommend established solutions.

Found a vulnerability or have an improvement? **Open an issue or contact us.**

---

## 📋 Changelog — V5.3.0

> **V5.3.0 is an architectural overhaul.** Monolith decomposed into isolated kernel modules, dual-vector IP forensics at database level, and automated regression tests.

| Area | V5.2.1 | V5.3.0 |
|---|---|---|
| **Architecture** | Monolithic single-file plugin | Modular `includes/` kernel directory — strict separation of concerns |
| **IP Storage** | Single `ip_origin` column — socket and claimed IP merged | `ip_socket` (REMOTE_ADDR, unforgeable) + `ip_claimed` (header-submitted) — physically separated |
| **Proxy Trust Model** | Proxy headers evaluated by default | Zero-Trust default — proxy header evaluation requires explicit admin opt-in (`vgt_omega_allow_proxies`) |
| **Test Coverage** | No automated tests — manual click-through only | `phpunit1.php` standalone regression suite — no WordPress core required |

---



## 🔐 What is VGT Omega Vault?

The WordPress ecosystem has **58,000+ form plugins.**
Not a single one encrypts data before writing it to the database.

**VGT Omega Vault closes this gap.**

A cryptographic data vault that **immediately encrypts every incoming record with AES-256-GCM** before it ever touches the database. Plaintext exists exclusively in RAM — for milliseconds — and nowhere else.

Built for **law firms, medical practices, tax advisors, and anyone receiving confidential inquiries through WordPress** while maintaining full GDPR compliance.

---

## ⚡ The Problem With Conventional Form Plugins

```
WPForms, Gravity Forms, Ninja Forms:
  Data received            → plaintext stored in DB
  DB dump by attacker      → all data compromised
  GDPR obligation          → encryption missing

VGT Omega Vault:
  Data received            → immediately AES-256-GCM encrypted
  DB dump by attacker      → ciphertext only → worthless
  GDPR obligation          → fulfilled by design
```

---

## 🏛️ Architecture — The Four Kernels *(Modularized V5.3.0)*

```
┌─────────────────────────────────────────────────────────┐
│                   VGT OMEGA PROTOCOL                     │
├──────────────┬──────────────┬──────────────┬────────────┤
│   CRYPTO     │      DB      │     API      │  FRONTEND  │
│   KERNEL     │    KERNEL    │    KERNEL    │   KERNEL   │
│              │              │              │            │
│ AES-256-GCM  │  Abstracted  │  Dual CSRF   │  Shortcode │
│ GCM Auth Tag │  Pagination  │  Rate Limit  │  Generator │
│ Random IV    │  Dual-Vector │  Honeypot    │  Gold UI   │
│ Auto-Upgrade │  IP Storage  │  Zero-Trust  │  AJAX      │
│ 3-Tier Keys  │  Platinum UI │  IP Profiler │            │
└──────────────┴──────────────┴──────────────┴────────────┘

V5.3.0 Module Layout (includes/):
  VGT_Omega_Crypto    ← AES-256-GCM + key validity — isolated
  VGT_Omega_DB        ← pure data abstraction layer
  VGT_Omega_API       ← firewall + validation pipeline + IP profiler
  VGT_Omega_Frontend  ← client-side rendering engine
  VGT_Omega_UI        ← admin-side rendering engine (strictly separated)
```

---

## 🔑 Crypto Kernel (`VGT_Omega_Crypto`)

The cryptographic core. Every data element is encrypted with **AES-256-GCM** — the same standard used for TOP SECRET data classification.

```php
// Encryption: Data → Ciphertext (stored in DB)
VGT_Omega_Crypto::encrypt($sensitive_data);

// Decryption: Ciphertext → Plaintext (RAM only)
// V5.2.0: Auto-Upgrade Engine applied transparently on read
VGT_Omega_Crypto::decrypt($ciphertext);
```

**Key Management:**
- 512-bit entropy during key generation (`random_bytes(64)`)
- Key file: `wp-content/uploads/vgt_keys/.vgt_core_secret.php`
- Direct access blocked via `.htaccess` + PHP exit guard
- File permissions: `chmod 0600`

---

## 🔄 Live Decrypt-and-Auto-Upgrade Engine *(New in V5.2.0)*

Key rotations and encryption upgrades no longer require downtime or manual data migration. The engine resolves every decryption request through a three-tier fallback cascade — and re-encrypts stale records transparently on the way out.

```
Decryption Request Received
         ↓
Tier 1: Supreme Key + Domain Lock
  → Success: record re-encrypted with current key → DB write → return plaintext
  → Fail: proceed to Tier 2
         ↓
Tier 2: Supreme Key (no Domain Lock)
  → Success: record re-encrypted with current key → DB write → return plaintext
  → Fail: proceed to Tier 3
         ↓
Tier 3: Legacy Key
  → Success: record re-encrypted with current key → DB write → return plaintext
  → Fail: decryption error — record flagged
```

**What this means in practice:**
- Migrate from old encryption key to new one — zero downtime, zero manual steps
- Records self-upgrade on first access — no batch migration scripts
- Domain migration (key domain lock changes) — handled transparently
- Re-encryption happens in RAM — ciphertext in DB is always current-generation after read

---

## 🛡️ API Kernel (`VGT_Omega_API`)

Multi-layered defense for every incoming request — **V5.2.0 adds Dual-Defense CSRF and hardened IP resolution.**

```
Layer 1:  Method Guard              → POST only
Layer 2a: CSRF — WP Nonce           → wp_verify_nonce() (session-bound)
Layer 2b: CSRF — Rotating Token     → stateless, hour+salt bound (cache-immune)
Layer 3:  Rate Limiting             → 60s cooldown per IP
Layer 4:  Honeypot Detection        → bot trap field
Layer 5:  IP Validation             → hardened proxy evaluator (see below)
Layer 6:  Email Validation          → Regex + is_email()
Layer 7:  Domain Validation         → Regex pattern
Layer 8:  Vector Validation         → whitelist pattern
Layer 9:  Injection Guard           → [<>{}\[\]\=] blocked
Layer 10: AES-256-GCM               → encryption
Layer 11: DB Write                  → ciphertext only
```

### Dual-Defense CSRF-Shield *(New in V5.2.0)*

Standard WordPress nonces fail silently on cached pages — the nonce is baked into the page at cache time and expires before the user submits the form. VGT Omega Vault now operates a **second, independent CSRF layer** that does not rely on session state:

```
Standard Nonce:
  Generated at page render → expires after 12-24h
  Cached page served 3h later → nonce still in HTML → ✅ valid
  Cached page served 25h later → nonce expired → ❌ CSRF false-positive

Rotating Stateless Token (V5.2.0):
  Token = HMAC(current_hour + site_salt)
  Valid: current hour + previous hour window
  No session required → cache-immune
  No replay window beyond 2 hours
```

Both layers must pass independently. Bypassing one does not bypass the other.

### IP-Spoofing & Zero-Trust Proxy Protocol *(V5.2.0 → V5.2.1 → V5.3.0)*

V5.2.0 introduced a hardened proxy evaluator. V5.2.1 added Cloudflare CIDR validation. **V5.3.0 changes the default trust model entirely** — proxy header evaluation is now opt-in, not opt-out:

```
V5.3.0 Zero-Trust Default (vgt_omega_allow_proxies = false):
  → ALL proxy headers ignored (X-Forwarded-For, CF-Connecting-IP, X-Real-IP)
  → ip_socket = REMOTE_ADDR always
  → ip_claimed = empty
  → Rate limiting and IP logging always use the real TCP socket
  → No spoofing vector exists — there is no header to manipulate

V5.3.0 Proxy Opt-In (vgt_omega_allow_proxies = true):
  → Admin explicitly enables proxy header evaluation
  → Cloudflare CIDR validation active (V5.2.1 logic retained)
  → Private ranges filtered via FILTER_FLAG_NO_PRIV_RANGE
  → ip_claimed populated from validated header value
  → ip_socket always retained as ground truth
```

```
Evaluation Chain (Opt-In mode):
  1. Is REMOTE_ADDR in Cloudflare IPv4/IPv6 CIDR list?
     → YES: read CF-Connecting-IP → ip_claimed
     → NO:  CF-Connecting-IP ignored
  2. Trusted reverse proxy? → read X-Real-IP → ip_claimed
  3. Fallback: ip_claimed = empty, ip_socket used for all decisions

Header-Injection Guard (active in both modes):
  Multi-IP X-Forwarded-For → first valid IP extracted
  Private ranges → filtered (FILTER_FLAG_NO_PRIV_RANGE | NO_RES_RANGE)
  Non-IP header values → blocked, REMOTE_ADDR used
  Header > 45 chars → blocked immediately
```

---

## 🗄️ Database Kernel (`VGT_Omega_DB`)

```
Stored in DB:               What attackers see:
  domain    → Ciphertext      K7mX9pQr2nZwAb...
  email     → Ciphertext      Lp4vN8kJhFmD3...
  vector    → Ciphertext      Wq6tR1uYcEiOx...
  threat    → Ciphertext      Bs5aG0ePzHlVn...
  ip_socket → Ciphertext      Tx2jM7yKdCfUw...   ← REMOTE_ADDR (unforgeable)
  ip_claimed→ Ciphertext      Rx9nP2qVsHlKe...   ← header-submitted IP (V5.3.0)
```

Even with full database access, all data remains **cryptographically worthless.**

**V5.3.0 — Dual-Vector IP Forensics:**

V5.2.1 stored a single `ip_origin` column that merged socket and claimed IP into one value — making it impossible post-write to distinguish whether a stored IP was the real TCP connection or a spoofed header value.

V5.3.0 separates them physically:

```
ip_socket  = REMOTE_ADDR
             → The actual TCP connection endpoint
             → Unforgeable at network level
             → Always written, regardless of proxy settings

ip_claimed = X-Forwarded-For / CF-Connecting-IP (after CIDR validation)
             → What the client claims to be
             → Only populated when vgt_omega_allow_proxies = true
             → Empty in Zero-Trust default mode
```

This enables forensic reconstruction: even after an attack, the database distinguishes between "what IP connected" and "what IP was claimed."

**V5.2.1 — Column Type Optimization (retained):**
`domain`, `email`, `vector`, and both IP columns use `varchar(...)` — full MySQL index support, InnoDB buffer pool resident. The encrypted payload column `threat` remains `text`.

---

## 🎨 Frontend & Admin Kernel

**Frontend — Shortcode Deployment:**
```
[vgt_omega_comlink]
```
Single shortcode deploys the complete encrypted form — Gold/Dark design, loading states, AJAX transmission, Dual-Defense CSRF tokens injected automatically.

**Admin Vault Dashboard — V5.2.0 Upgrades:**
- **Secure Pagination:** Platinum-design paginated navigation — no full table loads on large datasets
- **Lückenloses Escaping:** All output via context-specific escaping: `esc_html()` for text, `esc_url()` for links, `esc_attr()` for attributes — no raw variable output anywhere
- **On-the-fly Decryption:** Auto-Upgrade Engine fires transparently on record read — dashboard always displays current-generation data

---

## 🔒 Zero Disk State Principle

```
STANDARD PLUGIN:
  User submits form
       ↓
  Plaintext → MySQL Database
       ↓
  Attacker dumps DB → all data compromised ❌

VGT OMEGA VAULT:
  User submits form
       ↓
  RAM: Validation + Dual CSRF + IP Verification + Encryption (milliseconds)
       ↓
  Ciphertext → MySQL Database
       ↓
  Attacker dumps DB → ciphertext only → worthless ✅
       ↓
  Admin opens Vault → Auto-Upgrade Engine fires → decryption in RAM
       ↓
  Plaintext never leaves memory
```

---

## 📊 Security Features

| Feature | Standard Plugin | VGT Omega Vault |
|---|---|---|
| Database encryption | ❌ | ✅ AES-256-GCM |
| Zero Disk State | ❌ | ✅ RAM-only decryption |
| GCM Authentication Tag | ❌ | ✅ Tamper detection |
| CSRF — WP Nonce | partial | ✅ `wp_verify_nonce` |
| CSRF — Cache-immune rotating token | ❌ | ✅ Stateless HMAC (V5.2.0) |
| IP Spoofing protection | ❌ | ✅ Hardened proxy evaluator (V5.2.0) |
| Rate Limiting | ❌ | ✅ 60s cooldown per real IP |
| Honeypot Bot Detection | ❌ | ✅ |
| Injection Guard | partial | ✅ 11 layers |
| Key migration — zero downtime | ❌ | ✅ Auto-Upgrade Engine (V5.2.0) |
| Context-specific output escaping | ❌ | ✅ `esc_html` / `esc_url` / `esc_attr` (V5.2.0) |
| Key file protection | ❌ | ✅ `.htaccess` + `chmod 0600` |
| Paginated Admin Vault | ❌ | ✅ Platinum design (V5.2.0) |
| GDPR compliant by design | ❌ | ✅ |

---

## 🚀 Installation

### Requirements

```
PHP:        8.0+
WordPress:  6.0+
OpenSSL:    enabled (standard on every hosting)
```

### Setup

**1. Install plugin:**
```
WordPress Admin → Plugins → Upload Plugin → Select ZIP → Install
```

**2. Activate plugin:**
```
Database table created automatically.
Cryptographic key generated automatically.
Dual-Defense CSRF tokens initialized automatically.
```

**3. Deploy form:**
```
[vgt_omega_comlink]
```

**4. Open vault:**
```
WordPress Admin → VGT Vault
Records decrypted on-the-fly. Auto-Upgrade Engine fires transparently.
```

---

## 🎯 Who Is This For?

```
⚖️  Law Firms            → client inquiries encrypted
🏥  Medical Practices    → patient requests GDPR-compliant
📊  Tax Advisors         → client data secured
🏛️  Notaries             → confidential requests protected
🔐  Security Teams       → vulnerability disclosure forms
🏢  Enterprises          → any confidential inquiry workflow
```

---

## 🆚 Market Comparison

```
Gravity Forms  ($259/year):  No encryption. Plaintext in DB.
WPForms Pro    ($199/year):  No encryption. Plaintext in DB.
Ninja Forms    ($99/year):   No encryption. Plaintext in DB.
Formidable     ($199/year):  No encryption. Plaintext in DB.

VGT Omega Vault (free):      AES-256-GCM. Zero Disk State.
                              Dual-Defense CSRF. Cache-immune.
                              GDPR-compliant by design.
```

---

## 📁 File Structure

```
vgt-omega-vault/
├── vgt-omega-vault.php          ← bootstrapper + lifecycle hooks
│
├── includes/                    ← modular kernel directory (V5.3.0)
│   ├── class-vgt-omega-crypto.php   ← AES-256-GCM + 3-tier key engine
│   ├── class-vgt-omega-db.php       ← data abstraction + dual-vector IP + pagination
│   ├── class-vgt-omega-api.php      ← firewall + validation pipeline + IP profiler
│   ├── class-vgt-omega-frontend.php ← client-side rendering engine
│   └── class-vgt-omega-ui.php       ← admin rendering engine (strictly separated)
│
├── assets/
│   ├── vgt-omega.js             ← AJAX + CSRF token injection (decoupled)
│   └── vgt-omega.css            ← Platinum/Gold UI styles (decoupled)
│
├── phpunit1.php                 ← standalone regression tests (V5.3.0)
│
└── Auto-generated:
    └── wp-content/uploads/vgt_keys/
        ├── .htaccess                ← direct access blocked
        ├── index.php                ← zero-space guard
        └── .vgt_core_secret.php     ← AES key (chmod 0600)
```

---

## 🧪 Automated Regression Tests *(New in V5.3.0)*

V5.2.1 had no automated tests — changes required manual validation in a full WordPress environment. V5.3.0 ships `phpunit1.php`: a standalone regression suite that tests core IP parsing logic without loading WordPress.

```bash
# Run standalone — no WordPress installation required
php phpunit1.php

# Or via PHPUnit if installed
./vendor/bin/phpunit phpunit1.php
```

**Test coverage includes:**
- IP chain parsing from `X-Forwarded-For` multi-value headers
- Private IPv4 range filtering (`10.x`, `192.168.x`, `172.16.x`)
- Private IPv6 range filtering (`::1`, `fc00::/7`)
- Cloudflare CIDR validation (`is_cloudflare_ip()`)
- Dual-vector socket/claimed IP separation logic
- Edge cases: empty headers, malformed values, oversized strings

Suitable for CI/CD pipeline integration — add to GitHub Actions or any runner without a WordPress environment dependency.

---

## ⚠️ Important Notice

```
MANUALLY MODIFYING THE KEY FILE DESTROYS ALL ENCRYPTED DATA.

The cryptographic key is generated once on activation.
Back it up before any server migration:
  wp-content/uploads/vgt_keys/.vgt_core_secret.php

V5.2.0 Auto-Upgrade Engine:
  Key migration between encryption generations is handled automatically.
  Manual data re-encryption scripts are no longer required.
  Records upgrade to the current key on first read — silently, in RAM.

V5.2.1 .htaccess (Apache 2.4+ compatible):
  The generated .htaccess uses <IfModule mod_authz_core.c> to detect
  the Apache version and apply the correct directive:
    Apache 2.4+:  Require all denied
    Apache 2.2:   Deny from all (legacy fallback)
  No server warnings or permission mismatches on modern hosting environments.
```

---

## 🏆 Acknowledgments

| Contributor | Contribution |
|---|---|
| **[Daniel Ruf](https://github.com/DanielRuf)** | Responsible disclosure of 3 security issues (V5.2.1): CF-Connecting-IP trust bypass, database column type inefficiency, Apache 2.4 .htaccess incompatibility |

Security researchers who responsibly disclose vulnerabilities are credited here. To report a finding, open an issue or use the VGT Comlink.

---

## 🤝 Contributing

Pull requests are welcome. For major changes, please open an issue first.

```bash
git clone https://github.com/VisionGaiaTechnology/vgt-omega-vault
cd vgt-omega-vault
```

---

## ☕ Support the Project

If VGT Omega Vault saved you time, money or nerves — consider supporting:

[![PayPal](https://img.shields.io/badge/PayPal-Donate-00457C?style=for-the-badge&logo=paypal)](https://www.paypal.com/paypalme/dergoldenelotus)

---

## 📄 License

AGPLv3 License · © 2026 VisionGaia Technology · Cologne, Germany

Anyone using and modifying this plugin must publish changes under AGPLv3.

---

<div align="center">

**VISIONGAIATECHNOLOGY – WE ARCHITECT THE FUTURE OF SECURITY.**

[![VGT](https://img.shields.io/badge/VisionGaia-Technology-gold?style=for-the-badge)](https://visiongaiatechnology.de)

*VGT Omega Vault v5.3.0 — Modular Kernel Architecture // Dual-Vector IP Forensics // Zero-Trust Proxy Protocol // AES-256-GCM // Dual-Defense CSRF // Cloudflare CIDR Validation // Automated Regression Tests // GDPR-compliant by design // AGPLv3*
