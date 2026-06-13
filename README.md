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
[![Version](https://img.shields.io/badge/Version-6.0.0-brightgreen?style=for-the-badge)](#)
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

## 📋 Changelog — V6.0.0

> **V6.0.0 is a paradigm shift.** The static single-channel protocol logger has been completely rearchitected into a fully configurable, encrypted Drag-and-Drop form and funnel builder. V5.3.0 is now legacy.

### Evolution Matrix: V5.3.0 (Legacy) → V6.0.0 (DIAMANT SUPREME)

#### 1. System Architecture & Data Structure

| Parameter | V5.3.0 (Legacy) | V6.0.0 (Current) |
|---|---|---|
| **Form Mode** | Static (4 predefined fields) | Dynamic (unlimited forms and funnels) |
| **Storage Entity** | Single table (`wp_vgt_omega_audits`) | Three tables (`wp_vgt_omega_audits`, `wp_vgt_omega_forms`, `wp_vgt_omega_submissions`) |
| **Shortcode Interface** | Global shortcode `[vgt_omega_comlink]` | Instanced shortcodes `[vgt_omega_form id="X"]` with backward-compatibility mapping |
| **Field Types** | Hardcoded (Text, Email, Textarea) | Text, Email, Number, Textarea, Select, Radio, File, Headings, Paragraphs, Images, Videos |

#### 2. Architectural Hardening & Cryptography

**Form-Bound AAD Binding:**

V5.3.0 AES-256-GCM encrypted payloads were bound only to domain and type context. V6.0.0 extends the Additional Authenticated Data (AAD) binding to include the `form_id` (`$context | Domain | Form_ID`). Ciphertexts are cryptographically locked to their originating form instance — cross-form transfers or manipulation will fail at GCM tag verification.

**Dual-Defense CSRF Bypass Fix:**

V5.3.0 contained a logical defect (`&&` instead of `||`) that allowed validation to pass if only one of two security tokens was valid. V6.0.0 enforces a strict handshake requiring both tokens to be valid independently — WordPress Nonce and the stateless hourly-rotating HMAC-SHA256 token.

**Volatile RAM Decryption & XSS Hardening:**

V5.3.0 used direct PHP output and weak JS DOM assignment. V6.0.0 decrypts exclusively in the web server's volatile RAM on demand. Admin panel JS output is wrapped in a systemic `escapeHtml()` layer to neutralize script injections from manipulated database fields.

#### 3. Live Builder Engine & Design Control

**Drag-and-Drop Editor:**

V5.3.0 had no administrative configuration UI. V6.0.0 ships a three-panel workspace layout — module palette (left), real-time preview canvas (center), properties and style sidebar (right).

**Inline Editing & CSS Custom Properties:**

V5.3.0 used static styles. V6.0.0 supports direct text editing via `contenteditable="true"` on canvas elements. Design changes — including theme switches like Clean Light or Cyberpunk — render instantly via CSS Custom Properties (`--vgt-radius`, `--vgt-padding`, `--vgt-width`, `--vgt-gold`).

**Element-Level Text Color Selection:**

V5.3.0 had no selective color control. V6.0.0 reads and normalizes font colors from selected canvas elements using an off-screen canvas engine. Values are surfaced in standardized hex format (`#rrggbb`) and are individually configurable per element type: Title, Subtitle, Labels, Headings, and Paragraphs.

#### 4. Funnel Functionality (Multi-Step Flow)

**Funnel Modules (`step_break`):**

V5.3.0 had no multi-step capability — single-page data submission only. V6.0.0 introduces form segmentation into logical steps with a state-driven progress bar, per-step client-side validation, and smooth UI transitions between funnel stages.

---

## 📋 Changelog — V5.3.0 *(Legacy)*

> **V5.3.0 was an architectural overhaul.** Monolith decomposed into isolated kernel modules, dual-vector IP forensics at database level, and automated regression tests.

| Area | V5.2.1 | V5.3.0 |
|---|---|---|
| **Architecture** | Monolithic single-file plugin | Modular `includes/` kernel directory — strict separation of concerns |
| **IP Storage** | Single `ip_origin` column — socket and claimed IP merged | `ip_socket` (REMOTE_ADDR, unforgeable) + `ip_claimed` (header-submitted) — physically separated |
| **Proxy Trust Model** | Proxy headers evaluated by default | Zero-Trust default — proxy header evaluation requires explicit admin opt-in (`vgt_omega_allow_proxies`) |
| **Test Coverage** | No automated tests — manual click-through only | `phpunit1.php` standalone regression suite — no WordPress core required |

---

<img width="2549" height="1160" alt="image" src="https://github.com/user-attachments/assets/a711b39f-4ba3-4829-b496-d64e518a88a6" />



## 🔐 What is VGT Omega Vault?

The WordPress ecosystem has **58,000+ form plugins.**
Not a single one encrypts data before writing it to the database.

**VGT Omega Vault closes this gap.**

A cryptographic data vault and **Drag-and-Drop form builder** that **immediately encrypts every incoming record with AES-256-GCM** before it ever touches the database. Plaintext exists exclusively in RAM — for milliseconds — and nowhere else.

Built for **law firms, medical practices, tax advisors, and anyone receiving confidential inquiries through WordPress** while maintaining full GDPR compliance.


<img width="2536" height="1157" alt="image" src="https://github.com/user-attachments/assets/6ef21e5b-9a4e-46d8-b177-aa6c9dde1fe8" />


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

## 🏛️ Architecture — The Four Kernels *(V6.0.0)*

```
┌──────────────────────────────────────────────────────────────┐
│                    VGT OMEGA PROTOCOL V6                      │
├──────────────┬──────────────┬──────────────┬─────────────────┤
│   CRYPTO     │      DB      │     API      │  FRONTEND       │
│   KERNEL     │    KERNEL    │    KERNEL    │   KERNEL        │
│              │              │              │                 │
│ AES-256-GCM  │  3 Tables    │  Dual CSRF   │  Drag-and-Drop  │
│ Form-Bound   │  Form Store  │  (Fixed)     │  Live Builder   │
│ AAD Binding  │  Submission  │  Rate Limit  │  Multi-Step     │
│ GCM Auth Tag │  Store       │  Honeypot    │  Funnel Engine  │
│ Random IV    │  Dual-Vector │  Zero-Trust  │  CSS Custom     │
│ Auto-Upgrade │  IP Storage  │  IP Profiler │  Properties     │
│ 3-Tier Keys  │  Pagination  │              │  Inline Edit    │
└──────────────┴──────────────┴──────────────┴─────────────────┘

V6.0.0 Module Layout (includes/):
  VGT_Omega_Crypto    ← AES-256-GCM + form-bound AAD + 3-tier key engine
  VGT_Omega_DB        ← 3-table data abstraction + dual-vector IP + pagination
  VGT_Omega_API       ← firewall + fixed dual-CSRF + validation pipeline + IP profiler
  VGT_Omega_Frontend  ← client-side rendering engine + funnel step controller
  VGT_Omega_UI        ← admin rendering engine + drag-and-drop live builder
  VGT_Omega_Builder   ← form/funnel composition engine + CSS custom properties
```

<img width="2546" height="1160" alt="image" src="https://github.com/user-attachments/assets/3f7c962f-5d88-4f6a-9997-bee95b317f2a" />


---

## 🔑 Crypto Kernel (`VGT_Omega_Crypto`)

The cryptographic core. Every data element is encrypted with **AES-256-GCM** — the same standard used for TOP SECRET data classification.

```php
// Encryption: Data → Ciphertext (stored in DB)
// V6.0.0: AAD = $context | Domain | Form_ID
VGT_Omega_Crypto::encrypt($sensitive_data, $context, $form_id);

// Decryption: Ciphertext → Plaintext (RAM only)
// Auto-Upgrade Engine applied transparently on read
VGT_Omega_Crypto::decrypt($ciphertext, $context, $form_id);
```

**Form-Bound AAD Binding (V6.0.0):**

Every ciphertext is cryptographically bound to its originating form instance. A payload encrypted by Form A cannot be decrypted in the context of Form B — GCM tag verification will fail. This eliminates cross-form injection vectors that existed in V5.3.0 where AAD was bound to domain and type only.

**Key Management:**
- 512-bit entropy during key generation (`random_bytes(64)`)
- Key file: `wp-content/uploads/vgt_keys/.vgt_core_secret.php`
- Direct access blocked via `.htaccess` + PHP exit guard
- File permissions: `chmod 0600`

---

## 🔄 Live Decrypt-and-Auto-Upgrade Engine *(V5.2.0+, retained in V6.0.0)*

Key rotations and encryption upgrades require no downtime or manual migration. The engine resolves every decryption request through a three-tier fallback cascade — re-encrypting stale records transparently on read.

```
Decryption Request Received
         ↓
Tier 1: Supreme Key + Domain Lock + Form_ID (V6.0.0)
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

---

## 🛡️ API Kernel (`VGT_Omega_API`)

Multi-layered defense for every incoming request. **V6.0.0 fixes the Dual-Defense CSRF bypass vulnerability present in V5.3.0.**

```
Layer 1:  Method Guard              → POST only
Layer 2a: CSRF — WP Nonce           → wp_verify_nonce() (session-bound)
Layer 2b: CSRF — Rotating Token     → stateless, hour+salt bound (cache-immune)
          ↑ V6.0.0: Both layers now strictly required (|| → && fix)
Layer 3:  Rate Limiting             → 60s cooldown per IP
Layer 4:  Honeypot Detection        → bot trap field
Layer 5:  IP Validation             → hardened proxy evaluator
Layer 6:  Email Validation          → Regex + is_email()
Layer 7:  Domain Validation         → Regex pattern
Layer 8:  Vector Validation         → whitelist pattern
Layer 9:  Injection Guard           → [<>{}\[\]\=] blocked
Layer 10: Form-Bound AAD Binding    → context | domain | form_id (V6.0.0)
Layer 11: AES-256-GCM               → encryption
Layer 12: DB Write                  → ciphertext only
```

### Dual-Defense CSRF-Shield *(Fixed in V6.0.0)*

V5.3.0 contained a logical defect where a `&&` condition meant only one of the two CSRF tokens needed to be valid for the request to proceed. V6.0.0 corrects this: both layers must independently pass.

```
Standard Nonce:
  Generated at page render → expires after 12-24h
  Cache-immune via rotating stateless token as second layer

Rotating Stateless Token:
  Token = HMAC(current_hour + site_salt)
  Valid: current hour + previous hour window
  No session required → cache-immune

V5.3.0 (BROKEN):  if (nonce_valid || token_valid) → accept
V6.0.0 (FIXED):   if (nonce_valid && token_valid) → accept
```

### IP-Spoofing & Zero-Trust Proxy Protocol *(V5.3.0, retained)*

```
Zero-Trust Default (vgt_omega_allow_proxies = false):
  → ALL proxy headers ignored (X-Forwarded-For, CF-Connecting-IP, X-Real-IP)
  → ip_socket = REMOTE_ADDR always
  → ip_claimed = empty

Proxy Opt-In (vgt_omega_allow_proxies = true):
  → Cloudflare CIDR validation active
  → Private ranges filtered via FILTER_FLAG_NO_PRIV_RANGE
  → ip_claimed populated from validated header value
  → ip_socket always retained as ground truth
```

---

## 🗄️ Database Kernel (`VGT_Omega_DB`)

**V6.0.0 — Three-Table Architecture:**

```
wp_vgt_omega_audits      ← legacy audit log (backward-compatible)
wp_vgt_omega_forms       ← form/funnel definitions and builder state
wp_vgt_omega_submissions ← encrypted submission payloads (per form instance)
```

```
Stored in DB:               What attackers see:
  domain    → Ciphertext      K7mX9pQr2nZwAb...
  email     → Ciphertext      Lp4vN8kJhFmD3...
  vector    → Ciphertext      Wq6tR1uYcEiOx...
  threat    → Ciphertext      Bs5aG0ePzHlVn...
  ip_socket → Ciphertext      Tx2jM7yKdCfUw...   ← REMOTE_ADDR (unforgeable)
  ip_claimed→ Ciphertext      Rx9nP2qVsHlKe...   ← header-submitted IP
  form_id   → bound in AAD   (no standalone column — baked into ciphertext)
```

Even with full database access, all submission data remains **cryptographically worthless.**

---

## 🎨 Frontend & Admin Kernel

**Frontend — Shortcode Deployment:**

```
[vgt_omega_form id="X"]         ← V6.0.0 instanced shortcode
[vgt_omega_comlink]              ← legacy alias (backward-compatible)
```

**Live Builder (V6.0.0):**

```
┌─────────────┬─────────────────────────┬─────────────────┐
│   MODULES   │      CANVAS (Live)      │   PROPERTIES    │
│             │                         │                 │
│  Text       │  [Title]                │  --vgt-radius   │
│  Email      │  [Email Field    ]      │  --vgt-padding  │
│  Number     │  [Select ▼       ]      │  --vgt-width    │
│  Textarea   │  ──── step_break ────   │  --vgt-gold     │
│  Select     │  [Next Step →    ]      │                 │
│  Radio      │                         │  Theme:         │
│  File       │  Drag to reorder        │  ○ Clean Light  │
│  Heading    │  Click to edit inline   │  ● Cyberpunk    │
│  Paragraph  │  contenteditable="true" │                 │
│  Image      │                         │  Colors:        │
│  Video      │                         │  Title  #f0c040 │
│  step_break │                         │  Labels #ffffff │
└─────────────┴─────────────────────────┴─────────────────┘
```

**XSS Hardening (V6.0.0):**
All admin panel JS output passes through `escapeHtml()` before DOM insertion. Manipulated database fields cannot inject scripts through the Vault dashboard.

**Admin Vault Dashboard:**
- Secure Pagination — Platinum-design paginated navigation
- On-the-fly Decryption — Auto-Upgrade Engine fires transparently on record read
- Per-form submission view with dual-vector IP forensics

---

## 🔀 Funnel Engine — Multi-Step Forms *(New in V6.0.0)*

```
Single-Page Form (V5.3.0):        Multi-Step Funnel (V6.0.0):
  [Field 1]                          Step 1           Step 2           Step 3
  [Field 2]                          [Field 1]   →    [Field 3]   →    [Field 5]
  [Field 3]                          [Field 2]        [Field 4]        [Submit]
  [Field 4]                          [Next →]         [Next →]
  [Submit]
```

- `step_break` module segments forms into logical steps
- State-driven progress bar reflects current step
- Per-step client-side validation before proceeding
- Smooth UI transitions between funnel stages
- Full AES-256-GCM encryption applied at final submission

---

## 🔒 Zero Disk State Principle

```
STANDARD PLUGIN:
  User submits form
       ↓
  Plaintext → MySQL Database
       ↓
  Attacker dumps DB → all data compromised ❌

VGT OMEGA VAULT V6.0.0:
  User submits form (single-page or multi-step funnel)
       ↓
  RAM: Validation + Fixed Dual CSRF + IP Verification
       + Form-Bound AAD Binding + AES-256-GCM Encryption (milliseconds)
       ↓
  Ciphertext → MySQL Database (form-instance locked)
       ↓
  Attacker dumps DB → ciphertext only → worthless ✅
  Cross-form injection attempt → GCM tag mismatch → fail ✅
       ↓
  Admin opens Vault → Auto-Upgrade Engine fires → decryption in RAM
  + escapeHtml() wraps all DOM output
       ↓
  Plaintext never leaves memory
```

---

## 📊 Security Features

| Feature | Standard Plugin | V5.3.0 | V6.0.0 |
|---|---|---|---|
| Database encryption | ❌ | ✅ AES-256-GCM | ✅ AES-256-GCM |
| Zero Disk State | ❌ | ✅ | ✅ |
| GCM Authentication Tag | ❌ | ✅ | ✅ |
| Form-Bound AAD Binding | ❌ | ❌ | ✅ form_id in AAD |
| CSRF — WP Nonce | partial | ✅ | ✅ |
| CSRF — Cache-immune rotating token | ❌ | ✅ | ✅ |
| CSRF — Both tokens strictly required | ❌ | ❌ (logic defect) | ✅ fixed |
| IP Spoofing protection | ❌ | ✅ | ✅ |
| Zero-Trust proxy default | ❌ | ✅ | ✅ |
| Rate Limiting | ❌ | ✅ 60s per real IP | ✅ |
| Honeypot Bot Detection | ❌ | ✅ | ✅ |
| Injection Guard | partial | ✅ 11 layers | ✅ 12 layers |
| Key migration — zero downtime | ❌ | ✅ Auto-Upgrade | ✅ |
| XSS hardening in admin panel | ❌ | partial | ✅ escapeHtml() |
| Key file protection | ❌ | ✅ | ✅ |
| Paginated Admin Vault | ❌ | ✅ Platinum design | ✅ per-form view |
| GDPR compliant by design | ❌ | ✅ | ✅ |
| Drag-and-Drop form builder | ❌ | ❌ | ✅ |
| Multi-step funnel engine | ❌ | ❌ | ✅ step_break |
| Dynamic field types | ❌ | ❌ | ✅ 11 types |
| CSS Custom Properties theming | ❌ | ❌ | ✅ |
| Inline canvas editing | ❌ | ❌ | ✅ |
| Element-level color control | ❌ | ❌ | ✅ off-screen engine |

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
Three database tables created automatically.
Cryptographic key generated automatically.
Fixed Dual-Defense CSRF tokens initialized automatically.
```

**3. Build your form:**
```
WordPress Admin → VGT Vault → Form Builder → New Form
Drag fields onto the canvas. Add step_break for multi-step funnels.
```

**4. Deploy form:**
```
[vgt_omega_form id="1"]
[vgt_omega_comlink]              ← legacy shortcode still works
```

**5. Open vault:**
```
WordPress Admin → VGT Vault → Submissions
Records decrypted on-the-fly. Auto-Upgrade Engine fires transparently.
```

---

## 📁 File Structure

```
vgt-omega-vault/
├── vgt-omega-vault.php              ← bootstrapper + lifecycle hooks
│
├── includes/                        ← modular kernel directory
│   ├── class-vgt-omega-crypto.php   ← AES-256-GCM + form-bound AAD + 3-tier key engine
│   ├── class-vgt-omega-db.php       ← 3-table abstraction + dual-vector IP + pagination
│   ├── class-vgt-omega-api.php      ← firewall + fixed dual-CSRF + validation pipeline
│   ├── class-vgt-omega-frontend.php ← client-side rendering + funnel step controller
│   ├── class-vgt-omega-ui.php       ← admin rendering + vault dashboard (strictly separated)
│   └── class-vgt-omega-builder.php  ← drag-and-drop builder + CSS custom properties engine
│
├── assets/
│   ├── vgt-omega.js                 ← AJAX + CSRF token injection + funnel state machine
│   ├── vgt-omega-builder.js         ← live builder engine + inline editing + color picker
│   └── vgt-omega.css                ← Platinum/Gold UI + CSS custom properties
│
├── phpunit1.php                     ← standalone regression tests (V5.3.0+)
│
└── Auto-generated:
    └── wp-content/uploads/vgt_keys/
        ├── .htaccess                ← direct access blocked (Apache 2.4+)
        ├── index.php                ← zero-space guard
        └── .vgt_core_secret.php     ← AES key (chmod 0600)
```

---

## 🎯 Who Is This For?

```
⚖️  Law Firms            → encrypted client inquiries via custom funnels
🏥  Medical Practices    → patient request forms — GDPR-compliant by design
📊  Tax Advisors         → confidential client intake — AES-256-GCM protected
🏛️  Notaries             → multi-step disclosure forms with funnel segmentation
🔐  Security Teams       → encrypted vulnerability disclosure workflows
🏢  Enterprises          → any confidential inquiry pipeline — drag-and-drop deployment
```

---

## 🆚 Market Comparison

```
Gravity Forms  ($259/year):  No encryption. Plaintext in DB. No funnel builder included.
WPForms Pro    ($199/year):  No encryption. Plaintext in DB.
Ninja Forms    ($99/year):   No encryption. Plaintext in DB.
Formidable     ($199/year):  No encryption. Plaintext in DB.

VGT Omega Vault (free):      AES-256-GCM. Form-Bound AAD. Zero Disk State.
                              Fixed Dual-Defense CSRF. Cache-immune.
                              Drag-and-Drop Builder. Multi-Step Funnel Engine.
                              11 Field Types. CSS Custom Properties Theming.
                              GDPR-compliant by design.
```

---

## 🧪 Automated Regression Tests *(V5.3.0+)*

`phpunit1.php` is a standalone regression suite covering core IP parsing and crypto logic — no WordPress environment required.

```bash
php phpunit1.php
./vendor/bin/phpunit phpunit1.php
```

**Test coverage includes:**
- IP chain parsing from `X-Forwarded-For` multi-value headers
- Private IPv4/IPv6 range filtering
- Cloudflare CIDR validation (`is_cloudflare_ip()`)
- Dual-vector socket/claimed IP separation logic
- Form-bound AAD binding integrity (V6.0.0)
- Dual-CSRF strict handshake validation (V6.0.0)

Suitable for CI/CD pipeline integration — no WordPress environment dependency.

---

## ⚠️ Important Notice

```
MANUALLY MODIFYING THE KEY FILE DESTROYS ALL ENCRYPTED DATA.

The cryptographic key is generated once on activation.
Back it up before any server migration:
  wp-content/uploads/vgt_keys/.vgt_core_secret.php

V6.0.0 Migration from V5.3.0:
  The database schema expands automatically on activation (3 tables).
  Legacy submissions in wp_vgt_omega_audits remain accessible.
  The Auto-Upgrade Engine handles key transitions transparently on read.
  The legacy shortcode [vgt_omega_comlink] is mapped automatically.

V6.0.0 Form-Bound AAD:
  Ciphertexts from V5.3.0 use domain-only AAD context.
  The Auto-Upgrade Engine detects and re-encrypts legacy records with
  form-bound AAD on first access — no manual migration required.
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

*VGT Omega Vault v6.0.0 — Drag-and-Drop Form & Funnel Builder // Form-Bound AAD Binding // Dual-Defense CSRF Fix // AES-256-GCM // Zero Disk State // Multi-Step Funnel Engine // CSS Custom Properties // XSS Hardening // Zero-Trust Proxy Protocol // Cloudflare CIDR Validation // GDPR-compliant by design // AGPLv3*

</div>
