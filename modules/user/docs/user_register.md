# Web registration (`user/register`)

Created: 2026-06-24

Music extends via `music/config.json` → `user/register` + `//user_register` (SCSS in [`user_register.scss`](../../music/css/user_register.scss)). Core panel: [`register.php`](../panels/register.php), [`register.js`](../js/register.js), [`register.tpl.php`](../templates/register.tpl.php).

Related email auth (login, reminder): [`auth_email.md`](auth_email.md)

## Flow

1. Page render — panel block on `/register/` supplies form HTML and `data-cms_page_panel_id` + `data-success` on `.register_container`
2. Submit — `register.js` POSTs to `ajax_api/get_panel/` with `panel_id=user/register`, `cms_do=register`, `register_fields[]`, and `cms_page_panel_id`. The form is not posted as `fields`: that is the block's **Extra fields** repeater, and a stored block value wins over a posted one when the request names the block. As `fields`, the stored repeater replaced the form, and every registration failed with "bad email / mandatory". Fixed 2026-11-16.
3. Server — `register::panel_action` validates against **page block** settings, `create_user()`, optional `log_in_after` session, optional mailing lists
4. Success — client redirects to **user/user_settings** → **Links** → **Link to user** (`user_link`)

**Log in after** (`log_in_after` = Yes): calls `refresh_user_session` only when `login_allowed()` is true. If **User settings → Email confirmation** is Yes, new accounts stay logged out until `/verify-email/` (by design). A confirmation link of an account deleted in the meantime answers `invalid_token`, and nothing is updated or signed in (`user_model::verify_email_token`).

## What the server takes (2026-11-20)

- **Only the form:** `ajax.input` is `["cms", "register_fields"]`. Every other posted key is dropped, so a post can no longer set `show_email`, `show_password`, `password_min_length`, `log_in_after`, `mailinglists` or `fields`.
- **Rules from the stored block only:** `register::_register_rules()` reads the block named by `cms_page_panel_id` itself. It must be a shown `user/register` block, and its values sit over the definition defaults. Without one the answer is `bad_form` and nothing is created (an empty post used to create a user with no email or password).
- **Always a login name:** an email (or the username when that is the login name). With a password field, the password rule applies (`user_model::password_error()`: at least 8, or the block's higher minimum, with a letter and a number).
- **Fields kept:** the core fields (email, username, first and last name, phone) and the names in the block's **Extra fields**; any other posted id is dropped. Each value is stripped of tags, made valid UTF-8 and capped (email 254, names 100, phone 50, extra fields 500). The email is lowercased (`normalise_email()`) and checked with `FILTER_VALIDATE_EMAIL`.
- **Limit:** 5 new accounts an hour from one address (`too_many`).
- **Check:** `ai/tmp/user_auth_check.js`.

## CMS configuration

- **Link to user** — set in `user/user_settings` → **Links** (post-register/login destination, e.g. `/start/`)
- **Log in after** — auto-login after successful register
- Field visibility, extra fields, mailing lists — configured on the page block

## Access keys

Frontend panel access is enforced by [`cms_access_model.php`](../../cms/models/cms_access_model.php) (panel definition `"access"` / `"access_blocked"`, user repeater **Access** on `user/user`). Full guide: [`access.md`](../../cms/docs/access.md).

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