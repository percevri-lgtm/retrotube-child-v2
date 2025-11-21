# TML ↔ Popup Integration — Investigation Report (Audit Only)

## Summary
We will **keep our existing popup** UX and submit to **Theme My Login (TML)** endpoints using the core WordPress field names. This avoids template overrides and preserves our layout. This document captures endpoints, field maps, risks, and a step-plan for an adapter layer.

## What We Added (this PR)
- `inc/tmw-tml-inspector.php` — admin + WP-CLI tool that detects:
  - TML active/version, relevant options (AJAX, registration password, auto-login)
  - Current **slugs** and **endpoints** for login/register/lostpassword
  - Pages containing `[theme-my-login]` shortcodes (if any)
  - Canonical **field maps** our popup must submit

## Endpoints (friendly slugs)
- **Login:** `/{login-slug}/`
- **Register:** `/{register-slug}/`
- **Lost Password:** `/{lostpassword-slug}/`
> Inspector builds exact URLs for the current site.

## Field Maps (what our popup must POST)
- **Login:** `log`, `pwd`, optional `rememberme`, optional `redirect_to`
- **Register:** `user_login`, `user_email` (+ `pass1`/`pass2` if “Allow users to set their own password” is enabled in TML)
- **Lost Password:** `user_login`

## Adapter Strategy (next PR)
1. **Keep** current popup markup and JS.  
2. Add a thin **adapter** (`inc/tmw-tml-adapter.php`) that:
   - Reads the inspector output (slugs/options) at runtime.
   - Injects the **correct endpoint URLs** and **required hidden fields/nonces** into our popup forms via `wp_localize_script`.
   - Normalizes AJAX responses into our existing success/error UI (green bar, etc.).
3. **Caching**: ensure Cloudflare/Autoptimize bypass for `wp-login.php` and **TML slugs**.
4. **Testing Matrix**:
   - Login success/fail (wrong password), remember me
   - Registration with/without “set password”
   - Lost password (expired key, repeated requests)
   - Redirects (from `/videos/` or any page)
5. **Roll-back**: adapter can be toggled off (feature flag) to revert to legacy flows instantly.

## Known Risks
- Double-AJAX if TML’s own AJAX is enabled (keep it **off**).
- Caching layers sometimes cache `wp-login.php` or TML slugs → produces `error=expiredkey`. Keep the bypass rules applied.
- If registration requires email confirmation, adapt the success UI copy.

## Deliverables in NEXT PR (implementation)
- `inc/tmw-tml-adapter.php` (feature-flagged)
- JS bridge that posts to inspector-reported endpoints
- Response normalizer with `[TMW-TML-ADAPTER]` debug logs
