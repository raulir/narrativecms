# Google auth

Created: 2026-06-20

## Panels

| Panel | Role | Theme extend |
|-------|------|----------------|
| `user/login_google` | GSI host for **login** — sets session intent `login` | `//user_login_google` |
| `user/register_google` | GSI host for **register** — sets session intent `register` | `//user_register_google` |
| `user/auth_google` | **Shared** callback / result (`/auth-google/`) | `//user_auth_google` |
| `user/auth_gapp` | Native app WebView | — |

Shared logic: [`user_google_model.php`](../models/user_google_model.php)

## Session intent (one Google Console URI)

Both GSI buttons POST the credential to the **same** `login_uri` (typically `/auth-google/`).

| Session key | Set on | Cleared on |
|-------------|--------|------------|
| `$_SESSION['google_auth_intent']` | `login` when `/login-google/` is shown; `register` when `/register-google/` is shown | After `auth_google` handles the credential (success or error) |

Default if missing: **`login`** (no accidental account create).

Helpers: `set_web_auth_intent()`, `get_web_auth_intent()`, `clear_web_auth_intent()`.

## Only our own button signs in (since 2026-11-20)

A page render runs every panel's action, so until 2026-11-20 any render carrying `credential` signed in: a link `/auth-google/?credential=<someone's own Google token>`, or a form on another site, signed the visitor in to that account.

- **State:** `login_google` / `register_google` put `user_google_model::web_auth_state()` (one random token per session, `$_SESSION['google_auth_state']`) in `data-state`. `user_google_button.js` posts `cms_do=google`, `credential` and `state`.
- **`auth_google`** acts only on `cms_do=google` (the CMS never takes `cms_do` from a query string), with the session's state (`web_auth_state_ok`) and from our own page (`user_model::request_from_own_site()`). A failure is `google_error` plus a `google_state` line in `log/user_auth.log`, before Google is asked. The state is replaced after use. `ajax.input`: `cms`, `credential`, `state`. GSI's own `g_csrf_token` is not used (callback mode has none).
- **`auth_gapp`** signs in only from an `ajax_api` POST on an app host (`inapp`) from our origins: the web app's origin and the Android app's `http://localhost` ([`user_auth_overview.md`](user_auth_overview.md) § Sign-in only from our own pages). Its `panel_params` no longer signs in. The app's posts were already this shape, so no app change was needed.
- **Check:** `ai/tmp/signin_origin_check.js` (it can't make a real Google token: a real sign-in is tried by hand).

## Website journeys

### Login

1. `/login/` alternatives → `/login-google/` (intent = login)  
2. GSI → POST `/auth-google/`  
3. `login_from_web_payload`: existing → session; missing → `not_registered`; hidden → `user_hidden`

### Register

1. `/register/` alternatives → `/register-google/` (intent = register)  
2. GSI → POST **same** `/auth-google/`  
3. `register_from_web_payload`: missing → create + session; existing → `emailexists` (**no** login)

Result UI reorders CTAs by intent (login vs register first).

### App

The app posts the native ID token as `credential` to `user/auth_gapp`. The server verifies it with the `google_client_id` saved on `user/auth_google`, then `login_from_verified_app_payload` signs in or creates the account from that payload. A posted email without a valid token does not start a session.

## Operator setup

| URL | Panel | Notes |
|-----|--------|--------|
| `/login-google/` | `user/login_google` | Client id; **auth page** = `/auth-google/` |
| `/register-google/` | `user/register_google` | Same client id; **same auth page** `/auth-google/` |
| `/auth-google/` | `user/auth_google` | Client id; login + register link fields |
| Register page | `user/register` | Alternatives → `/register-google/` |

**Google Cloud Console:** only **`…/auth-google/`** as GSI login URI (plus JS origins). No second register callback URI.

## MVP QA

- [ ] Login Google — existing email → logged in  
- [ ] Login Google — unknown → not_registered  
- [ ] Register Google — new email → account + session  
- [ ] Register Google — existing email → emailexists, not logged in  
- [ ] After visiting register-google, open login-google then complete Google → still **login** (intent overwritten)  
- [ ] Only one auth path in Google Console  

## Post-MVP

- Native app bridge cleanup — [`auth_gapp`](../panels/auth_gapp.php)
