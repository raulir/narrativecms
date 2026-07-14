# System runtime (`system/`)

Bootstrap, request controllers, Loader, panel pipeline, config, and related core behaviour. **Not** admin field types, page editing, or site-module product rules — those stay in other `modules/cms/docs/*.md` files and site module docs.

Related CMS docs:

| Topic | Doc |
|-------|-----|
| Slugs, list URLs, route cache rebuild | [`routing.md`](routing.md) |
| `panel_params` frontend vs admin | [`cms_panel_params.md`](cms_panel_params.md) |
| Module `config.json` extends | [`cms_module_extends.md`](cms_module_extends.md) |
| Languages / targets boot | [`language.md`](language.md), [`targets.md`](targets.md) |

---

## Layers

| Layer | Path | Role |
|-------|------|------|
| **System** | `system/` | Bootstrap, routing, main controllers, Loader, panel render pipeline, core helpers |
| **CMS module** | `modules/cms/` | Admin UI, definitions, page/panel storage, schema tool, images, access |
| **Site modules** | `modules/music/`, `user/`, … | Visitor panels, business logic |

Site modules use system/CMS services; they must not assume admin request shape on the public site. System must not hard-code music-specific product rules.

---

## Bootstrap

Entry: project `index.php` → [`system/core/CodeIgniter.php`](../../../system/core/CodeIgniter.php) (after config).

Rough order:

1. [`config.php`](../../../system/core/config.php) — host config, modules list, `$GLOBALS['config']`
2. Common classes, error handler, URI, **Router**
3. Output, Input
4. Load base [`Controller`](../../../system/core/controller.php) (`get_instance()`)
5. Route class file: `system/core/controller_{class}.php` (e.g. `Index`, `Ajax_api`) or `modules/{module}/controllers/{class}.php`
6. Run requested method (usually page render or ajax)

**API shortcuts** (no full page render): [`system/cms.php`](../../../system/cms.php) may dispatch `modules/<module>/api/<id>.php` when the URI matches a module API id (see module `config.json` `"api"`). Lightweight paths (e.g. analytics beacon) intentionally avoid full CI boot.

**Config access:** `$GLOBALS['config']` — e.g. `base_path`, `base_url`, `upload_path` (`img/`), `cache/` under base path. Host files: `config/<host>.json`.

---

## Main controllers

| Class | File | Typical use |
|-------|------|-------------|
| `Index` | `system/core/controller_index.php` | Full page HTML, positions |
| `Ajax_api` | `system/core/controller_ajax_api.php` | `get_ajax` / panel ajax |
| `Files` | `system/core/controller_files.php` | File serving paths |
| Module admin | e.g. `modules/cms/controllers/admin.php` | CMS admin UI |

Prefer **`extends Controller`**. Legacy name **`CI_Controller`** is a `class_alias` only — do not introduce new `CI_*` type names. Same idea for models: **`extends Model`**, not `CI_Model`.

---

## Controller: main vs panel libraries

Base class: [`system/core/controller.php`](../../../system/core/controller.php) — class name **`Controller`**.

### Main request controller

The **first** `Controller` constructed in the request:

- Owns `Controller::$instance` → `get_instance()`
- Owns `Loader::$parent`
- Runs `Loader::initialize()` **once** (must not be wiped by later panel constructs)
- Loads panel/image helpers

### Panel controllers

Panel PHP files live at `modules/<module>/panels/<name>.php`. They are loaded with **`$this->load->library(...)`** (see `run_panel_method` / `panel()`), not as separate HTTP controllers.

Rules:

- Must **not** steal `get_instance()` or permanently reassign Loader `parent`
- Share main `load` / `input` / `db` (object handles)
- Models resolved on main via Loader; panel `$this->model_name` works through **`Controller::__get`** when the property lives on the main instance
- Prefer `namespace module;` + `class name extends \Controller`

Do **not** assign by reference (`=&`) into properties that go through `__get` — PHP raises *Indirect modification of overloaded property*. Use plain assignment or a declared property (e.g. `$panel_ci`).

---

## Loader and models

File: [`system/core/Loader.php`](../../../system/core/Loader.php)

### Models — one instance per name per request

```php
$this->load->model('cms/cms_access_model');
// → $this->cms_access_model (on main; panels via __get)
```

- Path form: **`module/model_filename`** (required slash)
- Class name matches filename (lowercase): `cms_access_model`
- Instances stored in Loader `_ci_model_instances`; created **once**
- Always attached to current `parent` (and to main if different)
- Never treat as “already loaded” without ensuring the host can access the instance

### Panel libraries (modules)

`load->library($controller_path, ['module' => …, 'name' => …], $object_name)`:

- Resolves `modules/<module>/panels/<name>.php`
- Supports namespaced classes `module\name`
- Tracks includes by **filepath**; re-init attaches under `$object_name` on main without falling through to `system/libraries/`
- Object name convention: `{module}_{name}_panel` (e.g. `music_hheader_panel`)

### Initialize once

`Loader::initialize()` runs only on the main controller. Panel re-construct must not clear model/file registries (that previously caused duplicate models and slow full-page renders).

---

## Panel pipeline

### Order on a full page

1. **Routing** → page id / list target  
2. **Access** (page-level)  
3. **`panel_action`** on panels that have it (all actions before params — site-wide)  
4. **`panel_params`** + **template** per panel (visitor path)  
5. CSS/JS packing, HTML cache write when applicable  

### Methods on panel classes

| Method | When |
|--------|------|
| `panel_action($params)` | State changes, POST/ajax `do=…`; runs **before** any `panel_params` on the page |
| `panel_params($params)` | Build template variables for **frontend** render only |

Return modified `$params` array. Load models with `$this->load->model('module/name')`.

Admin edit of a **page block** must **not** run that page panel’s `panel_params` (side effects / wrong language). Field widgets may run **their own** `panel_params`. Detail: [`cms_panel_params.md`](cms_panel_params.md).

### Helpers

- `_panel('module/panel', $params)` — embed panel HTML  
- `add_css()` / `add_js()` — page-level asset lists (prefer these over ad-hoc includes)  
- `get_ajax('module/panel', data)` (JS) — hits ajax controller + `panel_action` / render as configured  

### Filename layout (panel)

| Piece | Path |
|-------|------|
| Controller | `modules/<m>/panels/<name>.php` |
| Template | `modules/<m>/templates/<name>.tpl.php` |
| Definition | `modules/<m>/definitions/<name>.json` |
| JS / SCSS | `modules/<m>/js|css/<name>.*` |

---

## Routing (system view)

1. **Router** loads generated `cache/routes.php` (visible slugs → `index/index/...` targets).  
2. Numeric or `module/panel=id` targets select page or list-item rendering.  
3. Slug **table** and admin UX: [`routing.md`](routing.md) (storage, slugify, edit slug UI).

Public API modules: URI `module/api_id` may short-circuit in `cms.php` before full front controller (module `config.json` `"api"`).

---

## Config and modules

- Host config merged in [`config.php`](../../../system/core/config.php)
- Enabled modules drive which definitions, schema, menus, and extends load
- **`config.json` `"extends"`** (target/source, `//panel` convention): merged at boot into `$GLOBALS['config']['extends']` — definition fields, SCSS, JS (not PHP/template yet). Detail: [`cms_module_extends.md`](cms_module_extends.md)

---

## Cache (system-level philosophy)

- Do not add **serve-time** workarounds for outdated on-disk cache formats  
- When format changes: purge/rebuild on save; on hit, serve as-is  
- Full-page cache HIT may skip CI boot and thus skip `panel_action` (known limitation — see project `todo.md`)

---

## Models (system conventions)

| Rule | Detail |
|------|--------|
| Class / file | Fully lowercase, match filename: `cms_schema_model` |
| Extends | `Model` (not `CI_Model`) |
| Constructor | Omit unless needed (no obligatory `parent::__construct()`) |
| DB | `$this->db` available; no `$db->error()` on this older library |
| Visibility | No `public` keyword by convention; private/protected rare |
| Helpers | Prefer `_underscore` private methods on models |

JSON: use `cms_json_decode($data, $filename = '')` for files/data (better errors than bare `json_decode`).

---

## Performance note

Full page loads construct many panel libraries. Keeping a **single** Loader init and **shared** models removes a large amount of repeated bootstrap work; multi-panel pages can feel substantially faster server-side after these rules.

---

## Follow-ups (not all done)

- Repo-wide replace of remaining `extends CI_Controller` with `Controller`  
- Remove legacy definition `"extends"` handling from `system/` when projects migrated  
- Optional rename of Loader internal `_ci_*` property names  
