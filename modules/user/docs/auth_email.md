# Email auth (register, login, password reminder)

Created: 2026-06-21

## Panels

- `user/register` — email/password sign-up (music extends via `music/register`)
- `user/login` — email or username login (`user/user_settings` → login username)
- `user/reminder` — forgot-password email + token reset

Shared model: [`user_model.php`](../models/user_model.php)

Related: Google auth in [`auth_google.md`](auth_google.md)

Phase 1 code complete (register validation, `log_in_after`, mailing lists, reminder mail headers).

## CMS configuration

- Register panel: **Success URL** (`redirect_link`) — avoids `alert()` fallback in [`register.js`](../js/register.js)
- Register panel: **Log in after** = Yes (if auto-login desired)
- CMS settings: **From email** populated (needed for password reminder delivery)

## MVP QA

See consolidated checklist in [`user_register.md`](user_register.md) § MVP QA.

Open for MVP:

- [ ] Login `cms_page_panel_id` AJAX — same pattern as register ([`login.tpl.php`](../templates/login.tpl.php) + [`login.js`](../js/login.js))
- [ ] Optional: inline success message when Success URL unset ([`login.js`](../js/login.js), [`register.js`](../js/register.js))

## Post-MVP

- Central mail helper in user module (wrap `mail()` / future SMTP); document SMTP or host `mail()` setup
- Email verification (`email_verified` flag, confirm-link email, `user/verify_email` panel, block unverified login)
- Upgrade password hashing (`password_hash` / `password_verify`) with SHA1 migration
- Stronger email validation (`filter_var(FILTER_VALIDATE_EMAIL)`)
- Rate limits on register and reminder endpoints
- Optional honeypot or CAPTCHA on register
- CMS settings guide for email auth in admin