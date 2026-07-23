# Module panel extends (`config.json`)

Created: 2026-07-08

Site/final module extends base panels from other modules. Declare in **`modules/<module>/config.json`** — not in panel definition JSON.

Related: modules may also declare **`provides`** (capability → panel), e.g. Shopify `shop_checkout` → `shopify/checkout`. Aggregated in [`config.php`](../../../system/core/config.php) as `$GLOBALS['config']['provides']`. See [shop.md cart section](../../shop/docs/shop.md).

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

Boot: [`system/core/config.php`](../../../system/core/config.php) aggregates all module `extends` into `$GLOBALS['config']['extends']`. Request lifecycle / Loader: [`system.md`](system.md).

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
| Definition `item` / `settings` | Yes | [`cms_panel_model::merge_structures()`](../../models/cms_panel_model.php) when loading `target` |
| Settings **values** | Yes | [`get_cms_page_panel_settings()`](../../models/cms_page_panel_model.php) merges saved settings from each extend **source** into the target |
| SCSS | Yes | [`controller::get_panel_filenames()`](../../../system/core/controller.php) |
| JS | Yes | Same — appended after target panel JS; [`pack_js()`](../../../system/helpers/packer_helper.php) concatenates in order |
| Template | Yes | Same — if `modules/<ext>/templates/<source_panel>.tpl.php` exists, it **replaces** the target template entirely (no merge). Last extending module in `modules` order wins. |
| PHP controller | Yes | `modules/<ext>/panels/<source>.php` if present. **`panel_params` / `panel_action`:** target first, then each extend in order (chain). **`panel_heading`:** reverse-walk extenders — only the **last** implementer runs (else target). |

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

## Deprecated — do not use

| Mechanism | Notes |
|-----------|-------|
| Definition `"extends"` + `join_js` / `join_css` | Legacy child panel (`music/login` extending `user/login` via JSON). Removed from this project. |
| DB per-block `_extends.*` params | Legacy; delete if found |
| Definition `"extends"` code in core | Still present temporarily — todo: remove from `system/` when all projects migrated |

See also [`cms_panel_js.md`](cms_panel_js.md) for panel JS contracts.