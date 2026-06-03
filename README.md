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
[![Version](https://img.shields.io/badge/Version-5.2.0-brightgreen?style=for-the-badge)](#)
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

## 📋 Changelog — V5.2.0

> **V5.2.0 delivers four structural security upgrades.** No cosmetic changes — every item closes a concrete attack surface or eliminates a failure mode.

| Feature | What Changed |
|---|---|
| **Dual-Defense CSRF-Shield** | Stateless rotating token added alongside nonce — forms remain CSRF-immune even on cached pages |
| **Live Decrypt-and-Auto-Upgrade Engine** | Three-tier key fallback with in-place re-encryption — zero-downtime key migration |
| **IP-Spoofing & Header-Injection Protection** | Hardened proxy evaluator — manipulated `X-Forwarded-For` and similar headers detected and blocked |
| **Lückenloses Escaping & Secure Pagination** | Context-specific output escaping across all admin output + paginated Vault dashboard in Platinum design |

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

## 🏛️ Architecture — The Four Kernels

```
┌─────────────────────────────────────────────────────────┐
│                   VGT OMEGA PROTOCOL                     │
├──────────────┬──────────────┬──────────────┬────────────┤
│   CRYPTO     │      DB      │     API      │  FRONTEND  │
│   KERNEL     │    KERNEL    │    KERNEL    │   KERNEL   │
│              │              │              │            │
│ AES-256-GCM  │  Abstracted  │  Dual CSRF   │  Shortcode │
│ GCM Auth Tag │  Pagination  │  Rate Limit  │  Generator │
│ Random IV    │  Encrypted   │  Honeypot    │  Gold UI   │
│ Auto-Upgrade │  Storage     │  IP Hardened │  AJAX      │
│ 3-Tier Keys  │  Platinum UI │  Inj. Guard  │            │
└──────────────┴──────────────┴──────────────┴────────────┘
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

### IP-Spoofing & Header-Injection Protection *(New in V5.2.0)*

The previous IP resolution read `HTTP_X_FORWARDED_FOR` naively — trivially spoofable. V5.2.0 replaces this with a **hardened proxy evaluator**:

```
Evaluation Chain:
  1. Is request arriving from a known Cloudflare CIDR? → read CF-Connecting-IP
  2. Is request arriving from a trusted reverse proxy?  → read X-Real-IP
  3. Fallback: REMOTE_ADDR (direct connection)

Header-Injection Guard:
  Multi-IP values in X-Forwarded-For → first valid IP extracted
  Non-IP values injected into headers → blocked, REMOTE_ADDR used
  Header value exceeding 45 chars     → blocked immediately
```

Spoofed `X-Forwarded-For` values no longer affect rate limiting or IP logging.

---

## 🗄️ Database Kernel (`VGT_Omega_DB`)

```
Stored in DB:               What attackers see:
  domain    → Ciphertext      K7mX9pQr2nZwAb...
  email     → Ciphertext      Lp4vN8kJhFmD3...
  vector    → Ciphertext      Wq6tR1uYcEiOx...
  threat    → Ciphertext      Bs5aG0ePzHlVn...
  ip_origin → Ciphertext      Tx2jM7yKdCfUw...
```

Even with full database access, all data remains **cryptographically worthless.**

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
├── vgt-omega-vault.php      ← main plugin file
│
├── Kernels (inline):
│   ├── VGT_Omega_Crypto     ← AES-256-GCM + Auto-Upgrade Engine (3-tier)
│   ├── VGT_Omega_DB         ← database abstraction + paginated reads
│   ├── VGT_Omega_API        ← Dual CSRF + IP hardening + 11-layer defense
│   ├── VGT_Omega_Frontend   ← shortcode + CSRF token injection
│   ├── VGT_Omega_UI         ← admin vault dashboard + Platinum pagination
│   └── VGT_Omega_Bootstrap  ← system initialization
│
└── Auto-generated:
    └── wp-content/uploads/vgt_keys/
        ├── .htaccess            ← direct access blocked
        ├── index.php            ← zero-space guard
        └── .vgt_core_secret.php ← AES key (chmod 0600)
```

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
```

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

```
All data encrypted. Zero unencrypted disk state.
Decryption occurs on-the-fly directly in RAM.
```

</div>
