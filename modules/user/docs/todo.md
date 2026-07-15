# User module backlog

Auth, registration, reminder, Google, settings. CMS mail/platform: [`../../cms/docs/todo.md`](../../cms/docs/todo.md).

Sources: [`auth_email.md`](auth_email.md), [`auth_google.md`](auth_google.md), [`user_register.md`](user_register.md), [`user_auth_overview.md`](user_auth_overview.md).

**Legend:** `[ ]` open · `[x]` done

---

## MVP — setup / ops

- [ ] **user/user_settings → Links** — `login_link` `/login/`, `user_link` `/start/`, `logout_link` post-logout destination (re-save after label moves)
- [ ] **user/user_settings** — default access (e.g. `music_student` for ScoreTutor); **Email address confirmation** on/off as desired
- [ ] Page **`/verify-email/`** + `user/verify_email` panel (confirm-link target)
- [ ] CMS **From email** (verification + reminder + welcome mail) — also operators; mail helper is CMS
- [ ] Google Cloud Console — web client origins + `/auth-google/` redirect URI

## MVP QA — auth smoke test

10-row checklist detail: [`user_register.md`](user_register.md) § MVP QA

- [ ] Email register → login → duplicate email → logged-in register block
- [ ] Reminder mail + token file
- [ ] Google login path → `user_link`
- [ ] Guest denied → `login_link`; logout → `logout_link`

---

## Post-MVP — auth & users

- Production mail / SMTP setup docs for operators
- Stronger email validation (`FILTER_VALIDATE_EMAIL`)
- Rate limits on register + reminder
- Honeypot or CAPTCHA on register
- CMS settings guide for email auth
- `profile.tpl.php` audit — legacy register markup; is `user/profile` deployed?
- Consent `text` on register — enforce, not display-only
- **Native app** `user/auth_gapp` — panel_id, `panel_action`, bridge docs — [`auth_google.md`](auth_google.md)

Async mail queue is tracked under CMS: [`cms/docs/todo.md`](../../cms/docs/todo.md) § Email.

---

## Panel JavaScript — `*_ok` / `*_destroy`

- [ ] **`*_ok` refactor — user module** — login, register, reminder, Google panels, …
