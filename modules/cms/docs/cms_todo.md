# CMS todo / planned features

Backlog and design notes for core CMS functionality not yet implemented.

## Cross-installation RPC

Planned mechanism for one CMS installation to request work from another, RPC-style, over HTTPS. Each installation has an **installation API key** (or key pair); requests are authenticated and limited to an allowlist of operations.

### Use cases

**GeoIP lookup (analytics)**

- Site A has no GeoIP database uploaded in **Analytics settings** (or prefers not to host MaxMind data).
- Site B has the database and exposes a GeoIP resolve endpoint.
- Analytics `_resolve_geo()` on site A can call site B; results still pass through `cache/analytics_geo_cache.json` on site A.
- Request carries anonymised IP only (same format as stored in `cms_analytics_pageview`).

**Video encode offload**

- Site A runs on a basic CPU vhost ([`cms_video.md`](cms_video.md) queue is local ffmpeg).
- Site B has GPU / faster ffmpeg and registers as a remote encode peer.
- Site A uploads or grants temporary access to source mp4; site B runs encode, returns DASH assets or signals completion for pull.
- Keeps existing `cache/video_queue.json` model; adds optional **remote worker** step before or instead of local `process_encode_queue()`.

### Rough design constraints

- Installation identity + API key in site config (or CMS settings panel); keys rotatable.
- Explicit opt-in on **provider** installation (which RPC services it offers: `geoip`, `video_encode`, …).
- **Consumer** config: peer URL + key + enabled services.
- Timeouts, size limits, and audit log for RPC calls.
- No silent fallback — missing peer or failed RPC must be obvious in admin (same philosophy as rest of CMS).

### Related code today

| Area | Location |
|------|----------|
| Local GeoIP | `modules/analytics/models/analytics_model.php` — `_lookup_maxmind()`, `_resolve_geo()` |
| Geo data file | Analytics settings → GeoIP database (`img/` upload; help text in settings panel) |
| Video queue / cron | [`cms_video_model`](../models/cms_video_model.php), [`cms_video_encode`](../panels/cms_video_encode.php) |
| Public API routing | [`system/cms.php`](../../../system/cms.php) — `modules/<module>/api/<id>.php` |

### Implementation order (suggested)

1. Core RPC auth + request/response envelope in `cms` module (shared by all services).
2. GeoIP provider API on installations that host a GeoIP database file.
3. Analytics consumer path in `_resolve_geo()` when local mmdb missing.
4. Video encode remote worker protocol (heavier; build on same RPC layer).