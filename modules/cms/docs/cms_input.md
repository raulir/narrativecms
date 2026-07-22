# CMS panel inputs

Panel definition fields live in `modules/<module>/definitions/<panel>.json` under `item` (per-instance panel params) or `settings` (global panel settings). The admin UI renders them via [`print_fields()`](../helpers/cms_fields_helper.php), which routes each field to a `cms/cms_input_*` panel (or a custom module field panel such as `music/exercise_params`).

**Admin vs frontend:** preparing values for these **field** panels may use that field’s own `panel_params`. Do **not** run the **page panel’s** frontend `panel_params` when building the admin form — see [`cms_panel_params.md`](cms_panel_params.md).

Client behaviour follows [`cms_panel_js.md`](cms_panel_js.md): each input panel has `cms_input_<type>_init($root)` and a `cms_input_<type>_ok` guard class on its root element. Repeater fields preload the panel JS and call the matching init hook when a row is added.

`*` = required where noted.

## Universal properties

| Property | Description |
|----------|-------------|
| `type` * | Input type — see list below. Alias: `color` → `colour`. |
| `name` * | Template variable name. Lowercase alphanumerics and underscores only; first character must be a letter. Good: `heading`, `image`, `testing_text`. Bad: `testing text`, `Text`, `123`. |
| `label` * | Text shown next to the input in the CMS. Do not repeat the word “label” in the label text. |
| `help` | Help text in the CMS. `[` `]` bold, `{` `}` italic, `\|` line break. |
| `mandatory` | `"1"` — field must be non-empty before the panel can be shown. |
| `readonly` | `"1"` — display-only (supported on `text`, `textarea`, `select`, `repeater`, `image`). On `textarea` with `html`, TinyMCE is not loaded; content is shown in a plain readonly textarea. |
| `translate` | `"1"` — value is stored per language when multilingual CMS is enabled. |
| `groups` | String or array — only inside a `repeater`; show this field when the active repeater tab matches (used with a sibling `groups` input). |

## Default values

| Pattern | Use |
|---------|-----|
| plain string | Static default |
| `today` | Today’s date (`date` / `datetime`) |
| `:date:Y-m-d` | PHP `date()` format at render time |
| `:date:Y-m-d:86400` | `date(format, time() + offset_seconds)` |
| `:rnd:12` | Random lowercase alphanumeric string, length 12 |
| `:meta:page:seo_title` | Pull default from another field on the same panel (text / textarea) |

---

## Input types

### text

| Property | Description |
|----------|-------------|
| `default` | Default value |
| `search` | Search weight `0`–`3` |
| `max_chars` | Maximum character length |

### textarea

| Property | Description |
|----------|-------------|
| `default` | Default value |
| `lines` * | Height in CMS line-heights |
| `width` | `"wide"` — double-column width |
| `md` | `"1"` — plain textarea with **Preview** / **Edit** toggle; markdown rendered server-side via `cms/cms_input_textarea`. No TinyMCE. Works with `translate`. |
| `md_filter` | `"module/panel/function"` — optional pre-markdown filter; receives live `text` and caller `cms_page_panel_id`, returns filtered `text` and optionally `images` for `![alt](id)` resolution. Example: `"music/material/md_filter_preview_text"`. |
| `html` | Enables WYSIWYG editor. String of feature letters: `H` header, `L` list, `A` link, `B` bold, `I` italic, `U` underline, `C` colour, `Q` quote, `P` line breaks as `<p>` (default is `<br>`), `M` media (image upload). Ignored when `md` is set. |
| `html_class` | CSS class on TinyMCE body |
| `html_css` | Stylesheet URL for TinyMCE content |
| `styles` | With `M` — image style formats, e.g. `[{"name":"Left","style":{"float":"left"}}]` |
| `search` | Search weight `0`–`3` |
| `max_chars` | Maximum character length |

### date

| Property | Description |
|----------|-------------|
| `default` | Default value; `today` = today |

Returns string `YYYY-MM-DD`.

### datetime

| Property | Description |
|----------|-------------|
| `default` | Default value; `today` = today |
| `format` | `"timestamp"` — store Unix timestamp (seconds) instead of `YYYY-MM-DD HH:MM` |

### image

| Property | Description |
|----------|-------------|
| `category` | Image library category — common: `content`, `icon`, `diagram`, `logo` |
| `meta` | `"image"` — also used as page SEO / social image |
| `size` | `"small"` — compact input without static preview |
| `default` | Upload path (e.g. `2020/02/test.png`) or module default (`mymodule/test.png` → `modules/mymodule/img/test.png`) |

Detail: [`cms_input_image.md`](cms_input_image.md).

### select

| Property | Description |
|----------|-------------|
| `values` * | Object of `value` → `label` |
| `default` | Selected value (must exist in `values`) |
| `add_empty` | `"1"` — prepend `-- not specified --` option (value `''`) |
| `mandatory` | `"1"` — requires a selection; implies `add_empty`. Empty values `''` and `0` fail validation |

### multi

| Property | Description |
|----------|-------------|
| `values` * | Object of `value` → `label` |

Checkbox list; stores selected keys as an array.

### link

| Property | Description |
|----------|-------------|
| `targets` | Comma-separated target kinds. Default: `none,manual,page,lists`. `none` — empty allowed; `manual` — typed URL; `page` — CMS pages; `lists` — list items with `link_target`; `[list_name]` — e.g. `article` for one list type |
| `format` | `"short"` — single-line compact layout |

### file

| Property | Description |
|----------|-------------|
| `accept` | Allowed extensions, e.g. `.mp4` |

### cms_page_panels / panels

| Property | Description |
|----------|-------------|
| `panels` * | Comma-separated panel ids allowed (must exist in module `config.json`) |
| `size` | Visible panel rows before scroll; default `4` |

Nested page panels editor.

### panel / cms_panel

| Property | Description |
|----------|-------------|
| `flag` | Limit choices to panels with this flag in `config.json` (e.g. `cron`) |
| `add_empty` | `"1"` — prepend empty option |

Select panel type from installed modules.

### list

| Property | Description |
|----------|-------------|
| `add_empty` | `"1"` — prepend `-- not specified --` option (value `''`) |
| `mandatory` | `"1"` — requires a selection; implies `add_empty`. Empty values `''` and `0` fail validation |
| `link_target` | `"1"` — only lists with definition `list.link_target` set (linkable / public slug lists). `"0"` or omit — all lists |

Select one **list panel type** (definitions with a `"list"` block), e.g. `shop/product`. Labels are formatted `Shop/Product` (each path segment capitalised). Stored value is the raw id `module/panel`.

### fk

| Property | Description |
|----------|-------------|
| `list` | List id (e.g. `mymodule/mylist`) |
| `add_empty` | `"1"` — prepend `-- not specified --` option (value `''`) |
| `mandatory` | `"1"` — requires a selection; implies `add_empty`. Empty values `''` and `0` fail validation |

Deprecated alternative (block target):

| Property | Description |
|----------|-------------|
| `target` * | `"block"` |
| `name` * | `"[target_panel]_id"` |
| `label_field` | Field used as option label; default `heading` |

### repeater

| Property | Description |
|----------|-------------|
| `fields` * | Array of input definitions |
| `height` | Minimum block height in rems |

Only one nesting level is supported in `print_fields()`.

### repeater_select

| Property | Description |
|----------|-------------|
| `target` * | Repeater field name to read options from |
| `field` * | Repeater sub-field to use as label (`text` or `textarea`) |
| `add_empty` | `"1"` — prepend empty option |
| `labels` | Optional label map |

### subtitle

Section heading in the CMS form. No `name` required.

### list_link

Shows a list item URL on the site. No `name` required.

### groups

Repeater-only tab control. Same shape as `select` (`values`, etc.). Other fields in the same repeater block use `"groups": ["tab_id"]` (or array) to show only when that tab is active.

### xy

| Property | Description |
|----------|-------------|
| `target` * | Image field name on the same panel |
| `default` | `"50,50"` — x,y percentages |

Returns `['x' => …, 'y' => …]` — click position on image, percentages from top-left.

### mask

| Property | Description |
|----------|-------------|
| `target` * | Image field name on the same panel |
| `definition` | Average side count of grid divisions |

Returns `['width' => …, 'height' => …, [0/1 grid values…]]`.

### colour

| Property | Description |
|----------|-------------|
| `default` | Default colour value |

### layout

Page layout selector (from module `config.json` `layouts`).

### modules

Module load order selector (folders under `modules/`).

### page

CMS page selector (all pages).

### multifk

| Property | Description |
|----------|-------------|
| `targets` * | Array of list ids — merged into one select |

### grid

Table grid tied to panel model `ds_*` data-source methods. Can be embedded in a template (`form/form_grid`) or used in panel `settings` via `type: grid` (see [`note_system.json`](../../music/definitions/note_system.json)).

| Property | Description |
|----------|-------------|
| `ds` * | Data source name — panel implements `ds_{name}()` |
| `fields` | Column definitions (`text`, `id`, `cms/cms_grid_editable`, or `module/panel`) |
| `operations` | `S` schema, `L` list, `C` create row, `D` delete row |
| `base_name` | Panel name when `base_id` not available |

**`ds_*` operations:**

| Op | Meaning |
|----|---------|
| `S` | Return column `fields` (may merge dynamic columns — see `form/basic` `ds_subscribers`) |
| `L` | Return row arrays; each row needs `id` |
| `C` | Create row; `id` = parent `base_id` |
| `D` | Delete row; with `base_id`, `id` = parent and `row_id` = line to remove |
| `U` | Update cell (ajax via `cms/cms_grid_editable` when `ds` + `base_id` set) |

**Editable cells:** `cms/cms_grid_editable` — blur-save. With `ds` + `base_id`, calls `U` on the data source; otherwise updates a `cms_page_panel` list item.

**Reference:** [`form/basic.php`](../../form/panels/basic.php) `ds_subscribers` (read-only grid, `form_data` table). [`music/note_system.php`](../../music/panels/note_system.php) `ds_rows` (editable grid, `headers` + `rows` in panel settings JSON).

---

## See also

- [`cms_input_image.md`](cms_input_image.md) — image/video input and selector stack
- [`agents.md`](agents.md) — panel and definition conventions