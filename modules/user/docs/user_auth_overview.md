# User auth overview (ScoreTutor)

Short map of login, register, reminder, email confirm, and how music themes them. Detail: [`auth_email.md`](auth_email.md), [`user_register.md`](user_register.md), [`auth_google.md`](auth_google.md).

## Global switches (`user/user_settings`)

| Setting | Effect |
|---------|--------|
| **Login username** | `0` = **email** is login name; `1` = **username**. Used by `user/login` and `user/reminder` for label + lookup. |
| **Email confirmation** | If yes, login blocked until `/verify-email/?token=…` succeeds. |
| **Links** | `login_link` (guest), `user_link` (after login/register/Google), `logout_link`. |
| **Default access** | Keys on new register (e.g. `music_student`); modules also grant `login_access` on login. |

Access keys, panel `"access"` / `"access_blocked"`, page gates: [`access.md`](../../cms/docs/access.md).

Mail needs CMS **From email** / SMTP ([`cms_email.md`](../../cms/docs/cms_email.md)).

## Panels and pages

| Panel | Typical page | Role |
|-------|--------------|------|
| `user/login` | `/login/` | Email or username + password; forgot CTA; optional Google alternative |
| `user/register` | `/register/` | Sign-up; optional auto-login (**Log in after**) |
| `user/reminder` | `/reminder/` | Request reset email; open token link → set new password (**not** auto-login) |
| `user/password_change` | embed (e.g. `/settings/`) | Logged-in new password + confirm (ajax); labels under **User → Password change** |
| `user/verify_email` | `/verify-email/` | Confirm email from register mail |
| `user/userforward` | Guest pages | Logged-in visitors → `user_link` |
| `user/logout` | — | Clear session → `logout_link` |

**DB panel name must match the real panel** (e.g. `user/login`, not legacy `music/login`). Params are stored by `cms_page_panel_id`; rename only `panel_name` to fix admin fields. Script: `php grok/rename_legacy_music_login.php`.

## Password reminder (not magic login)

1. User enters **login name** (email or username per **Login username** setting) on `/reminder/`.  
2. Token written to **`cache/user_reminders.json`**; **previous open tokens for that same login name are deleted** so only the newest link works. Email body is `{reminder page URL}?token=…`.  
3. Open link within **30 minutes** → form for **new password**; login field is **prefilled** with the value used when requesting the link.  
4. Save validates token + TTL; password is set only if that succeeds (UI no longer claims success on a failed update).  
5. User signs in on login with **email** if “Email is login name” (not the display Username field on the user record).  

Having a **Username** on the user (e.g. “Eunice”) does not change email-login mode — they must use **email** to log in.  

Admin can mint a link via **`user/loginlink`** (CMS only) into the same JSON file. Tokens are **not** durable across cache wipe / multi-server without shared `cache/`.

Forgot CTA on login is **on the login page block**: **Forgot URL** / text / CTA (`forgot_link`, …) — not under User settings → Links.

## Email verification

Register → `send_email_verification()` → `/verify-email/?token=…` → sets `email_verified`. Tokens in `cache/user_email_verify.json` (see model). Resend from login when unverified.

## Music theme (config extends)

`music/config.json` → `extends`:

| Target | Source | SCSS |
|--------|--------|------|
| `user/login` | `//user_login` | `music/css/user_login.scss` |
| `user/register` | `//user_register` | `music/css/user_register.scss` |
| `user/reminder` | `//user_reminder` | `music/css/user_reminder.scss` |

Place **target** panels on pages. Extension defs may be empty `item:[]` (theme only). Base SCSS stays tiny under `user/css/`; ScoreTutor look is the music files.

## Passwords

- Store: `password_hash(PASSWORD_DEFAULT)`.  
- Login: `password_verify`; legacy SHA1 upgraded on successful login.

## Quick checklist

- [ ] `user_settings`: login name mode, links, From email  
- [ ] Login/register/reminder panels are `user/*` not `music/*`  
- [ ] Login **Forgot URL** → `/reminder/`  
- [ ] Request reminder → mail arrives → token form → new password → login  
- [ ] With **Email is login name**, reminder asks for **email**
