# CMS provider pattern (`provides` + `run_action`)

How **domain modules** plug in **interchangeable third-party backends** without hard-coding module names. Use this doc to add a new provider from scratch (human or AI) without reverse-engineering shop/energy/weather.

Related: [cms_module_extends.md](cms_module_extends.md) (template/controller *extends* is different — see below), [agents.md](agents.md), [system.md](system.md).

---

## 1. Mental model

| Role | Owns | Does **not** |
|------|------|----------------|
| **Domain module** | UI, domain tables, orchestration, which *service* is needed | Vendor API keys, vendor HTTP, vendor file caches |
| **Provider module** | Credentials, HTTP to vendor, raw file cache, normalize to a **contract** | Domain UI, domain history tables, picking “which product page” |

```
Browser / kiosk
    → domain FE or domain API (e.g. energy/refresh_energy, weather panel)
        → domain model (energy_model, weather_model)
            → run_action('vendor/panel', { do, …, return_result: 1 })
                → provider panel_action
                    → provider model (HTTP + cache)
                        → standardized JSON
            → domain writes its own DB (energy_history, weather_history, …)
        → domain reads DB for UI
```

**Secrets stay on the provider.** Domain FE talks only to this CMS.

---

## 2. Registry: `provides` in `config.json`

### Declaration (provider module)

In `modules/{provider}/config.json`:

```json
{
  "name": "My Vendor",
  "version": "1.0",
  "panels": [
    {
      "id": "myvendor",
      "name": "My Vendor settings",
      "flags": ["hidden"]
    },
    {
      "id": "forecast",
      "name": "My Vendor forecast",
      "flags": ["hidden"]
    }
  ],
  "provides": [
    {
      "service": "weather_forecast",
      "panel": "//forecast",
      "label": "My Vendor forecast"
    }
  ],
  "cms_menu": [ … ]
}
```

`cms_menu` is optional. Link to `admin/panel_settings/{module}__{settings_panel}/` **only** when that panel’s definition has a non-empty **`settings`** array (credentials, cache minutes). Do not menu-link domain panels that only have **`item`** fields, or capability panels with empty `settings` — those open an empty editor. See [agents.md](agents.md) § Admin CMS menu.

| Field | Required | Meaning |
|-------|----------|---------|
| `service` | yes | Capability name (string). Domain code and admin dropdowns key on this. |
| `panel` | yes | Panel that implements the capability. |
| `label` | no | Human label in admin select (default = panel name). |

### Panel name resolution (boot)

Loaded in [`system/core/cms_config.php`](../../../system/core/cms_config.php) → `$GLOBALS['config']['provides']`.

| `panel` value | Becomes |
|---------------|---------|
| `//forecast` | `{this_module}/forecast` |
| `forecast` (no slash) | `{this_module}/forecast` |
| `other/panel` | left as-is |

Registry shape after boot:

```php
$GLOBALS['config']['provides']['weather_forecast'] = [
  'metoffice/forecast' => [
    'panel'   => 'metoffice/forecast',
    'module'  => 'metoffice',
    'service' => 'weather_forecast',
    'label'   => 'Met Office Global Spot',
  ],
  'openmeteo/forecast' => [ … ],
];
```

Only **enabled site modules** (site `modules` list) are scanned. Enable the provider module in CMS or it will not appear.

### Services already used in this codebase (examples)

| Service | Domain | Example providers |
|---------|--------|-------------------|
| `weather_forecast` | weather | metoffice/forecast, openmeteo/forecast |
| `energy_price` | energy | octopusenergy/price |
| `energy_usage` | energy | octopusenergy/usage |
| `energy_price_forecast` | energy | agileforecast/forecast |
| `shop_checkout` | shop | shopify/checkout |
| `subscription_checkout` | subscription | stripe/subscription_checkout |
| `ai` | cms (translations, etc.) | xai/ai |

**Name services by capability, not vendor** (`weather_forecast`, not `metoffice_api`).

---

## 3. Admin: pick a provider (`cms/cms_input_provides`)

On the **domain** panel definition (settings), add a field:

```json
{
  "type": "cms/cms_input_provides",
  "name": "weather_provider",
  "label": "Forecast provider",
  "service": "weather_forecast",
  "default": "openmeteo/forecast",
  "help": "Module panel that implements weather_forecast."
}
```

| Property | Role |
|----------|------|
| `type` | Always `cms/cms_input_provides` (CMS-level; prefer over deprecated `shop/cms_input_provides`) |
| `name` | Setting key stored on domain panel / cms_settings (e.g. `weather_provider`, `price_provider`) |
| `service` | Must match `provides[].service` |
| `default` | Optional full panel name `module/panel` |

Implementation: [`modules/cms/panels/cms_input_provides.php`](../panels/cms_input_provides.php) — builds dropdown from `$GLOBALS['config']['provides'][$service]`. Stored value is the **panel name string** (e.g. `octopusenergy/price`), not the module alone.

---

## 4. Invocation: `run_action`

### Call (domain model)

```php
// Prefer Controller instance
$CI = get_instance(); // or $this inside a Controller

$result = $CI->run_action('metoffice/forecast', [
  'do'             => 'forecast',   // or service name; provider panel checks this
  'latitude'       => 51.89,
  'longitude'      => -2.12,
  'force_refresh'  => 0,            // optional; provider TTL decides network if 0
  'return_result'  => 1,            // required for in-process use
  'no_html'        => 1,            // optional; domain helpers often set this
]);
```

- `run_action($panel, $params)` → `run_panel_method($panel, 'panel_action', $params)` ([`system/core/controller.php`](../../../system/core/controller.php)).
- With **`return_result => 1`**, the provider **returns** an array (merged with params). Without it, many providers `print` JSON and `exit` (HTTP-style).

### Domain helper pattern (recommended)

```php
function get_provider($field, $default = ''){
  $settings = $this->get_domain_settings(); // panel settings merge
  $panel = trim((string)($settings[$field] ?? ''));
  return $panel !== '' ? $panel : $default;
}

function _call_provider($provider, array $params){
  $provider = trim((string)$provider);
  if ($provider === ''){
    return ['ok' => 0, 'error' => 'No provider'];
  }
  $CI = function_exists('get_instance') ? get_instance() : null;
  if ($CI === null || !method_exists($CI, 'run_action')){
    return ['ok' => 0, 'error' => 'No controller for provider call'];
  }
  $params['return_result'] = 1;
  $params['no_html'] = 1;
  $result = $CI->run_action($provider, $params);
  return is_array($result) ? $result : ['ok' => 0, 'error' => 'Provider failed'];
}

// Usage
$raw = $this->_call_provider(
  $this->get_provider('weather_provider', 'openmeteo/forecast'),
  ['do' => 'forecast', 'latitude' => $lat, 'longitude' => $lon, 'force_refresh' => $force ? 1 : 0]
);
```

Real examples: `energy/energy_model::_call_provider`, `weather/weather_model::_call_forecast_provider`.

### Provider panel: `panel_action`

File: `modules/{provider}/panels/{id}.php`  
Namespace: `{provider}`  
Class: `{id}` extends `\Controller`

Minimal skeleton:

```php
<?php
namespace myvendor;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides service weather_forecast.
 */
class forecast extends \Controller {

  function panel_action($params){

    $post = function($key){
      if (!empty($this->input) && is_object($this->input) && method_exists($this->input, 'post')){
        return $this->input->post($key);
      }
      return null;
    };

    $do = $params['do'] ?? $post('do');
    if ($do !== 'forecast' && $do !== 'weather_forecast'){
      return $params; // not our action
    }

    $this->load->model('myvendor/myvendor_model');

    $return_result = !empty($params['return_result']) || !empty($post('return_result'));
    $lat = $params['latitude'] ?? $post('latitude');
    $lon = $params['longitude'] ?? $post('longitude');
    $force = !empty($params['force_refresh']) || !empty($post('force_refresh'));

    $result = $this->myvendor_model->get_forecast_payload($lat, $lon, $force);

    if ($return_result){
      return is_array($result) ? array_merge($params, $result) : $params;
    }
    print(json_encode($result, JSON_UNESCAPED_UNICODE));
    exit();
  }

  function panel_params($params){
    return $params;
  }
}
```

**Flags:** provider panels that are not placed on public pages should use `"flags": ["hidden"]` so they are settings/action-only.

---

## 5. Response contract

Agree a **stable JSON shape** between domain and all providers for a service. Domain must not parse vendor-specific raw APIs.

### Common envelope (recommended)

```php
[
  'ok'          => 1|0,
  'error'       => '',           // if ok=0
  'source'      => 'myvendor',   // free-text for history/debug
  'fetched_at'  => time(),
  'from_cache'  => 0|1,
  // service-specific:
  'data'        => [ … ],        // or slots / series / etc.
]
```

### Service-specific examples

**`weather_forecast`** — Open-Meteo-shaped `data` (hourly/daily arrays) so domain can share one parser; see metoffice/openmeteo models.

**`energy_price` / `energy_usage`** — lists of half-hour `slots` with `slot_start`, prices or `usage_kw`, sources; domain upserts `energy_history`.

**`shop_checkout` / `subscription_checkout`** — action-oriented: redirect URLs, order ids; not “series”.

Document the contract in the **domain** `docs/agents.md` (and provider docs can say “implements X contract”).

---

## 6. Caching and `force_refresh`

| Layer | Who | Typical behaviour |
|-------|-----|-------------------|
| Provider **file cache** | Provider module under `cache/` | Honour TTL unless `force_refresh` |
| Domain **DB history** | Domain module only | Provider never writes domain tables |
| FE / menu poll | Domain API | **No force** on normal poll; `?force=1` exceptional |

**Rules of thumb**

1. Provider: fresh cache within TTL → return without network; expired → network; stale file only if request fails.  
2. Do **not** treat “non-force” as “never network if any file exists” (that froze energy usage / weather until fixed).  
3. Domain orchestrator: pass `force_refresh` through only when the public API explicitly forces; background menu/page polls leave force off.

---

## 7. Settings split

| Concern | Where |
|---------|--------|
| API keys, account ids, vendor cache minutes | **Provider** settings panel (`admin/panel_settings/{module}__{panel}/`) |
| Which provider, lat/lon, product code, UI labels | **Domain** panel settings |
| Site-wide AI provider | **cms_settings** + `cms/cms_input_provides` service `ai` |

Never put vendor secrets on the domain panel.

---

## 8. Checklist: add a new provider (for service `S`)

### A. Provider module `modules/myvendor/`

1. **`config.json`**
   - `panels`: settings panel + capability panel(s) (`flags: ["hidden"]` if not public UI).
   - `provides`: `{ "service": "S", "panel": "//capability", "label": "…" }`.
   - Optional `cms_menu` under the domain’s admin group (redefine parent id if needed) — **only** if the settings definition has a **non-empty `settings`** array (see agents.md: no empty `panel_settings` menu links).
2. **Settings** — `definitions/myvendor.json` with fields in **`settings`** (not only `item`), `panels/myvendor.php`, model getters for credentials.
3. **Capability panel** — `panels/capability.php` with `panel_action` as above; `do` accepts short name and/or service name.
4. **Model** — HTTP via streams (see agents.md: no curl for new code if project uses streams); file cache; return standardized payload.
5. **Docs** — `docs/agents.md`: provides service, settings, cache paths, call example.
6. **Enable** module in site module list.

### B. Domain (if new service)

1. Choose **service name** `S`.
2. Domain definition field: `cms/cms_input_provides` + `service: "S"`.
3. Domain model: `get_provider()` + `_call_provider()`; merge result into domain DB/UI.
4. Domain docs: contract for `S`, default provider panel name.
5. Optional session-free **domain API** for FE polls (menu `data-refresh_api`); still no force on normal poll.

### C. Do **not**

- Hard-code `if ($vendor === 'metoffice')` in the domain.
- Write domain history from the provider.
- Call vendor APIs from browser with secret keys.
- Copy an entire sibling module and leave dual writes / dual registries.

---

## 9. Worked examples (this repo)

### Weather

| Piece | Location |
|-------|----------|
| Domain | `weather` — UI + `weather_history` |
| Providers | `metoffice`, `openmeteo` — `provides.weather_forecast` → `//forecast` |
| Select | `weather/definitions/weather.json` → `weather_provider` |
| Call | `weather_model::_call_forecast_provider` → `run_action` |

### Energy

| Piece | Location |
|-------|----------|
| Domain | `energy` — graph, `energy_history`, menu refresh |
| Providers | `octopusenergy` (price + usage), `agileforecast` (price forecast) |
| Select | `energy/definitions/price.json` → `price_provider`, `usage_provider`, `price_forecast_provider` |
| Call | `energy_model::_call_provider` / `refresh_providers_into_history` |

### Shop / subscription

| Piece | Location |
|-------|----------|
| Domain | `shop` cart / `subscription` pricing |
| Providers | `shopify` → `shop_checkout`; `stripe` → `subscription_checkout` |
| Select | shop settings fields via `cms/cms_input_provides` |
| Call | cart / checkout `run_action($provider, …)` |

---

## 10. `provides` vs `extends`

| | **provides** | **extends** |
|--|--------------|-------------|
| Purpose | Pluggable **capability** (which backend implements a service) | Pluggable **UI/behaviour** on an existing panel |
| Config | `provides: [{ service, panel, label }]` | `extends: [{ target, source }]` |
| Selection | Admin dropdown stores panel name | Automatic chain when module enabled |
| Runtime | Domain calls chosen panel via `run_action` | CMS merges definitions / templates / controller methods |
| Doc | **This file** | [cms_module_extends.md](cms_module_extends.md) |

Example: energy **extends** `menu/menu` for chips; energy **uses provides** for price/usage vendors.

---

## 11. Debugging

| Symptom | Check |
|---------|--------|
| Dropdown empty | Module enabled? `provides` in `config.json`? Service string exact match? |
| `run_action` no-op | Panel path wrong? `do` not accepted by `panel_action`? |
| Always stale | Provider treating non-force as “never network if file exists”? |
| Domain empty UI | Domain history write/read; provider may be fine but sync window wrong |
| Secrets in logs | Remove logging of headers/bodies with keys |

Inspect registry after boot (debug only):

```php
print_r($GLOBALS['config']['provides']['weather_forecast'] ?? []);
```

---

## 12. Minimal file tree for a provider

```text
modules/myvendor/
  config.json                 # panels + provides + cms_menu
  definitions/
    myvendor.json             # settings fields (api_key, cache_minutes, …)
    forecast.json             # optional empty / hidden panel def
  panels/
    myvendor.php              # settings panel_params if needed
    forecast.php              # panel_action → model
  models/
    myvendor_model.php        # HTTP + cache + get_*_payload()
  docs/
    agents.md                 # service contract, settings, cache paths
  css/ / js/                  # usually none for pure providers
```

Domain keeps public panels, schema, FE, and APIs.

---

## 13. Summary one-liner

**Provider advertises `service` → `panel` in `config.json`; domain stores a chosen panel name via `cms/cms_input_provides`; domain orchestrates with `run_action($panel, [do, …, return_result=>1])`; provider returns a standardized payload; domain alone owns domain data and UI.**
