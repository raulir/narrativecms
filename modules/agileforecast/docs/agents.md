# Agile Forecast module — agent notes

Provider for **energy_price_forecast** (agileforecast.co.uk). Does **not** write `energy_history`.

## Provides

- `energy_price_forecast` → `agileforecast/forecast`

## Settings

- `cache_minutes` (default **120** / 2 h) — raw file-cache TTL; FE poll does not force refresh

## Cache

- `cache/agileforecast_raw_{REGION}.json`
