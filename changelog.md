## 📋 Changelog — V5.3.0
 
> **V5.3.0 is an architectural overhaul.** Monolith decomposed into isolated kernel modules, dual-vector IP forensics at database level, and automated regression tests.
 
| Area | V5.2.1 | V5.3.0 |
|---|---|---|
| **Architecture** | Monolithic single-file plugin | Modular `includes/` kernel directory — strict separation of concerns |
| **IP Storage** | Single `ip_origin` column — socket and claimed IP merged | `ip_socket` (REMOTE_ADDR, unforgeable) + `ip_claimed` (header-submitted) — physically separated |
| **Proxy Trust Model** | Proxy headers evaluated by default | Zero-Trust default — proxy header evaluation requires explicit admin opt-in (`vgt_omega_allow_proxies`) |
| **Test Coverage** | No automated tests — manual click-through only | `phpunit1.php` standalone regression suite — no WordPress core required |
 
---


## 📋 Changelog — V5.2.1

> **V5.2.1 is a security patch release** — three community-reported issues resolved. Special thanks to **[Daniel Ruf](https://github.com/DanielRuf)** for the responsible disclosure of all three findings.

| Issue | Fix |
|---|---|
| **IP-Spoofing via CF-Connecting-IP** | `is_cloudflare_ip()` CIDR validator — `HTTP_CF_CONNECTING_IP` trusted only when request originates from a verified Cloudflare IP range |
| **Database Column Types** | `domain`, `email`, `vector`, `ip_origin` migrated from `text` to `varchar(...)` — full MySQL index support, reduced I/O overhead |
| **Apache 2.4 .htaccess Compatibility** | `<IfModule mod_authz_core.c>` guard added — `Require all denied` on Apache 2.4+, legacy `Deny from all` fallback preserved for older environments |

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

