# Access keys and panel access

Frontend and CMS access control: keys on users, page/panel gates, and how denied access is handled.

Implementation: [`cms_access_model.php`](../models/cms_access_model.php).

Related: [`user_auth_overview.md`](../../user/docs/user_auth_overview.md), [`user_register.md`](../../user/docs/user_register.md), [`system.md`](system.md).

---

## Concepts

### Access keys

Strings such as `music_student`, `music_*`, `cms_pages`, `*`.

- Stored on **frontend users** (`user/user` → Access repeater: each row has `key`)
- Also kept in session as `access_keys` after login (`refresh_user_session`)
- Matching is bidirectional wildcard: user key `music_*` matches required `music_student`, and required `music_*` matches user `music_student` ([`_access_key_matches`](../models/cms_access_model.php))

### Soft redirects

Access login redirects use **HTTP 302** only. Project rule: never permanent redirects (301/308) for navigation or access control (see [`agents.md`](agents.md) — HTTP redirects).

---

## Frontend user keys

| Source | When |
|--------|------|
| **Default access** | `user/user_settings` → Default access repeater — applied on register (`create_user`) |
| **Login grant** | Each module `config.json` → `login_access` (e.g. `music_student`) — merged on login via `refresh_user_session()` |
| **Admin edit** | CMS user editor Access repeater on `user/user` |

Session: `$_SESSION['user']` + optional `$_SESSION['access_keys']`.  
API: `get_session_access_keys()`, `user_has_access($required)`, `parse_access_keys($user)`.

Without the **user** module, session keys may still come from legacy `$_SESSION['user']` / `access_keys` if set; there is **no** login UI.

---

## Page access

- CMS page field **access**: comma-separated keys (e.g. `music_student, music_teacher`)
- `user_has_page_access` / `enforce_page_access`
- Denied **with user module**: soft redirect to **Link to login** (`user/user_settings` → `login_link`) via `_reject_access`
- Denied **without user module**: no login redirect (gate returns; page is not forced to a fake login URL)

Guest redirect URL helper: `user_model::get_login_redirect_url()` / `_get_login_redirect()`.

---

## Position layout pages

When a page position (header/footer) points at another page, that page’s `access` is checked while building `page_config`.

Denied → `_inline_access_denied` → short “Access denied” HTML in that position (`get_access_denied_inline_html`), not a full login redirect and not a blank skip comment.

This is separate from panel `access_blocked`.

---

## Panel access (definition)

On the panel definition JSON (top-level):

```json
{
  "access": "music_*",
  "access_blocked": "skip"
}
```

| Property | Meaning |
|----------|---------|
| `access` | Required key pattern. Empty or `*` = public |
| `access_blocked` | What to do if the user lacks the key: **`login`** (default) or **`skip`** |

### Decision matrix

| Condition | Result |
|-----------|--------|
| No required access / `*` | Allow |
| User has matching key | Allow |
| Denied + **user module absent** | **Always skip** (even if `access_blocked` is `login`) |
| Denied + user module + `access_blocked` empty/`login` | **Login** — soft 302 / JSON to login link |
| Denied + user module + `access_blocked` = `skip` | **Skip** — comment only |

### Skip behaviour

- HTML: `<!-- panel "module/panel" access skipped -->` only (no real element)
- No panel controller, template, `panel_action`, or panel JS/CSS
- Applies to: page placing (`render` → `panel`), `_panel()` / `_panel_id()`, `ajax_panel` / ajax API
- Ajax `no_html` + skip: empty result, action not run

### Login behaviour

- Soft **302** to `login_link` (user module settings)
- Ajax `no_html`: JSON `{ error: { message: 'access_denied', login_url, login_text } }`
- May clear session if user was “logged in” but lacked the key

### API

| Method | Role |
|--------|------|
| `get_panel_access_meta($panel_name)` | `{ required, blocked }` — request-cached |
| `get_panel_required_access($panel_name)` | Required key string (BC) |
| `check_panel_access($panel_name)` | `'allow'` \| `'login'` \| `'skip'` |
| `enforce_panel_access($panel_name, $params)` | `true` allow; `false` skip; login mode exits via `_reject_access` |
| `get_panel_access_skipped_html($panel_name)` | Skip comment HTML |
| `get_cache_access_hash($panel_name)` | Panel HTML cache key fragment by session keys when access required |

Pass `$params['_access_ok'] = 1` after a successful enforce to avoid a second check in nested `panel()` (used by `ajax_panel` / `_panel`).

---

## CMS admin access

Separate from frontend users:

- `$_SESSION['cms_user']` and module `config.json` **access** list / menu items
- Admin menu filtering in `cms/cms_menu` (cms_user keys, not frontend `user/user` keys)
- Admin panels and operations use their own checks

---

## Panel HTML cache

When a panel has required `access`, the panel cache filename includes `get_cache_access_hash()` so guest vs authorised users do not share the wrong HTML (full panel vs skipped comment).

---

## Examples

**Hard gate (default login)** — e.g. exercise engine:

```json
"access": "music_*"
```

Guest with user module → soft redirect to login.

**Optional block on a public page** — show only for members:

```json
"access": "music_*",
"access_blocked": "skip"
```

Guest still sees the rest of the page; that panel slot is only the skip comment.

**Site without user module** — any denied panel always skips; never login redirect.

---

## Quick reference: grant keys on register/login

1. `user/user_settings` → Default access (new users)  
2. Module `config.json` → `login_access` (merged every login)  
3. Edit user Access repeater in CMS for one-off grants  
