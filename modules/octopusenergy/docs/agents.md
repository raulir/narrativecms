# Octopus Energy module — agent notes

Provider for official Agile **prices** and meter **usage**. Does **not** write `energy_history`.

## Provides

| Service | Panel |
|---------|--------|
| `energy_price` | `octopusenergy/price` |
| `energy_usage` | `octopusenergy/usage` |

## Settings (`octopusenergy/octopusenergy`)

`api_key`, `account_number`, `mpan`, `meter_serial`, `device_id` — never commit keys.

## Caches (provider-owned)

- Rates: existing product/region JSON under `cache/`
- Mini samples: `cache/energy_usage_mini_{device}.json`
- REST half-hours: `cache/energy_usage_rest_{mpan}_{serial}.json`
- GraphQL JWT: `cache/energy_graphql_token.json` (sha256 of api key, **not** the raw key)
- Device discovery: `cache/energy_graphql_device.json`

### GraphQL auth (`obtainKrakenToken`)

- JWT reused **across HTTP requests** for **`GRAPHQL_TOKEN_TTL_SEC` (30 min)** — L1 static + L2 disk. Do **not** call login on every Mini poll.
- Login failure (incl. **KT-CT-1199** on token mutation) → **`auth_backoff_until`** for **`USAGE_AUTH_BACKOFF_SEC` (5 min)**; Mini skips network while active.
- Telemetry **AUTHORIZATION** (expired/invalid JWT): **one** invalidate + re-login + retry, then **5 min** auth backoff if still failing.
- Telemetry **KT-CT-1199** (points/request pressure): mini-cache `graphql_backoff_until` (**`USAGE_RATE_BACKOFF_SEC`**, 5 min) — separate from login backoff.
- REST prices/consumption use **HTTP Basic** API key — no Kraken JWT.

### Usage poll TTLs (PHP constants in `octopusenergy_model.php` — not CMS yet)

| Constant | Default | Role |
|----------|--------:|------|
| `USAGE_POLL_MIN_SEC` | 120 s | Mini GraphQL min gap |
| `USAGE_MAIN_MAX_SEC` | 3600 s | Main live window (≤1 h) |
| `USAGE_GAP_FILL_MAX_SEC` | 3600 s | One backfill window (≤1 h toward REST) |
| `USAGE_GAP_FILL_MIN_SEC` | 60 s | Default min gap between Mini gap-fills (CMS **`gap_fill_min_sec`** overrides; clamp 30–3600) |
| `USAGE_REST_MIN_SEC` | 1800 s | REST consumption min gap |
| `USAGE_REST_MAX_FETCH_SEC` | 21600 s | Max REST ask window (6 h from last tip forward) |
| `USAGE_RATE_BACKOFF_SEC` | 300 s | After telemetry KT-CT-1199 |
| `USAGE_AUTH_BACKOFF_SEC` | 300 s | After login fail / reauth exhausted |
| `GRAPHQL_TOKEN_TTL_SEC` | 1800 s | Disk JWT reuse (30 min) |

Mini cycle: **main** live tail + **always try gap-fill** (no live_ok / thin-span gate). Gap-fill no-ops when Mini already meets REST frontier, points low/blocked (known budget only), or **gap_fill_min_sec** not elapsed (CMS, default 60 s). One gap-fill ≤ **1 h** backward per attempt.

**Stuck demand chip (e.g. constant kW):** chip is Mini **samples** only. If `graphql_backoff_until` or `auth_backoff_until` is active, main never runs → samples freeze. Check mini cache + token file.

**Gap-fill debug:** mini cache field **`gap_fill_last_skip`**: `min_interval` | `no_samples` | `no_hole` | `no_token` | `points_blocked` | `points_low` | `window_short` | `attempt` | `ok` | `ok_empty` | `rate_limited` | `auth_failed`. Also `gap_fill_last_window` when an attempt runs.

**Gap-fill rate (`last_gap_fill`):** claimed **atomically** on the mini JSON file (`flock` LOCK_EX: re-read → compare → write `now` → unlock) **before** gap-fill GraphQL. Concurrent `refresh_energy` processes cannot both pass the min interval. In-memory-only update was not a lock (two PHP requests could both poll the same window).

`rateLimitInfo` **fail-open**: missing/unknown points budget no longer blocks gap-fill (old fail-closed `isBlocked=true` could skip forever while main still worked).

### Agile rates TTL (`_cache_needs_refresh`)

| Condition | TTL |
|-----------|-----|
| Normal | **30 min** since `fetched_at` |
| After 16:00 London and day-ahead horizon incomplete | **5 min** |

Do **not** use rates **`display`** mode on the `refresh_energy` path (that served any existing file cache forever and froze official prices).

**Kiosk-wide API polling, force rules, and `cache/apis.log` format:** [`modules/energy/docs/apis.md`](../../energy/docs/apis.md).
