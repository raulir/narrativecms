# User module backlog

Auth, registration, reminder, Google, settings. CMS mail/platform: [`../../cms/docs/todo.md`](../../cms/docs/todo.md).

Sources: [`auth_email.md`](auth_email.md), [`auth_google.md`](auth_google.md), [`user_register.md`](user_register.md), [`user_auth_overview.md`](user_auth_overview.md).

**Legend:** `[ ]` open · `[x]` done

---

## Urgent

Site review 2026-11-20 (CMS `review.md`; the items with their evidence and fixes are in `ai/next_big_plan.md`):

- [x] **Item 1. The reset mail linked to a posted address** (done 2026-11-20). `user/reminder` built the link from the posted `url` and had no `ajax.input`, so anyone could have a genuine reset mail send its token to their own site. Now `user_model::reminder_link()` (this site plus the reminder page) and `new_token()` (64 hex from `random_bytes`, also for verification and `user/loginlink`). [`auth_email.md`](auth_email.md). Check: `ai/tmp/reminder_link_check.php`, `ai/tmp/reminder_check.js`.
- [x] **Item 4. Registration and login** (done 2026-11-20; [`user_auth_overview.md`](user_auth_overview.md) § Passwords, § Emails in lowercase, § Limits and sessions; [`user_register.md`](user_register.md) § What the server takes). Register takes its rules from the stored block only and keeps only its fields; one password rule (8, a letter, a number); one login error; limits on login, reminder, resend and register with a fail2ban line; password change asks for the current password; a change or reset ends the other sessions; logout gives a new session id; token files under a lock; emails lowercase. Check: `ai/tmp/user_auth_check.js`. Live: copy, then `ai/user_auth_texts_setup.php` and `ai/user_email_lowercase.php --write` (with your word).
- [x] **Item 5. Sign-in forced by someone else, kept session ids** (done 2026-11-20, with subscription; [`user_auth_overview.md`](user_auth_overview.md) § Sign-in only from our own pages). `auth_google` signs in only on our button's post (`cms_do=google` plus the session's state), `auth_gapp` only on the app's ajax post on the app host; login, register and both Google sign-ins refuse another site's `Origin` (`user_model::request_from_own_site()`). The confirmation link signs in only in the browser that asked for the mail, with a new session id; the browser hand-off regenerates too. `success_url` escaped in the templates. Check: `ai/tmp/signin_origin_check.js`.
- [x] **Dead code** (removed 2026-11-20, site review item 10): `user_model::get_current_tempuser`, `user_model::get_users_by_group`, `user_google_model::login_from_app_profile` (nothing called them).
- [ ] **The browser hand-off link can be passed on.** A signed-in app's `subscription/enter/?app_enter=` link (5 minutes, single use) signs in whichever browser opens it, so someone could send a visitor their own. Binding it would need a "Continue as …?" screen in the browser. Low; noted in item 5.

- [x] **`user/register` creates a user from an empty post** (fixed 2026-11-20 with item 4: rules from the shown block only, `bad_form` without one) — field rules (`show_email`, `show_password`, `show_username`, …) come only from the register block. An ajax post `panel_id=user/register&cms_do=register` without `cms_page_panel_id` merges no block, so nothing is required and `create_user()` stores a user with no email, username or password. Two such rows were created by a local test on 2026-11-06 (deleted). Fix: require a shown `user/register` block (or fall back to its settings with safe defaults) and refuse when email / username and password are empty; add `ajax.input`

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
- ~~Rate limits on register + reminder~~ (done 2026-11-20, item 4)
- Honeypot or CAPTCHA on register
- CMS settings guide for email auth
- `profile.tpl.php` audit — legacy register markup; is `user/profile` deployed?
- Consent `text` on register — enforce, not display-only
- **Native app** `user/auth_gapp` — panel_id, `panel_action`, bridge docs — [`auth_google.md`](auth_google.md)

Async mail queue is tracked under CMS: [`cms/docs/todo.md`](../../cms/docs/todo.md) § Email.

---

## Panel JavaScript — `*_ok` / `*_destroy`

- [ ] **`*_ok` refactor — user module** — login, register, reminder, Google panels, …
