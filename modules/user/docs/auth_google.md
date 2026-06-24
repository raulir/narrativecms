# Google auth

Created: 2026-06-20

## Panels

- `user/auth_google` — web GSI (Google Identity Services button + POST)
- `user/auth_gapp` — native app WebView AJAX endpoint

Shared logic:
[`user_google_model.php`](../models/user_google_model.php)

App WebView login was verified on Android emulator (separate app project).

## MVP — Google web

- [ ] **Success redirect** — after GSI POST to `/auth-google/`, session
  is set but [`auth_google.tpl.php`](../templates/auth_google.tpl.php) only
  prints `$error`. User sees a blank page. Use `redirect_link` from panel
  settings (`start/`) — pattern in
  [`userforward.php`](../panels/userforward.php) or logged-in branch in
  [`login.tpl.php`](../templates/login.tpl.php).
- [ ] **Error display** — show a readable message for `google_error`, not
  a raw key.

## MVP QA

See consolidated checklist in [`user_register.md`](user_register.md) § MVP QA (Google rows).

## Post-MVP — native app (`user/auth_gapp`)

App flow works via native `google_login()` bridge (not in this repo).

- [ ] App project: change AJAX `panel_id` from `music/auth_google` to
  `user/auth_gapp`
- [ ] Move login logic from `panel_params` → `panel_action` if native
  bridge switches to `get_ajax()` (`no_html: 1` skips `panel_params`)
- [ ] Document native bridge contract in app project (payload shape,
  `no_html` flag, redirect on `login_success`)

## Context

- Web client ID (CMS):
  `270024484737-2vdk9rl0186254ul2ve2qdbv1ejcpmau.apps.googleusercontent.com`
- Pages: `login-google` (9), `auth-google` (10)
- Login panel 86 (`music/login` extends `user/login`) — web alternative
  links to `/login-google/`
- `inapp: "1"` only in [`config/10.0.2.2.json`](../../../config/10.0.2.2.json)
  — app emulator config
- GSI requires HTTPS for `login_uri` — local HTTP shows
  "Unsecured login_uri provided"