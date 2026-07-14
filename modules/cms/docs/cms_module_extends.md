# Module panel extends (`config.json`)

Created: 2026-07-08

Site/final module extends base panels from other modules. Declare in **`modules/<module>/config.json`** — not in panel definition JSON.

## Panel names and `//` (current module)

Panel ids are normally **`module/panel`** (e.g. `user/login`, `music/units`).

Where a value expects a module prefix, **`//`** means **the module that owns the file or config** — write the path using that module’s own name, not a hard-coded module slug from elsewhere.

| Context | Example in `music` | Resolves to |
|---------|-------------------|-------------|
| `config.json` extends `source` | `"//user_login"` | `music/user_login` |
| Panel definition `"image"` | `"//panel_login.png"` | `music/panel_login.png` |
| Any `"//…"` string in definition JSON | `"//panel_somepanel.png"` | `music/panel_somepanel.png` |

Handlers:

- [`config.php`](../../../system/core/config.php) — `extends[].source` with `//`
- [`cms_panel_model::get_cms_panel_config()`](../../models/cms_panel_model.php) — `"//` at the start of JSON string values → `"<module>/`

Use `module/…` when referring to another module. Use `//…` only for assets or panel ids inside the **current** module’s files.

## Declaration

```json
"extends": [
  { "target": "user/login", "source": "//user_login" },
  { "target": "user/register", "source": "//user_register" }
]
```

| Field | Meaning |
|-------|---------|
| `target` | Base panel rendered on the page (`module/panel`) |
| `source` | Extension panel in the declaring module — use `//<panel>` (see above): `//user_login` → `music/user_login` |

Boot: [`system/core/config.php`](../../../system/core/config.php) aggregates all module `extends` into `$GLOBALS['config']['extends']`.

## Extension panel naming

Convention: `<baseModule>_<panel>` in the extending module — e.g. `user_login` extends `user/login`, `shopify_product` extends `shopify/product`.

## Files in extending module

```
modules/music/
  definitions/user_login.json   # extra CMS fields merged into target definition
  css/user_login.scss           # theme (loaded after target SCSS)
  js/user_login.js              # behaviour (concatenated after target JS)
```

| Asset | Supported | Handler |
|-------|-----------|---------|
| Definition `item` / `settings` | Yes | [`cms_panel_model::merge_structures()`](../../models/cms_panel_model.php) when loading `target` |
| SCSS | Yes | [`controller::get_panel_filenames()`](../../../system/core/controller.php) |
| JS | Yes | Same — appended after target panel JS; [`pack_js()`](../../../system/helpers/packer_helper.php) concatenates in order |
| PHP controller | **Todo** | Not wired |
| Template | **Todo** | Not wired |

## JavaScript

Extension JS loads **after** base panel JS. When `pack_js` is on, files are concatenated into one cache file — later definitions override earlier ones (redefine `login_init`, etc.).

AJAX `panel_id` stays on **target** (e.g. `user/login`), not the extension panel.

## CMS usage

Place the **target** panel on pages (`user/login`), not a duplicate panel id in the extending module. Music example: login / register / reminder use `user/login`, `user/register`, `user/reminder`; themes in `music/css/user_login.scss`, `user_register.scss`, `user_reminder.scss`.

## Deprecated — do not use

| Mechanism | Notes |
|-----------|-------|
| Definition `"extends"` + `join_js` / `join_css` | Legacy child panel (`music/login` extending `user/login` via JSON). Removed from this project. |
| DB per-block `_extends.*` params | Legacy; delete if found |
| Definition `"extends"` code in core | Still present temporarily — todo: remove from `system/` when all projects migrated |

See also [`cms_panel_js.md`](cms_panel_js.md) for panel JS contracts.