# Analytics module

First-party, self-hosted pageview tracking for the CMS. Lightweight beacon API (no full CI bootstrap), MariaDB storage, admin dashboard with geo and charts.

## Site config flags

| Key | Default | Purpose |
|-----|---------|---------|
| `beacon` | on (1) if omitted | Native tracker. Set `"beacon": 0` in `config/<host>.json` to disable. |
| `analytics` | per-site | Google/third-party only (gtag, legacy GA, GTM, pixel). Unchanged for backwards compatibility. |

## Setup

1. Enable module **analytics** in **Site settings → Modules**.
2. Run **CMS → Schema** (fix module **analytics**). On a fresh install this creates `cms_analytics_pageview`. When `cms_analytics_visit` still exists, the schema tool migrates it automatically (`migrate_from` in `cms_analytics_pageview.json` — renames table, columns, and indexes).
3. Upload **GeoLite2-City.mmdb** in **Tools → Analytics settings → GeoIP database** (download from [MaxMind GeoLite2](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) — free account required). The file is stored under `img/`. Without it, geo reports on the dashboard show an error. The mmdb is per-installation data — not shipped with every CMS distribution.
4. Embed panel **analytics/beacon** on the site layout (same way as gtag). JS loads automatically as panel JS (`beacon.js`).
5. Optional: add **analytics/analytics_geo_resolve** under **Repeating tasks** for background geo backfill.

## Admin

- **Tools → Analytics** — dashboard (last 50 pageviews, top pages, geo top 50, 7-day hourly chart).
- **Tools → Analytics settings** — delay (ms), collect engagement (heartbeats), GeoIP database file.

## Beacon session

- Cookie **`beacon`** — links pageviews into one browsing session (separate from PHP session).
- **`session_id`** column stores the cookie value; dashboard shows first 8 chars of `md5(session_id)` in the Session column.
- **`pageview_token`** — single pageview + heartbeats only.
- **Session minutes** in Analytics settings (default 60): sliding expiry, refreshed on each `do=hit`. `0` = browser session cookie.

## Beacon behaviour

- First POST `do=hit`: page path, viewport size, anonymised IP (server); sets/refreshes `beacon` cookie.
- Heartbeats at 5s, 10s, 20s, 30s, 60s, 120s, 180s, 240s, 300s when engagement enabled.
- Position-link navigation: new pageview via `cms_position_link_after` hook (no unload beacons).
- Bot user-agents filtered on server.

## Virtual pageviews

Other modules can record pageviews without a full navigation. Call from JS when the beacon panel is on the layout:

```javascript
if (typeof beacon_pageview === 'function') {
  beacon_pageview('engine/unit/' + unit_id)
}
```

The music engine uses this when a user starts a new exercise set (`engine/unit/{unit_id}`).

## GeoIP

- Database file path from **Analytics settings → GeoIP database** (`img/` upload).
- Lookup only on dashboard/cron, not on beacon.
- Cache: `cache/analytics_geo_cache.json` (30-day TTL per IP entry).
- `_resolve_geo()` is abstracted so a future third-party or **cross-installation CMS RPC** GeoIP endpoint can replace local MaxMind behind the same cache (see [`cms_todo.md`](../../cms/docs/cms_todo.md)).

City-level accuracy is approximate (metro area), not street-level. Anonymised IP (/24) is sufficient for aggregate reporting.

**Local/private visitors** store the full IP (not anonymised). Dashboard shows country **Localhost**, area from the range (`127`, `10`, `192.168`, `172.16`, `::1`, …), and city as the exact address.

## Third-party alternatives

When this module is not enough:

- **Matomo CE** — full GA replacement, best bot filtering, PHP + MariaDB.
- **Umami / Plausible** — simple UI, separate Node/Elixir stack.
- **Open Web Analytics (OWA)** — PHP + MySQL, lighter than Matomo.