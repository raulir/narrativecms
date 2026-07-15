# Analytics module backlog

Source detail: [`analytics.md`](analytics.md). Page-cache / shared beacon issues that need CMS core: also listed in [`../../cms/docs/todo.md`](../../cms/docs/todo.md) § Page cache.

**Legend:** `[ ]` open · `[x]` done

---

- [ ] **Language fallback for beacon** — `analytics_get_beacon_language()` uses only the `language` cookie; fall back to PHP session / `$GLOBALS` language when cookie is empty (bots and cache HITs often have no cookie).
- [ ] **Drop pageview geo columns** — remove `country`, `region`, `city`, `geo_resolved` from `cms_analytics_pageview` (geo lives on sessions only). CMS schema tool does not auto-drop columns; manual ALTER after removing from `cms_analytics_pageview.json`.
- [ ] **Turnstile fallback for blocked JS beacon** — on first `do=hit` failure, hidden Cloudflare Turnstile + `get_ajax` to beacon `panel_action`; `verified` column; keys in Analytics settings. Full design: [`analytics.md`](analytics.md) § Backlog — Turnstile fallback.

Cross-installation GeoIP RPC (consume CMS peer): design in [`cms/docs/todo.md`](../../cms/docs/todo.md) § Cross-installation RPC.

---

## Panel JavaScript — `*_ok` / `*_destroy`

- [ ] **`*_ok` refactor — analytics module** — dashboard, beacon, …
