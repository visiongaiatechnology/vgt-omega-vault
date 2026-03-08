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
[![PHP](https://img.shields.io/badge/PHP-8.0+-blue?style=for-the-badge&logo=php)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-21759B?style=for-the-badge&logo=wordpress)](https://wordpress.org)
[![Encryption](https://img.shields.io/badge/Encryption-AES--256--GCM-gold?style=for-the-badge)](#)
[![Status](https://img.shields.io/badge/Status-DIAMANT_VGT_SUPREME-purple?style=for-the-badge)](#)

**OMEGA PROTOCOL ACTIVE · ZERO DISK STATE · ENCRYPTED TRANSMISSION**

</div>

---

## 🔐 What is VGT Omega Vault?

The WordPress ecosystem has **58,000+ form plugins**.  
Not a single one encrypts data before writing it to the database.

**VGT Omega Vault** closes this gap.

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
│ AES-256-GCM  │  Abstracted  │  CSRF Guard  │  Shortcode │
│ GCM Auth Tag │  Pagination  │  Rate Limit  │  Generator │
│ Random IV    │  Encrypted   │  Honeypot    │  Gold UI   │
│ RAM-only     │  Storage     │  Injection   │  AJAX      │
│ Decryption   │              │  Detection   │            │
└──────────────┴──────────────┴──────────────┴────────────┘
```

### 🔑 Crypto Kernel (`VGT_Omega_Crypto`)

The cryptographic core. Every data element is encrypted with **AES-256-GCM** — the same standard used by the US military for TOP SECRET data.

```php
// Encryption: Data → Ciphertext (stored in DB)
VGT_Omega_Crypto::encrypt($sensitive_data);

// Decryption: Ciphertext → Plaintext (RAM only)
VGT_Omega_Crypto::decrypt($ciphertext);
```

**Key Management:**
- 512-bit entropy during key generation (`random_bytes(64)`)
- Key file: `wp-content/uploads/vgt_keys/.vgt_core_secret.php`
- Direct access blocked via `.htaccess` + PHP exit guard
- File permissions: `chmod 0600`

### 🗄️ Database Kernel (`VGT_Omega_DB`)

```
Stored in DB:               What attackers see:
  domain    → Ciphertext      K7mX9pQr2nZwAb...
  email     → Ciphertext      Lp4vN8kJhFmD3...
  vector    → Ciphertext      Wq6tR1uYcEiOx...
  threat    → Ciphertext      Bs5aG0ePzHlVn...
  ip_origin → Ciphertext      Tx2jM7yKdCfUw...
```

Even with full database access, all data remains **cryptographically worthless**.

### 🛡️ API Kernel (`VGT_Omega_API`)

Multi-layered defense for every incoming request:

```
Layer 1:  Method Guard        → POST only
Layer 2:  CSRF Protection     → wp_verify_nonce()
Layer 3:  Rate Limiting       → 60s cooldown per IP
Layer 4:  Honeypot Detection  → bot trap field
Layer 5:  Email Validation    → Regex + is_email()
Layer 6:  Domain Validation   → Regex pattern
Layer 7:  Vector Validation   → whitelist pattern
Layer 8:  Injection Guard     → [<>{}\[\]\=] blocked
Layer 9:  AES-256-GCM         → encryption
Layer 10: DB Write            → ciphertext only
```

### 🎨 Frontend Kernel (`VGT_Omega_Frontend`)

```
[vgt_omega_comlink]
```

A single shortcode deploys the complete encrypted form — including Gold/Dark design, loading states, and AJAX transmission.

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
Database table is created automatically.
Cryptographic key is generated automatically.
```

**3. Deploy form:**
```
Insert shortcode on any page or post:
[vgt_omega_comlink]
```

**4. Open vault:**
```
WordPress Admin → VGT Vault
All incoming requests are decrypted on-the-fly and displayed.
```

---

## 📊 Security Features

| Feature | Standard Plugin | VGT Omega Vault |
|---------|----------------|-----------------|
| Database encryption | ❌ | ✅ AES-256-GCM |
| Zero Disk State | ❌ | ✅ RAM-only |
| GCM Authentication Tag | ❌ | ✅ Tamper detection |
| CSRF Protection | partial | ✅ wp_verify_nonce |
| Rate Limiting | ❌ | ✅ 60s cooldown |
| Honeypot Bot Detection | ❌ | ✅ |
| Injection Guard | partial | ✅ 10 layers |
| Key file protection | ❌ | ✅ .htaccess + chmod 600 |
| Admin Vault Dashboard | ❌ | ✅ |
| Email notification | ✅ | ✅ |
| GDPR compliant by design | ❌ | ✅ |

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
  RAM: Validation + Encryption (milliseconds)
       ↓
  Ciphertext → MySQL Database
       ↓
  Attacker dumps DB → ciphertext only → worthless ✅
       ↓
  Admin opens Vault → decryption in RAM
       ↓
  Plaintext never leaves memory
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

## 📁 File Structure

```
vgt-omega-vault/
├── vgt-omega-vault.php      ← main plugin file
│
├── Kernels (inline):
│   ├── VGT_Omega_Crypto     ← AES-256-GCM engine
│   ├── VGT_Omega_DB         ← database abstraction
│   ├── VGT_Omega_API        ← request handler + defense
│   ├── VGT_Omega_Frontend   ← shortcode generator
│   ├── VGT_Omega_UI         ← admin vault dashboard
│   └── VGT_Omega_Bootstrap  ← system initialization
│
└── Auto-generated:
    └── wp-content/uploads/vgt_keys/
        ├── .htaccess            ← access blocked
        ├── index.php            ← zero-space guard
        └── .vgt_core_secret.php ← AES key (chmod 600)
```

---

## ⚠️ Important Notice

```
MANUALLY MODIFYING THE KEY FILE DESTROYS ALL ENCRYPTED DATA.

The cryptographic key is generated once.
Back it up before migration or server transfer:
  wp-content/uploads/vgt_keys/.vgt_core_secret.php
```

---

## 🆚 Market Comparison

```
Gravity Forms  ($259/year):  No encryption. Plaintext in DB.
WPForms Pro    ($199/year):  No encryption. Plaintext in DB.
Ninja Forms    ($99/year):   No encryption. Plaintext in DB.
Formidable     ($199/year):  No encryption. Plaintext in DB.

VGT Omega Vault (free):      AES-256-GCM. Zero Disk State.
                              GDPR-compliant by design.
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
