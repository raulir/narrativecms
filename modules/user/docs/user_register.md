# Web registration (`user/register`)

Created: 2026-06-24

Music extends via `music/config.json` → `user/register` + `//user_register` (SCSS in [`user_register.scss`](../../music/css/user_register.scss)). Core panel: [`register.php`](../panels/register.php), [`register.js`](../js/register.js), [`register.tpl.php`](../templates/register.tpl.php).

Related email auth (login, reminder): [`auth_email.md`](auth_email.md)

## Flow

1. Page render — panel block on `/register/` supplies form HTML and `data-cms_page_panel_id` + `data-success` on `.register_container`
2. Submit — `register.js` POSTs to `ajax_api/get_panel/` with `panel_id=user/register`, `do=register`, `fields[]`, and `cms_page_panel_id`
3. Server — `register::panel_action` validates against **page block** settings, `create_user()`, optional `log_in_after` session, optional mailing lists
4. Success — client redirects to **user/user_settings** → **Links** → **Link to user** (`user_link`)

**Log in after** (`log_in_after` = Yes): calls `refresh_user_session` only when `login_allowed()` is true. If **User settings → Email confirmation** is Yes, new accounts stay logged out until `/verify-email/` (by design).

## CMS configuration

- **Link to user** — set in `user/user_settings` → **Links** (post-register/login destination, e.g. `/start/`)
- **Log in after** — auto-login after successful register
- Field visibility, extra fields, mailing lists — configured on the page block

## Access keys

Frontend panel access is enforced by [`cms_access_model.php`](../../cms/models/cms_access_model.php) (panel definition `"access"`, user repeater **Access** on `user/user`).

- **Default access** — `user/user_settings` → **Default access** repeater (applied on register via `create_user`)
- **Login grant** — each module `config.json` → `login_access` array (e.g. `music_student` from [`music/config.json`](../../music/config.json)); merged on login via `refresh_user_session()`

## Music layer

- CMS page block: `user/register` (panel #87); theme in [`user_register.scss`](../../music/css/user_register.scss)
- Entry CTAs — `music/landing` and `user/login` (each has its own CMS `register_link`)
- Logged-in visitors on `/register/` — immediate redirect to Success URL (no inline message)

## MVP QA

Four-path smoke test (email + Google register/login):

- [ ] Register while logged out → redirect to Success URL
- [ ] With **Log in after** = Yes → arrive at Success URL already logged in
- [ ] Log in with same email + password on login panel
- [ ] Duplicate email → `message_emailexists`
- [ ] Extra register field (if configured) saved in user `meta`
- [ ] Register while logged in (AJAX) → `message_loggedin` error
- [ ] Forgot password → token in `cache/user_reminders.json`; email sends if host mail works
- [ ] Web login Google — existing email → `/start/` logged in
- [ ] Web login Google — unknown email → not registered
- [ ] Web register Google — `/register/` → `/register-google/` → same `/auth-google/` → new email → account + session
- [ ] Web register Google — existing email → emailexists, not logged in; retry + login links
- [ ] Google Cloud Console: origins + single login URI `/auth-google/` (session intent for register vs login)

## Post-MVP

- Audit [`profile.tpl.php`](../templates/profile.tpl.php) — legacy register-form markup; check if `user/profile` is deployed anywhere
- Rate limits, honeypot/CAPTCHA on register
- Consent `text` field enforcement (currently display-only)
- Central mail helper in user module (wrap `mail()` / future SMTP)
- Native app Google auth (`auth_gapp`) — [`auth_google.md`](auth_google.md)

See also [`auth_email.md`](auth_email.md) Post-MVP (mail helper, validation, rate limits).