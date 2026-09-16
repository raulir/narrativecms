# Imagemaker module

Optional GD pipeline for **layered images**: warp an overlay onto a background via a control-point grid, then tint with colour masks.

Not Timmy-specific. Site modules use it only when installed (soft dependency).

## Install

1. Module folder: `modules/imagemaker/`
2. Enable **imagemaker** in CMS site modules settings (`$GLOBALS['config']['modules']`)
3. Admin: **Content → Imagemaker → Styles** (`admin/cms_list/imagemaker__style/`)

## Inter-module API

**Prefer `provides.image_compose`.** Domain modules must not `load->model('imagemaker/imagemaker_model')` for product (or other) composites. Shop is the first consumer (`shop/shop_image_model`).

```php
$CI = get_instance();
$result = $CI->run_action($compose_panel, [
	'do' => 'compose',
	'overlay' => $ontop_key,
	'base' => $base_key,
	'transform' => $transform_json,
	'blending' => 'on',
	'return_result' => 1,
	'no_html' => 1,
]);
// $result['image'], $result['mask'], $result['_reason']
```

`$compose_panel` is the stored provides panel (e.g. `imagemaker/compose`). Shop setting **Image compose** on `shop/shop`; if empty and only one provider is registered, shop uses that panel.

Engine (inside the provider only): `add_image` / `add_colour` on `imagemaker_model`.

| Method | Role |
|--------|------|
| `is_available()` | Module in `config['modules']` |
| `add_image($ontop, $base, $transform)` | Warp layer; returns `['image','mask']` (+ `error` on failure) |
| `add_colour($colour, $image, $mask='')` | Colour layer; returns relative PNG key |
| `decode_transform` / `expand_transform_grid` | Transform helpers |
| `blend_colour` / `dist_to_line` / `point_inside` / `mask_allows_paint` | Pixel / geometry steps |
| `load_gd` / `resolve_absolute` / `ensure_cache_dir` | I/O |

**Image args:** CMS relative keys (`2025/04/x.png`), keys under `imagemaker/…`, or absolute paths.  
**Returns:** relative under `upload_path` (cached as `imagemaker/a_*`, `m_*`, `c_*`).

Shop/Timmy/Shopify must not load this model; they go through `provides.image_compose`.

## Style list (`imagemaker/style`)

| Field | Meaning |
|-------|---------|
| `heading` | Label |
| `print_background` | Base layer |
| `transform` | Edge control grid — input `imagemaker/cms_input_transform` (see below) |
| `blending` | RGB only: `on` (default) = lightness-aware blend; `off` = overwrite with artwork RGB. **Alpha always multiplies transparencies** (\(T_\text{out}=T_\text{base}\times T_\text{overlay}\); GD 0=opaque, 127=clear). Base PNG alpha is kept on save. Fully transparent base pixels are not painted (their RGB is ignored — often leftover black). |
| `colour_mask_1` / `colour_mask_2` | Black = no paint for tint steps |

### Transform field UI

Definition:

```json
{
  "type": "imagemaker/cms_input_transform",
  "name": "transform",
  "label": "Transform",
  "target": "print_background",
  "points": "5"
}
```

| Property | Description |
|----------|-------------|
| `target` | Image field on the same panel (background for the overlay) |
| `points` | Points per edge including corners (default `5`) |

- Field value is JSON in a **hidden textarea** (same idea as `mask`)
- **Edit** opens `imagemaker/transform_picker` popup:
  - **Left:** nearly square image stage (image + polyline + handles)
  - **Right:** tools (Select / Cancel, zoom slider, reset view, reset points, JSON textarea + Apply)
  - **Zoom** (0.5–8, wheel or slider) grows the image stage; point **positions** track the image, handle **drawing** stays fixed rem size (not CSS-scaled)
  - **Pan** by dragging empty image area (not handles)
  - Handles stay draggable; positions always `%` of the image
- JSON textarea shows the generated value (pretty-printed). Edit it and **Apply** to update handles. **Select** still saves the current handles (Apply first if you edited JSON).
- Corners shared; tooltips show label + `x%, y%`
- Thumbnail preview draws the current polygon on the target image

### Transform JSON

Prefer **percent** of base image size (same idea as cms_image crop `%`):

```json
{
  "width": 4,
  "height": 4,
  "maxx": 100,
  "maxy": 100,
  "units": "percent",
  "data": [
    [[26.0, 14.6], [38.0, 14.6], [50.1, 14.6], [62.1, 14.6], [74.1, 14.6]],
    [[26.0, 31.8], [74.1, 31.8]],
    [[26.0, 48.9], [74.1, 48.9]],
    [[26.0, 66.0], [74.1, 66.0]],
    [[26.0, 83.1], [38.0, 83.1], [50.1, 83.1], [62.1, 83.1], [74.1, 83.1]]
  ]
}
```

- `width` / `height` — number of **cells** (5 points per edge → cells = 4)
- `units`: `"percent"` — each point is `[x%, y%]` of the **base** image; converted to pixels at warp time
- Legacy absolute pixels: omit `units` (or use large `maxx`/`maxy` matching the base pixel size)
- Sparse mid-rows with only two endpoints (left/right) are interpolated by `expand_transform_grid`
- Detect helper for a frame PNG/JPG: `php cache/_detect_frame.php` (writes `cache/frame_transform.json`)
- Local warp test (same algorithm as `add_image`): `php grok/imagemaker/run_wobble_frame.php` — see `grok/imagemaker/README.md`

## Shop style FK (extends)

When this module is enabled, it extends:

| Target | Source | Field |
|--------|--------|--------|
| `shop/product` | `imagemaker/shop_product` | `imagemaker_style_id` |
| `shop/subcategory` | `imagemaker/shop_subcategory` | `imagemaker_style_id` |
| `shop/category` | `imagemaker/shop_category` | `imagemaker_style_id` |

Each group starts with subtitle **Imagemaker**. FK list: `imagemaker/style`, optional (`add_empty`).

### Cascade

```
style_id = product.imagemaker_style_id
        ?: subcategory.imagemaker_style_id
        ?: category.imagemaker_style_id
```

Used as the **main** (non-variant) product image and for grid thumbs.

### Product dimension rules

Admin: **Tools → Imagemaker → Product dimension rules** (`imagemaker/dimension_rules` settings).

Repeater: `product_dimension` (e.g. `frame`) + `dimension_value` (e.g. `black`) + `imagemaker_style_id`. Match is case-insensitive against CMS dim slug + value id/label. Field names stay as stored.

When a product has that option value:

- Compose `original_artwork` onto **that style** (own photo + transform).
- Cache `imagemaker/product_{id}_{styleId}_{hash8}.png`.
- Put the file in the gallery with `ids` = those variant ids (all sizes for that frame).
- Strip those ids from Shopify gallery images (drop the row if no ids remain).
- Variant ids that no rule claims (e.g. Oak, Unframed) are put on the **cascade** composite so the picker does not fall through to White/Black.
- Shopify / CMS gallery rows that lose all variant ids stay in the gallery as **non-variant** images (always visible under the active generated frame).

No matching rule: cascade style is still the main image (as before).

### Productthumb + PDP gallery (shop consumes compose)

Shop **`shop_image_model`** owns cascade, dimension rules, 15s generate budget, gallery insert, and thumb HTML bust. Thumbs use the cascade style only.

Call path: productthumb / mega-menu preview / PDP after Shopify catalogue merge → `shop_image_model` → `run_action(image_compose)`. Overlay is product **`original_artwork`**.

Colour masks / `add_colour` on thumbs are **not** wired yet (see todo).

### Product page gallery (`shop/product` chain)

```
shop/product → shopify/shop_product (catalogue + shop_image_model compose) → imagemaker (style FK extend only) → timmy
```

| Layer | Role |
|-------|------|
| **shopify** | TTL recheck / refresh; merge catalogue; then `shop_image_model->apply_to_product_params` |
| **imagemaker** | Style FK fields on product/category/subcategory; `provides.image_compose` |
| **timmy** | Presentation only — **does not** overwrite catalogue / images |

**Module order:** `shopify` before `imagemaker` before site presentation (`timmy`).

## Migration from Timmy prototype

Old list panel name was `timmy/imagemaker`. After enabling this module:

```sql
UPDATE cms_page_panel
SET panel_name = 'imagemaker/style'
WHERE panel_name = 'timmy/imagemaker';
```

FK integers on products stay valid if product fields are reintroduced later.

## Cache

Directory: `{upload_path}/imagemaker/`. Safe to purge; files regenerate on next call.
