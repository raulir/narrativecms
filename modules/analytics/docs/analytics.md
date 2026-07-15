# Analytics module

First-party, self-hosted pageview tracking for the CMS. Lightweight beacon API (no full CI bootstrap), MariaDB storage, admin dashboard with geo and charts.

## Site config flags

| Key | Default | Purpose |
|-----|---------|---------|
| `beacon` | on (1) if omitted | Native tracker. Set `"beacon": 0` in `config/<host>.json` to disable. |
| `analytics` | per-site | Google/third-party only (gtag, legacy GA, GTM, pixel). Unchanged for backwards compatibility. |

## Setup

1. Enable module **analytics** in **Site settings → Modules**.
2. Run **CMS → Schema** (fix module **analytics**). On a fresh install this creates pageview / session / php staging tables. After upgrading, re-run Schema so pageview `user_id` and session `user_id` / `username` / `meta` columns exist. When `cms_analytics_visit` still exists, the schema tool migrates it automatically (`migrate_from` in `cms_analytics_pageview.json` — renames table, columns, and indexes).
3. Upload **GeoLite2-City.mmdb** in **Tools → Analytics settings → GeoIP database** (download from [MaxMind GeoLite2](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) — free account required). The file is stored under `img/`. Without it, geo reports on the dashboard show an error. The mmdb is per-installation data — not shipped with every CMS distribution.
4. Embed panel **analytics/beacon** on the site layout (same way as gtag). JS loads automatically as panel JS (`beacon.js`).
5. Add **analytics/analytics_process** under **Repeating tasks** for background session assignment, aggregation, and geo resolve (replaces the old `analytics_geo_resolve` task if present).

## Admin

- **Tools → Analytics** — dashboard (7-day dual-axis chart, last 50 sessions, last 50 pageviews, top pages, geo top 50). Opening the dashboard runs **analytics_process** first; all pageview stats and charts read **`cms_analytics_pageview` only** (php staging is not queried). Last-50 sessions show **User** (login email/username) when the user module is installed. **Details** on each session/pageview row opens a floating panel with the full database row (including **User agent**, session **user_id** / **meta**, pageview **user_id**).
- **Tools → Analytics settings** — delay (ms), session minutes, collect engagement, GeoIP database file, GeoIP diagnostics (show debug block on dashboard, default No).
- **analytics/beacon** panel (per instance on page or layout position) — **JS tracking** (default No), **PHP tracking** (default Yes). Edit on the embedded panel block (e.g. **Pages → footer → Beacon**), not under **Analytics settings**.

## Beacon session

- Cookie **`beacon`** — visitor session cookie (separate from PHP session). Stored on each pageview as **`beacon_id`** at hit time.
- **`session_id`** on pageviews is set by **analytics_process** once the pageview is assigned to a row in **`cms_analytics_session`**. Dashboard shows first 8 chars of `md5(session_id)` in the Session column.
- **`pageview_token`** — single pageview + heartbeats only.
- **Session minutes** in Analytics settings (default 60): sliding expiry, refreshed on each `do=hit`. `0` = browser session cookie.

### Language on pageviews

| Path | Language source |
|------|-----------------|
| **JS beacon** | Cookie `language` only (`analytics_get_beacon_language`) |
| **PHP tracking** | CMS request language: `$GLOBALS['language']['language_id']` after targets (cookie → config → Accept-Language → first allowed), else content/default language |
| **PHP + JS merge** | If the main JS row has empty language and the php row has a value, process copies PHP language onto the main pageview |
| **Session** | Latest non-bot pageview language preferring **non-empty** (`ORDER BY (language = '') ASC, updated DESC`) |

### Session source (`cms_analytics_session.source`)

Recomputed on each session sync:

| Value | Dashboard | Meaning |
|-------|-----------|---------|
| `beacon` | Normal | Continuous beacon path (default) |
| `php` | PHP only | All non-bot hits have viewport 0×0 (no JS engagement hits) |
| `ip_ua` | IP+UA | More than one distinct `beacon_id` in the session (orphan cluster) |

Priority when computing: **ip_ua > php > beacon**.

### How `session_id` is assigned (`analytics_process`)

Only pageviews with **empty `session_id`** (and `bot = 0`) are assigned. Rows that already have a `session_id` from a working cookie/beacon path are **never** reassigned or merged by IP+UA.

For unassigned non-bot pageviews (grouped by `beacon_id`):

1. **Beacon key (primary)** — normally `session_id = beacon_id` (cookie continuity).
2. **IP + user agent fallback (orphans only)** — among **still-unassigned** pageviews only, same **`ip_anonymised`** + **`user_agent`** within **session minutes** of each other (if session minutes is `0`, a **60 minute** window is used for this match only) are clustered. The **oldest `beacon_id`** in that cluster becomes the shared `session_id` for all of them. Empty IP or empty UA is never used.

**Not done:** attaching a new beacon into an **existing** session that already has assigned hits (e.g. another user on the same IP with a working cookie). Two visitors with different beacon cookies therefore stay two sessions even on the same IP+UA.

Bot rows are excluded. Orphan clusters that share IP+UA can still false-merge (NAT + same browser); that only affects unassigned traffic.

## Session table (`cms_analytics_session`)

Cached aggregates per session: started, last activity, pageview count, total seconds, final language, first/last page, geo and **user agent** from the **first** pageview. Updated by **analytics_process** (cron and on each dashboard load). Beacon only writes pageviews.

### User identity (optional `user` module)

When the **user** module is installed, analytics stores CMS login identity. If `user` is not in site modules, all of the following stay empty/`0` and no PHP user session is read.

| Store | Columns | Notes |
|-------|---------|--------|
| **Pageview** | `user_id` | Set at hit time when visitor is logged in (`$_SESSION['user']`). Guests → `0`. |
| **Session** | `user_id`, `username`, `meta` | Aggregated on session sync. Last-50 table shows **User** (`username`) only; Details shows all three. |

**Capture**

| Path | How `user_id` is set |
|------|----------------------|
| PHP tracking | `record_php_pageview()` → `cms_analytics_pageview_php.user_id` via `analytics_current_user_id()` |
| PHP → main | On match: fill main `user_id` if empty; on promote: copy from php row |
| JS beacon API | `analytics_insert_pageview()` may start PHP session lightly (same `session_name` rules as `session.php`) and read logged-in user — not baked into HTML |

Do not put `user_id` in cacheable page HTML (`data-*`).

**Known gap:** full page-cache HIT without `panel_action` and without a usable session cookie on the beacon request can leave pageview `user_id = 0` until a later PHP-tracked or session-aware JS hit. Session sticky identity still applies once any hit has a user id.

**Session sticky rules**

- Latest non-zero pageview `user_id` (by `updated`) becomes the session’s current user.
- Pageviews with `user_id = 0` (logout) **do not** clear session `user_id` / `username`.
- Display name: `user/user_settings` — if **Login username** / `show_username` is off → **email**, else **username** (same idea as `user_model` `loginname`). Re-resolved on each sync (latest name only).
- When session `user_id` changes from one non-zero id to another, append a meta line: `Other user id: <previous_id>` (shared device / multiple logins). Append is driven by transition vs **stored** session row so process re-runs do not spam meta.

## User agent and bot handling

- **`user_agent`** (VARCHAR 500) is stored on every **`cms_analytics_pageview`** row (JS beacon API and promoted PHP rows).
- **`cms_analytics_session.user_agent`** is copied from the first non-bot pageview on session sync.
- **`bot`** (TINYINT, default 0) on **`cms_analytics_pageview`**: set on JS `do=hit` when viewport is **0×0** or the user agent matches **`analytics_is_bot_user_agent()`** (empty UA, `curl`, `googlebot`, `go-http-client`, `scandash`, `pr-cy`, `cms-checker`, `forestengine`, etc.). JS hits are always stored; bot rows are excluded from sessions, charts, and totals.
- **PHP tracking** still skips recording when **`analytics_is_bot()`** (server UA only — no viewport).
- **`analytics_process`** deletes bot pageviews older than **300 seconds** (last step each run). PHP dedup: if a matching main row exists (including a JS bot row), the php staging row is dropped only.
- Dashboard **Details** on pageviews shows **`bot`** (0/1). Bot rows may appear briefly in the last-50 list before purge.

## Per-panel tracking (`analytics/beacon`)

Each beacon panel instance controls how that embed tracks:

| Setting | Default | Effect |
|---------|---------|--------|
| **PHP tracking** | Yes | `panel_action` writes **`cms_analytics_pageview_php`** whenever this panel is rendered — full page load or partial position load |
| **JS tracking** | No | `beacon.js` loads but sends **no** API requests unless Yes |

Recommended layout: public/footer pages → PHP yes, JS no (pageviews only, no scroll/time); engine/interactive pages → JS yes.

**PHP path** (when **PHP tracking** is Yes):

- **`panel_action`** records navigation from `cms_request_uri` on any render that includes the panel (full page or position).
- **`music/prepare`** records `engine/unit/{unit_id}` whenever **PHP tracking** is Yes (JS may also record when **JS tracking** is Yes; process dedupes).
- Visitor key: cookie `beacon` → else `$_SESSION['analytics_beacon_id']` → else new UUID; persisted to cookie + PHP session on server record only (beacon API does **not** use PHP session).
- **`analytics_process`** runs php normalisation first: on rows older than **30 seconds**, if a matching main row exists (same `beacon_id` + `page` within ±30s), **delete** the php row; otherwise **promote** into `cms_analytics_pageview` and delete the php row. Session sync then uses the main table only — php staging stays nearly empty between cron runs.

Does not run when **page cache** serves HTML without bootstrapping CI (no `panel_action`).

**JS path** (when **JS tracking** is Yes):

- Template exposes `data-beacon_id`, delay, and collect engagement; `beacon.js` POSTs to `analytics/beacon`.
- Global **collect engagement** in Analytics settings applies only when JS tracking is on.

## Beacon behaviour

- First POST `do=hit`: page path, viewport size, anonymised IP (server); optional POST `beacon_id`; sets/refreshes `beacon` cookie.
- Heartbeats at 5s, 10s, 20s, 30s, 60s, 120s, 180s, 240s, 300s when engagement enabled.
- Single page mode (position ajax navigation): new pageview via `cms_position_link_after` hook (no unload beacons).
- Bot-like JS hits recorded with **`bot = 1`** (see **User agent and bot handling** above).

## Page path normalisation

All hits (JS beacon, PHP tracking, virtual `beacon_pageview`) pass through **`analytics_normalise_page()`** at **record time**:

- Leading `/`
- Trailing `/` (CMS canonical `/<slug>/`), except home stays `/`
- Strip query string and hash if present
- Strip `/index.php` prefix

No cron rewrite of stored paths — only new hits are canonical.

## Virtual pageviews

Other modules can record pageviews without a full navigation. Call from JS when the beacon panel is on the layout and **JS tracking** is Yes (otherwise `beacon_pageview` is a no-op):

```javascript
if (typeof beacon_pageview === 'function') {
  beacon_pageview(window.location.pathname)
}
```

**Music unit sets:** after start, the browser URL is the unit public slug (e.g. `/1a-counts-and-rhythm/`). JS records that path via `beacon_pageview`; **`music/prepare`** records the same slug path when **PHP tracking** is on (via `music_model::get_unit_public_path()`). No separate `engine/unit/{id}` path.

## GeoIP

- Database file path from **Analytics settings → GeoIP database** (`img/` upload).
- Lookup only in **analytics_process**, not on beacon. Resolved country/region/city stored on **`cms_analytics_session`** only (legacy geo columns on pageviews are unused).
- Cache: `cache/analytics_geo_cache.json` (30-day TTL per IP entry).
- **GeoIP diagnostics** on the dashboard (bottom block) is off by default; enable in Analytics settings when debugging.
- `_resolve_geo()` is abstracted so a future third-party or **cross-installation CMS RPC** GeoIP endpoint can replace local MaxMind behind the same cache (see [`todo.md`](../../cms/docs/todo.md) § Cross-installation RPC).

City-level accuracy is approximate (metro area), not street-level. Anonymised IP (/24) is sufficient for aggregate reporting.

**Local/private visitors** store the full IP (not anonymised). Dashboard shows country **Localhost**, area from the range (`127`, `10`, `192.168`, `172.16`, `::1`, …), and city as the exact address.

## Backlog — Turnstile fallback for blocked JS beacon

**Status:** not implemented — design only.

**Problem:** On pages with **JS tracking = Yes**, `beacon.js` POSTs to the lightweight `analytics/beacon` API. Ad blockers and privacy tools often block that URL. The hit fails silently today (`.catch(() => {})`), so real users can be missing from analytics even though they are not bots. PHP tracking covers full page loads when CI runs, but not cache HITs or JS-only paths.

**Idea:** Keep all behaviour inside **`analytics/beacon`** (panel JS + `panel_action`). On the **first pageview only**, if the `do=hit` `fetch` fails (network / blocked) or returns no `pageview_token` (but not a clean 204 when beacon is disabled), run **Cloudflare Turnstile** (invisible/hidden widget). If Turnstile succeeds, recover the pageview via **`get_ajax('analytics/beacon', …)`** → `panel_action` with `do=turnstile_hit`, which verifies the token server-side and inserts the row. Ad blockers are less likely to block `ajax_api/get_panel` than `/analytics/beacon`.

**Flow:**

1. Normal path unchanged: `fetch` `do=hit` → `pageview_token` → heartbeats.
2. Failure path (once per page load): load Turnstile only when configured → `turnstile.execute()` → POST token to beacon panel ajax.
3. Server: `POST https://challenges.cloudflare.com/turnstile/v0/siteverify` (secret from settings); on success insert pageview with **`bot = 0`**.

**Settings** (planned: **Analytics settings**, global — like GeoIP):

- `turnstile_enabled` (Yes/No, default No)
- `turnstile_site_key`
- `turnstile_secret_key`

**Schema** (planned): add **`verified`** TINYINT on `cms_analytics_pageview` (default 0). Successful JS paths set **`verified = 1`** when **`bot = 0`** — both normal `fetch` hits and Turnstile-recovered hits. PHP-promoted rows stay **`verified = 0`**. Bot rows stay **`bot = 1`**, **`verified = 0`**, purged as today.

**Scope:**

- Only when **JS tracking = Yes** on the embed and Turnstile is enabled + keys set.
- Turnstile script loads **only after** beacon `fetch` failure (no extra third-party load for successful hits).
- Heartbeats stay on the lightweight API in v1; if that remains blocked after recovery, engagement may be missing but the verified pageview remains.

**Limitations:**

- Cannot distinguish ad-block vs offline vs server error — all trigger fallback when configured.
- Turnstile failure or second block → no pageview (by design: prefer under-counting bots over counting them).
- Requires Cloudflare Turnstile widget + server verify (similar integration pattern to reCAPTCHA in `modules/form`).

**Key files to touch when implementing:** [`beacon.js`](../js/beacon.js), [`panels/beacon.php`](../panels/beacon.php), [`templates/beacon.tpl.php`](../templates/beacon.tpl.php), [`helpers/analytics_api_helper.php`](../helpers/analytics_api_helper.php), [`api/beacon.php`](../api/beacon.php), [`definitions/analytics_settings.json`](../definitions/analytics_settings.json), [`schema/cms_analytics_pageview.json`](../schema/cms_analytics_pageview.json).

## Third-party alternatives

When this module is not enough:

- **Matomo CE** — full GA replacement, best bot filtering, PHP + MariaDB.
- **Umami / Plausible** — simple UI, separate Node/Elixir stack.
- **Open Web Analytics (OWA)** — PHP + MySQL, lighter than Matomo.