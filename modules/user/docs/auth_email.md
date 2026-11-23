# Email auth (register, login, password reminder)

Created: 2026-06-21

## Panels

- `user/register` — email/password sign-up (music extends via `config.json` → `//user_register`)
- `user/login` — email or username login (`user/user_settings` → login username)
- `user/reminder` — forgot-password email + token reset (ScoreTutor theme via music `config.json` extend `//user_reminder` → `music/css/user_reminder.scss`). Input label/lookup follow **user/user_settings → Login username** (email vs username), same as `user/login`. The mailed link is `user_model::reminder_link()`: this site's address and the page that shows the reminder, never a posted address (until 2026-11-20 the page's own `url` was posted and used, so anyone could have the mail link to another site). `user/loginlink` uses the same link and token.
- `user/password_change` — logged-in change password (new + confirm, ajax). Embedded via `_panel()` (e.g. `music/settings`). Copy/labels: **User → Password change** (`admin/panel_settings/user__password_change/`). Theme: music extend `//user_password_change`. Shared validation/email helpers on `user_model` (also used by reminder save).
- `user/verify_email` — email confirmation link handler

New password errors (reminder save and `password_change`), each with its own text field: `passwords_mismatch`, `password_short` (the one rule since 2026-11-20: at least 8 characters with a letter and a number, `user_model::password_error()`, also used by register; before, 5 characters), and on the reminder `timeout` when the link's token is missing, replaced by a newer reminder, used or older than 30 minutes (the "Timeout" text). `bad_save` is left for a real failure. Until 2026-11-18 a short password and a stale link both showed only "Problem with update". Check: `ai/tmp/reminder_check.js`.

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

Since 2026-11-20 the link signs in (with a new session id) only in the browser that registered or asked for the resend (`user_model::verify_mark()`); anywhere else it confirms the email and Continue goes to the login page, so nobody can be signed in by opening someone else's link ([`user_auth_overview.md`](user_auth_overview.md) § Sign-in only from our own pages). Login and register also refuse a post from another site (`bad_origin`).

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
- ~~Stronger email validation~~ — done 2026-11-20 on register (`FILTER_VALIDATE_EMAIL`)
- ~~Rate limits on register and reminder endpoints~~ — done 2026-11-20 ([`user_auth_overview.md`](user_auth_overview.md) § Limits and sessions)
- Optional honeypot or CAPTCHA on register
- CMS settings guide for email auth in admin