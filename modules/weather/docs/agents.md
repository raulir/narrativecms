# Weather module — agent notes

Orchestrates forecast UI + **`weather_history`**. Does **not** call Met Office / Open-Meteo directly — uses CMS **provider** modules.

## Provider pattern

CMS-level guide (add providers without copy-paste archaeology): [`modules/cms/docs/provider_pattern.md`](../../cms/docs/provider_pattern.md).

| Role | Module | Responsibility |
|------|--------|----------------|
| Domain | `weather` | Provider select, history write/read, BBC UI parse, sky icons |
| Provider | `metoffice` | DataHub Global Spot HTTP + raw file cache + 3h→1h blend |
| Provider | `openmeteo` | Open-Meteo HTTP + raw file cache |

### Config

- Provider modules declare `provides.weather_forecast` → `//forecast` panel.
- Weather panel field: `weather_provider` (`cms/cms_input_provides`, service `weather_forecast`).
- Call: `$ci->run_action($provider, ['do'=>'forecast', 'latitude'=>…, 'longitude'=>…, 'force_refresh'=>0|1, 'return_result'=>1])`.
- Standard response: `ok`, `source`, `attribution`, `fetched_at`, `from_cache`, `data` (Open-Meteo-shaped hourly/daily).

### Settings split

| Panel | Where | Fields |
|-------|--------|--------|
| `weather/weather` | **`item`** (edit on page panel) | `weather_provider`, location, lat/lon, labels |
| `metoffice/metoffice` | **`settings`** + admin **Weather → Met Office** | `api_key`, `cache_minutes` |
| `openmeteo/openmeteo` | **`settings`** + admin **Weather → Open-Meteo** | `cache_minutes`, `models` |

Enable modules **weather**, **metoffice**, **openmeteo** in CMS. Put Met Office key on Met Office settings, not weather. Do not add a CMS menu link for `weather/weather` — its definition has no `settings` (see cms `agents.md`).

## Data

### Source of truth: `weather_history` (SQL)

- Schema: `modules/weather/schema/weather_history.json` — **one row per hour** per `location_key`.
- **Portable across sources:** physical columns + free-text `source` / `resolution` + optional `meta`.
- **Write (weather only):** past hours insert-once / freeze (`is_final=1`); current+future upsert.
- **Read:** day chips + hourly cards built **from DB** after sync (provider file cache is network only).
- **Consumers:** energy **`forecast_usage`** reads `temp_c` (read-only) for planned house load / greenhouse test rule.
- **Daily fill:** missing **current/future** hours only → **09–20** day high, **21–08** night low (`resolution=daily_fill`). Never invents past hours (those only freeze from real API slots).

### Provider caching

- Each provider owns its cache strategy (raw API JSON files under `cache/`).
- Weather never writes provider caches.
- **`weather/refresh_weather`:** normal poll **honours** provider `cache_minutes` (network only when TTL expired). `?force=1` bypasses TTL. Same idea as `energy/refresh_energy`.
- **FE ownership:** big weather poll is driven by the **header menu** (`data-refresh_api=weather/refresh_weather`) on any page, not only the weather tab — so energy can keep refreshing while weather is open and vice versa.
- Providers: fresh file within TTL → cache; expired/force → network; stale file only if request fails.

## UI

- Top: **6 equal day chips + Later**; weather day **06:00** London
- Day detail: **24 hour columns** — temp + sky + rain %, wind mph
- **Sky icons:** emoji stacks with `.weather_sky_stack_*` classes
- Cheltenham default **51.89, −2.12**

## CMS

- Panel `weather/weather` on page slug `weather` (provider/lat/lon/labels on the page panel)
- Front menu: ENERGY + WEATHER chips
- Admin: Weather → Met Office / Open-Meteo (provider credentials only)
