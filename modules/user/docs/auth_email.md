# Email auth (register, login, password reminder)

Created: 2026-06-21

## Panels

- `user/register` — email/password sign-up (music extends via `config.json` → `//user_register`)
- `user/login` — email or username login (`user/user_settings` → login username)
- `user/reminder` — forgot-password email + token reset (ScoreTutor theme via music `config.json` extend `//user_reminder` → `music/css/user_reminder.scss`). Input label/lookup follow **user/user_settings → Login username** (email vs username), same as `user/login`.
- `user/password_change` — logged-in change password (new + confirm, ajax). Embedded via `_panel()` (e.g. `music/settings`). Copy/labels: **User → Password change** (`admin/panel_settings/user__password_change/`). Theme: music extend `//user_password_change`. Shared validation/email helpers on `user_model` (also used by reminder save).
- `user/verify_email` — email confirmation link handler

Shared models: [`user_model.php`](../models/user_model.php) (verification tokens, auth state, `validate_new_password` / `change_user_password` / `send_password_updated_email`), [`cms_email_model.php`](../../cms/models/cms_email_model.php) (all outbound mail transport)

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

- New passwords: `password_hash(PASSWORD_DEFAULT)` in `create_user` / `set_user_password` / reminder reset / `password_change`
- Login: `password_verify()` with transparent SHA1 migration on success

## CMS configuration

- Register panel: **Log in after** = Yes (if auto-login desired)
- CMS settings: **From email** populated (reminder + verification + password-updated mail)
- Page `/verify-email/` with `user/verify_email` panel
- **User → Password change** — labels/messages for the embedded change form (not a page block)

## MVP QA

See consolidated checklist in [`user_register.md`](user_register.md) § MVP QA.

## Post-MVP

- ~~Central mail helper~~ — done: [`cms_email.md`](../../cms/docs/cms_email.md)
- Stronger email validation (`filter_var(FILTER_VALIDATE_EMAIL)`)
- Rate limits on register and reminder endpoints
- Optional honeypot or CAPTCHA on register
- CMS settings guide for email auth in admin