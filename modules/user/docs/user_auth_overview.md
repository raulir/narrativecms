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
| `user/password_change` | embed (e.g. `/settings-account/`) | Logged-in new password + confirm (ajax); labels under **User → Password change** |
| `user/verify_email` | `/verify-email/` | Confirm email from register mail |
| `user/userforward` | Guest pages | Logged-in visitors → `user_link` |
| `user/logout` | — | Clear session → `logout_link` |

**DB panel name must match the real panel** (e.g. `user/login`, not legacy `music/login`). Params are stored by `cms_page_panel_id`; rename only `panel_name` to fix admin fields. Script: `php ai/rename_legacy_music_login.php`.

## Password reminder (not magic login)

1. User enters **login name** (email or username per **Login username** setting) on `/reminder/`.  
2. Token (64 hex characters, `user_model::new_token()`) written to **`tmp/user_reminders.json`**; **previous open tokens for that same login name are deleted** so only the newest link works. Email body is `{reminder page URL}?token=…`, built by `user_model::reminder_link()` from this site's address (`base_site`, else the request host's config) and the page that shows `user/reminder`, never from a posted value (`ajax.input` lets only `username`, `password`, `password2`, `token` through).  
3. Open link within **30 minutes** → form for **new password**; login field is **prefilled** with the value used when requesting the link.  
4. Save validates token + TTL; password is set only if that succeeds (UI no longer claims success on a failed update).  
5. User signs in on login with **email** if “Email is login name” (not the display Username field on the user record).  

Having a **Username** on the user (e.g. “Eunice”) does not change email-login mode — they must use **email** to log in.  

Admin can mint a link via **`user/loginlink`** (CMS only) into the same JSON file. Tokens are **not** durable across cache wipe / multi-server without shared `cache/`.

Forgot CTA on login is **on the login page block**: **Forgot URL** / text / CTA (`forgot_link`, …) — not under User settings → Links.

## Email verification

Register → `send_email_verification()` → `/verify-email/?token=…` → sets `email_verified`. Tokens in `tmp/user_email_verifications.json` (single use, 7 days). Resend from login when unverified. The link signs in only in the browser that asked for the mail (§ Sign-in only from our own pages).

## Music theme (config extends)

`music/config.json` → `extends`:

| Target | Source | SCSS |
|--------|--------|------|
| `user/login` | `//user_login` | `music/css/user_login.scss` |
| `user/register` | `//user_register` | `music/css/user_register.scss` |
| `user/reminder` | `//user_reminder` | `music/css/user_reminder.scss` |

Place **target** panels on pages. Extension defs may be empty `item:[]` (theme only). Base SCSS stays tiny under `user/css/`; ScoreTutor look is the music files.

## Session length

Frontend login (`$_SESSION['user']`) uses the PHP session in [`session.php`](../../../system/core/session.php) (`cms_session_boot()` — same boot as admin and APIs that need the session). **CMS → Site settings → Session length days** (default **30**, clamp 1–365). Cookie + session-file GC use that many days. `Set-Cookie` is sent when the browser has no session cookie, or **once per PHP session** (`_session_cookie_ok`) so a leftover Session cookie is upgraded to the 30-day expiry. A new setting value applies on the **next request**; shortening days can expire session files (GC) before the browser cookie dies. `dir.session` (default `tmp/sessions`) isolates files from the system 1440s GC.

CMS admin shares the same cookie but is **logged out 24 hours after last password entry** (`$_SESSION['cms_password_last_checked']`). Frontend login is not affected by that stamp.

## Passwords

- Store: `password_hash(PASSWORD_DEFAULT)`.  
- Login: `password_verify`; legacy SHA1 upgraded on successful login.
- **One rule** (since 2026-11-20): at least 8 characters with a letter and a number, `user_model::password_error()`, for register (or the block's higher minimum), the reminder reset and password change. Before, the reset and change took 5.
- **Password change** asks for the current password when the account has one (an account made with Google has none and sees no such row): `password_current`, error `bad_current`.

## Emails in lowercase

Emails are kept and looked up in lowercase (`user_model::normalise_email()`), since phones capitalise the first letter: register, `create_user` (so Google sign-up too), login, the reminder, resend, and `get_user_by_email()`. The email inputs (website login, register, reminder, the app's login) have `autocapitalize="none"`. Stored addresses were lowercased once by `ai/user_email_lowercase.php <host> [--write]`, which leaves two accounts that differ only in case alone and lists them. Lookups also ignored case before (the param `value` column is `utf8mb4_general_ci`).

## Limits and sessions

- **Login:** an unknown user and a wrong password answer the same `bad_password` ("Wrong email or password"), so nobody learns which accounts exist. After 10 failures for one login name or 20 from one client address within 15 minutes the answer is `too_many` before any password is checked.
- **Mail:** the reminder and "resend confirmation" allow 3 an hour per typed address and 10 per client address, counted whether or not the account exists. Register allows 5 new accounts an hour per client address.
- **Storage:** `user_model::attempts_over()` / `attempt_note()` keep timestamps in `tmp/user_limits/`, written under a lock (`json_file_update()`, which the reminder and verification token files use too).
- **Fail2ban:** each failure and each refusal writes `log/user_auth.log` in the 404 log's format (`[client <ip>] USER AUTH login_fail`); the optional jail is in CMS [`fail2ban.md`](../../cms/docs/fail2ban.md).
- **Other sessions end:** the session keeps the password hash it was made with; `get_current()` clears a session whose hash no longer matches, so a password change or reset signs out the user's other devices. The session that changes its own password is refreshed and stays.
- **Logout** clears the user and its keys and gives a new session id.
- **Check:** `ai/tmp/user_auth_check.js`.

## Sign-in only from our own pages

Since 2026-11-20 another site can't sign a visitor in to an account of its choosing (login CSRF: the visitor would then practise and pay into the attacker's account).

- **Origin check:** `user_model::request_from_own_site()` takes the `Origin` header, else the `Referer`'s origin. It refuses `null` and any origin outside `own_origins()`, with a `bad_origin` line in `log/user_auth.log`. A request with neither header is allowed (not a browser form or fetch).
  - **`own_origins()`:** `base_site`, `base_host` and the request host. On an app host (`inapp`) also the web app's origins (`app_web_origins()`: config `app_web_origins`, else `base_site` without its `app.` / `app_` label; `subscription_model::app_web_origins()` calls this one) and the Android app's `http://localhost` (androidScheme `http`), `https://localhost`, `capacitor://localhost`.
  - **Used by:** `user/login` (`cms_do=login`, answer `bad_origin`), `user/register` (`errors: ['bad_origin']`, no account), `user/auth_google` and `user/auth_gapp`. A real visitor never sees `bad_origin`, so it has no text.
  - **Why the app host too:** there a browser's session cookie wins over the app's posted session id (`session.php` `cms_session_adopt_posted`), so a forced sign-in could reach the web app.
- **Google:** the website button posts the session's state with `cms_do=google`; the app's Google sign-in works only on the app host's ajax post ([`auth_google.md`](auth_google.md) § Only our own button signs in).
- **Email confirmation link:** sending the mail (register, resend) marks this browser's session (`user_model::verify_mark()`, `$_SESSION['user_verify_mark']`); the token keeps `sha1(mark)`. `verify_email` always confirms, but signs in (with a new session id) only where the mark matches. Opened in another browser, the email is confirmed and Continue goes to the login page; a token from before this change confirms without signing in.
- **Browser hand-off** (`subscription/enter/?app_enter=`): signs in with a new session id. The link itself can still be passed on (5 minutes, single use; user `todo.md`).
- **Check:** `ai/tmp/signin_origin_check.js`.

## Quick checklist

- [ ] `user_settings`: login name mode, links, From email  
- [ ] Login/register/reminder panels are `user/*` not `music/*`  
- [ ] Login **Forgot URL** → `/reminder/`  
- [ ] Request reminder → mail arrives → token form → new password → login  
- [ ] With **Email is login name**, reminder asks for **email**
