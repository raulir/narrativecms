# Agile Forecast module — agent notes

Provider for **energy_price_forecast** (agileforecast.co.uk). Does **not** write `energy_history`.

## Provides

- `energy_price_forecast` → `agileforecast/forecast`

## Settings

- `cache_minutes` (default **120** / 2 h) — raw file-cache TTL; FE poll does not force refresh

## Cache

- `cache/agileforecast_raw_{REGION}.json`
- TTL **`cache_minutes`**. Network only when the file is older than TTL (or missing).
- HTTP / invalid JSON / no `prices`: **keep** the previous file (any age) and return it.
- HTTP ok with a **shorter** future map: **merge** — keep the longer cached tail; new overlapping slots win.
- Energy history: domain writes **all future** forecast slots (14 d cap). Not clipped to the kiosk graph window.
