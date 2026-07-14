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

`inapp` + `login_from_app_profile` — may create if missing (unchanged).

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
