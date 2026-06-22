# Email auth (register, login, password reminder)

Created: 2026-06-21

## Panels

- `user/register` — email/password sign-up (music extends via `music/register`)
- `user/login` — email or username login (`user/user_settings` → login username)
- `user/reminder` — forgot-password email + token reset

Shared model: [`user_model.php`](../models/user_model.php)

Related: Google auth in [`auth_google.md`](auth_google.md)

## Phase 1 — done

- [x] Save extra register fields to `meta` in [`register.php`](../panels/register.php)
- [x] Server-side validation vs panel settings (`show_email`, `show_fullname`, `show_username`, `show_password`, extra fields)
- [x] `log_in_after` setting — sets `$_SESSION['user']` after successful register
- [x] Mailing list block — reads `mailinglists` from panel settings, uses `$data` fields
- [x] `create_user` — skip email duplicate/format checks when email empty
- [x] Reminder mail — `From` uses `config['email']`; shared `_mail_headers()` on both `mail()` calls

## CMS configuration

- Register panel: **Success URL** (`redirect_link`) — avoids `alert()` fallback in [`register.js`](../js/register.js)
- Register panel: **Log in after** = Yes (if auto-login desired)
- CMS settings: **From email** populated (needed for password reminder delivery)

## Phase 1 manual test checklist

- [ ] Register while logged out → redirect to success URL (or alert if URL not set)
- [ ] With **Log in after** = Yes → arrive at success URL already logged in
- [ ] Log in with same email + password on login panel
- [ ] Duplicate email → `message_emailexists`
- [ ] Extra register field (if configured) saved in user `meta`
- [ ] Forgot password → token in `cache/user_reminders.json`; email sends if host mail works

---

## Deferred — Phase 2 (mail foundation)

- [ ] Central mail helper in user module (wrap `mail()` / future SMTP)
- [ ] Document SMTP or host `mail()` setup for production
- [ ] Replace `alert('registration successful')` in [`register.js`](../js/register.js) with inline success message

## Deferred — Phase 3 (email verification)

- [ ] `email_verified` flag (or `show=0` until confirmed) on `user/user`
- [ ] Confirm-link email on register; `user/verify_email` panel
- [ ] Register setting: require email verification yes/no; email templates with `{{link}}`
- [ ] Block login for unverified users; optional resend confirm email

## Deferred — Phase 4 (security)

- [ ] Upgrade password hashing (`password_hash` / `password_verify`) with SHA1 migration
- [ ] Stronger email validation (`filter_var(FILTER_VALIDATE_EMAIL)`)
- [ ] Rate limits on register and reminder endpoints
- [ ] Optional honeypot or CAPTCHA on register

## Deferred — Phase 5 (ops)

- [ ] Full manual test checklist (register → login → reset → duplicate email → Google coexistence)
- [ ] CMS settings guide for email auth in admin