# Module panel extends (`config.json`)

Created: 2026-07-08

Site/final module extends base panels from other modules. Declare in **`modules/<module>/config.json`** — not in panel definition JSON.

Related: modules may also declare **`provides`** (capability → panel), e.g. Shopify `shop_checkout` → `shopify/checkout`, xAI `ai` → `xai/ai`. Aggregated in [`cms_config.php`](../../../system/core/cms_config.php) as `$GLOBALS['config']['provides']`. Admin select: **`cms/cms_input_provides`** (`service` field). **Full guide:** [**provider_pattern.md**](provider_pattern.md). Also [shop.md cart section](../../shop/docs/shop.md), [xai.md](../../xai/docs/xai.md), [language.md](language.md) (AI translations).

## Panel names and `//` (current module)

Panel ids are normally **`module/panel`** (e.g. `user/login`, `music/units`).

Where a value expects a module prefix, **`//`** means **the module that owns the file or config** — write the path using that module’s own name, not a hard-coded module slug from elsewhere.

| Context | Example in `music` | Resolves to |
|---------|-------------------|-------------|
| `config.json` extends `source` | `"//user_login"` | `music/user_login` |
| Panel definition `"image"` | `"//panel_login.png"` | `music/panel_login.png` |
| Any `"//…"` string in definition JSON | `"//panel_somepanel.png"` | `music/panel_somepanel.png` |

Handlers:

- [`cms_config.php`](../../../system/core/cms_config.php) — `extends[].source` with `//`
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

Boot: [`cms_config_load_full()`](../../../system/core/cms_config.php) aggregates all module `extends` into `$GLOBALS['config']['extends']`. Request lifecycle / Loader: [`system.md`](system.md).

## Extension panel naming

Convention: `<baseModule>_<panel>` in the extending module — e.g. `user_login` extends `user/login`, `shopify_product` extends `shopify/product`.

## Files in extending module

```
modules/music/
  definitions/user_login.json   # extra CMS fields merged into target definition
  css/user_login.scss           # theme (loaded after target SCSS)
  js/user_login.js              # behaviour (concatenated after target JS)
  templates/user_login.tpl.php  # optional full template replace of target (see below)
```

| Asset | Supported | Handler |
|-------|-----------|---------|
| Definition merge | Yes | [`get_cms_panel_config($target)`](../../models/cms_panel_model.php) → [`merge_structures()`](../../models/cms_panel_model.php) for each source (see merge rules below) |
| Settings **values** | Yes | [`get_cms_page_panel_settings()`](../../models/cms_page_panel_model.php) merges saved settings from each extend **source** into the target |
| SCSS | Yes | [`controller::get_panel_filenames()`](../../../system/core/controller.php) |
| JS | Yes | Same — appended after target panel JS; [`pack_js()`](../../../system/helpers/packer_helper.php) concatenates in order |
| Template | Yes | Same — if `modules/<ext>/templates/<source_panel>.tpl.php` exists, it **replaces** the target template entirely (no merge). Last extending module in `modules` order wins. |
| PHP controller | Yes | `modules/<ext>/panels/<source>.php` if present. **`panel_params` / `panel_action`:** target first, then each extend in order (chain). **`panel_heading`:** reverse-walk extenders — only the **last** implementer runs (else target). |

### Definition merge rules (`merge_structures`)

Base = target definition; overlay = each source in module order. Boot indexes: `$GLOBALS['config']['extends_by_target']`, `extend_sources`.

| Key | Rule |
|-----|------|
| `item`, `settings` | Merge fields by `name` (`_merge_field_definition`; nested `fields` same) |
| `list` | `array_merge` — source subkeys win (e.g. `link_target: "0"`) |
| `extra_buttons` | Append |
| `js` | Append unique entries when both arrays |
| `label`, `description`, `image` | Source only if target empty (keep product list titles) |
| `filename`, `module` | Never from source |
| **All other keys** | Source **overwrites** (including unknown future keys: `ensure_data`, `cache`, …) |

**Do not** declare circular extends (A→B and B→A). No runtime cycle guard.

### Discovery (lists / schema)

Use [`list_definition_panel_names()`](../../models/cms_panel_model.php) (skips pure extend **sources** by default). Then `get_cms_panel_config` + `is_real_list_config` for lists. Schema builds table columns from **merged** target config, not raw source JSON.

Semantic reads always go through `get_cms_panel_config` — never open definition JSON for `link_target` / fields / list flags.

### Naming convention

Extension panel id = `{target_module}_{target_panel}` in the extending module, e.g. `shop/product` ← `//shop_product` → `timmy/shop_product` or `shopify/shop_product`.

## Template replace

Unlike SCSS/JS (append / cascade), an extension **template** is a full replace of the target’s `.tpl.php`.

1. Place `templates/<source_panel>.tpl.php` next to the extension panel (e.g. `music/templates/user_login.tpl.php` for `source: "//user_login"`).
2. When the **target** is rendered (`user/login`), that file is used instead of `user/templates/login.tpl.php`.
3. If several modules extend the same target and each provides a template, the **last** module in the site `modules` list (and thus in `$GLOBALS['config']['extends']`) wins.
4. Extension with only definition/CSS/JS and **no** template file → base template unchanged.
5. HTML comments note the source when replaced: `template from config extend "music/user_login"`.

## JavaScript

Extension JS loads **after** base panel JS. When `pack_js` is on, files are concatenated into one cache file — later definitions override earlier ones (redefine `login_init`, etc.).

AJAX `panel_id` stays on **target** (e.g. `user/login`), not the extension panel.

## CMS usage

Place the **target** panel on pages (`user/login`), not a duplicate panel id in the extending module. Music example: login / register / reminder use `user/login`, `user/register`, `user/reminder`; themes in `music/css/user_login.scss`, `user_register.scss`, `user_reminder.scss`.

## Removed (do not reintroduce)

| Mechanism | Notes |
|-----------|-------|
| Definition JSON `"extends"` + `join_js` / `join_css` | Old child-panel model; removed from core |
| Runtime `_extends` params / `cms_wrapper` from definition extends | Removed |
| Bare `definitions/{slug}.json` scans for list slug detection | Use list template slug → panel name + `get_lists()` |

Only **config.json** `extends` (`target` keeps original panel name; modules add fields/theme) is supported.

See also [`cms_panel_js.md`](cms_panel_js.md) for panel JS contracts.