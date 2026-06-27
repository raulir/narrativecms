# Email auth (register, login, password reminder)

Created: 2026-06-21

## Panels

- `user/register` — email/password sign-up (music extends via `music/register`)
- `user/login` — email or username login (`user/user_settings` → login username)
- `user/reminder` — forgot-password email + token reset
- `user/verify_email` — email confirmation link handler

Shared model: [`user_model.php`](../models/user_model.php)

Related: Google auth in [`auth_google.md`](auth_google.md)

## Redirects (user/user_settings → Links)

All frontend auth redirects use **User settings** → **Links** (no per-panel success URLs):

- Guest denied → **Link to login** (`login_link`)
- After login / register / Google → **Link to user** (`user_link`)
- After logout → **Log out** (`logout_link`)

Logged-out **Login label** (`login_text`) is on each page **music/hheader** panel (Pages → header position). Logged-in header dropdown: **Settings → User header settings** (`music/userheader`) — Dashboard, Settings, Log out labels and links.

## Email verification

Global switch: **user/user_settings** → **Email address confirmation**

- **Yes** — user must confirm email before login (`email_verified` on `user/user`)
- **No** — confirmation email still sent on register, but login is allowed

Flow: register → `send_email_verification()` → link to `/verify-email/?token=…` → `user/verify_email` sets `email_verified=yes` → redirect to `user_link`.

Login: `unverified_email` error when confirmation required; **Resend confirmation email** on login panel.

Google register: `email_verified=yes` automatically.

## Password hashing

- New passwords: `password_hash(PASSWORD_DEFAULT)` in `create_user` / `set_user_password` / reminder reset
- Login: `password_verify()` with transparent SHA1 migration on success

## CMS configuration

- Register panel: **Log in after** = Yes (if auto-login desired)
- CMS settings: **From email** populated (reminder + verification mail)
- Page `/verify-email/` with `user/verify_email` panel

## MVP QA

See consolidated checklist in [`user_register.md`](user_register.md) § MVP QA.

## Post-MVP

- Central mail helper in user module (wrap `mail()` / future SMTP); document SMTP or host `mail()` setup
- Stronger email validation (`filter_var(FILTER_VALIDATE_EMAIL)`)
- Rate limits on register and reminder endpoints
- Optional honeypot or CAPTCHA on register
- CMS settings guide for email auth in admin