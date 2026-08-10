# Met Office module — agent notes

Weather **provider** for DataHub Global Spot. Does **not** write `weather_history`.

## Provides

- Service: `weather_forecast` → panel `metoffice/forecast`
- Weather module selects this via `cms/cms_input_provides` on `weather/weather`

## Settings (`metoffice/metoffice`)

- `api_key` — DataHub free Global Spot (360/day). Never commit.
- `cache_minutes` — raw API TTL (default 180)

## Cache

- File: `cache/metoffice_raw_{lat}_{lon}.json`
- Stores **raw** hourly + three-hourly GeoJSON bundles
- Converts to Open-Meteo-shaped hourly on read (3h→1h linear temp/wind/pop; sky mid-step)
- `force_refresh=0`: serve cache if younger than `cache_minutes`; else network (stale only on fail)
- `force_refresh=1`: always network

## Call contract

```php
$ci->run_action('metoffice/forecast', [
  'do' => 'forecast',
  'latitude' => 51.89,
  'longitude' => -2.12,
  'force_refresh' => false,
  'return_result' => 1,
]);
// → ok, source, attribution, fetched_at, from_cache, data{hourly,daily}
```
