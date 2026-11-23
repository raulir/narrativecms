# Basic module backlog

Frontend utility panels (language, vimeo, lightbox, …). Site menu lives in the separate **`menu`** module (`menu/menu`).

**Legend:** `[ ]` open · `[x]` done

---

- [x] **`console_log` wrote any posted text to `log/console.log`** (done 2026-11-20, site review item 2 in `ai/next_big_plan.md`). It now has `"access": "basic_base"`, so it runs only for a signed-in CMS user (debugging a device means signing in to the CMS on it) and is skipped quietly for everyone else. One line per message (newlines become spaces), at most 2,000 characters, written with a lock; `panel_action` returns `$params`.
- [x] **Consent** (done 2026-11-20, same review, item 10). `gtag.tpl.php` had the condition inverted (it loaded before any choice and after "no tracking", but not after acceptance), and `pixel.js` and `gtm.js` ignored `gdpr_notrack`. Now the three templates print the container with `data-consent="1"` when the gdpr module is on, and `basic_tracking_allowed()` (in each of `gtag.js`, `gtm.js`, `pixel.js`) loads the tracker only after the visitor closed the gdpr popup (cookie `gdpr`) without choosing no tracking (`gdpr_notrack`). The decision is in the browser because these pages can come from the page cache. Without the gdpr module the trackers load as before. Accepting in the popup takes effect from the next page.

## Panel JavaScript — `*_ok` / `*_destroy`

Contract: [`cms_panel_js.md`](../../cms/docs/cms_panel_js.md).

- [ ] **`*_ok` refactor — basic module** — `language_init`, `audioplayer_init`, vimeo, lightbox, …

## Languages (frontend leftovers)

Stale `basic/language` DB data cleanup is tracked with CMS Languages work: [`cms/docs/todo.md`](../../cms/docs/todo.md) § Languages.
