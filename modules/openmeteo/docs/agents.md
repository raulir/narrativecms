# Open-Meteo module — agent notes

Weather **provider**. Does **not** write `weather_history`.

## Provides

- Service: `weather_forecast` → panel `openmeteo/forecast`
- Weather module selects this via `cms/cms_input_provides` on `weather/weather`

## Settings (`openmeteo/openmeteo`)

- `cache_minutes` — raw API TTL (default 180)
- `models` — Open-Meteo models param (default `best_match`)

## Cache

- File: `cache/openmeteo_raw_{lat}_{lon}.json` (raw API JSON)
- `force_refresh=0`: serve cache if younger than `cache_minutes`; else network (stale only on fail)
- `force_refresh=1`: always network

## Call contract

```php
$ci->run_action('openmeteo/forecast', [
  'do' => 'forecast',
  'latitude' => 51.89,
  'longitude' => -2.12,
  'force_refresh' => false,
  'return_result' => 1,
]);
// → ok, source, attribution, fetched_at, from_cache, data{hourly,daily}
```
