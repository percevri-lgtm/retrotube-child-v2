# TML Popup Adapter — Implementation Notes

- We keep the popup UI. The adapter exposes three AJAX actions:
  - tmw_tml_login → fields: log, pwd, rememberme?, redirect_to?
  - tmw_tml_register → fields: user_login, user_email, (pass1, pass2 if TML “set password” is enabled)
  - tmw_tml_lostpassword → field: user_login
- Back-compat: if the old actions (wpst_*_member) are not already registered, we register them to the same handlers.
- Registration respects TML “Allow users to set their own password”:
  - **OFF (current site):** creates user with a strong temp password and immediately triggers `retrieve_password()` to email the set-password link.
  - **ON:** requires pass1/pass2 to match.
- Success/Errors are normalized JSON so the popup can show the same green message bar.
- Cookies are set by `wp_signon()` (AJAX is same-origin), no page reload needed; use `redirect` from JSON if you want to navigate to /dashboard/.
- Keep Cloudflare/Autoptimize bypass for `/wp-login.php` and TML slugs to avoid `error=expiredkey`.
