# Imagemaker module

Optional GD pipeline for **layered images**: warp an overlay onto a background via a control-point grid, then tint with colour masks.

Not Timmy-specific. Site modules use it only when installed (soft dependency).

## Install

1. Module folder: `modules/imagemaker/`
2. Enable **imagemaker** in CMS site modules settings (`$GLOBALS['config']['modules']`)
3. Admin: **Content → Imagemaker → Styles** (`admin/cms_list/imagemaker__style/`)

## Inter-module API

No `extends` / `provides` required to call the engine. Check install, load model, call steps:

```php
if (in_array('imagemaker', $GLOBALS['config']['modules'] ?? [], true)) {
	$this->load->model('imagemaker/imagemaker_model');

	// 1) Warp overlay onto base (optional)
	$pair = $this->imagemaker_model->add_image($ontop_key, $base_key, $transform_json);
	// $pair['image'], $pair['mask'] — relative keys under upload_path

	// 2) Tint (optional mask: black = no paint)
	$out = $this->imagemaker_model->add_colour('#a4ebf4', $pair['image'], $pair['mask']);
}
```

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

Do not hard-require this module from shop/timmy/shopify core paths without an `in_array` guard.

## Style list (`imagemaker/style`)

| Field | Meaning |
|-------|---------|
| `heading` | Label |
| `print_background` | Base layer |
| `transform` | Edge control grid — input `imagemaker/cms_input_transform` (see below) |
| `blending` | RGB only: `on` (default) = lightness-aware blend; `off` = overwrite with artwork RGB. **Alpha always multiplies transparencies** (\(T_\text{out}=T_\text{base}\times T_\text{overlay}\); GD 0=opaque, 127=clear) |
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
  - **Right:** tools (Select / Cancel, zoom slider, reset view, reset points, point readout)
  - **Zoom** (0.5–8, wheel or slider) grows the image stage; point **positions** track the image, handle **drawing** stays fixed rem size (not CSS-scaled)
  - **Pan** by dragging empty image area (not handles)
  - Handles stay draggable; positions always `%` of the image
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

### Productthumb composite

`timmy/productthumb` → `shopify_product_model::get_productthumb_params` (soft-loads imagemaker only if module is in `config['modules']`):

1. Resolve style via cascade  
2. Need non-empty product **`original_artwork`** (Timmy/Shopify-synced flat art)  
3. Warp artwork **onto** style `print_background` with style `transform` (`add_image`)  
4. Cache:  
   - File: `imagemaker/product_{product_id}_{hash8}.png`  
     (`hash8 = substr(md5(basename(original_artwork) . '.' . style_update_time), 0, 8)`)  
   - **`cms_image` row** (category `imagemaker`) so `_ib()` can build sized derivatives / webp  
   - Hit only when **both** file and DB row exist; if only one is present, purge both sides and rebuild  
5. If cache complete → use as `thumbnail_image` / gallery  
6. If missing and **script elapsed** (`$GLOBALS['timer']['start']`, ms) **≥ 15000** → skip generation, keep Shopify/CMS thumb  
7. Productthumb HTML cache uses `productthumb_{id}_{hash8}.html` when a composite is used (style/artwork change busts cache)

API:

| Method | Role |
|--------|------|
| `resolve_style_id($product)` | Cascade FK |
| `resolve_product_composite($product)` | Composite path or `''` (style + artwork + cache/timer) — use from productthumb, mega menu preview, etc. |
| `product_composite_cache_key($product_id, $original, $style_update_time)` | `hash8` + relative path |
| `get_product_composite_image($product_id, $original, $style_id)` | Cache hit / generate / timer skip |
| `script_elapsed_ms()` | Uses existing `$GLOBALS['timer']['start']` |

Colour masks / `add_colour` on thumbs are **not** wired yet (see todo).

### Product page gallery (`shop/product` chain)

On this site the `panel_params` chain is:

```
shop/product → shopify/shop_product → imagemaker/shop_product → timmy/shop_product
```

| Layer | Role |
|-------|------|
| **shopify** | TTL recheck / refresh; merge catalogue fields; attach `options`, `variants`, `shopify_images` |
| **imagemaker** | Composite into `images` (after variant-linked rows; drops primary `image` slide when same as main product photo) |
| **timmy** | Presentation only (dimensions UI, variant_active order, customisation) — **does not** overwrite catalogue / images |

Imagemaker step:

1. Same cascade + `get_product_composite_image` as productthumb  
2. `apply_composite_to_images` — variant `ids` first, then composite (no `ids`), then other  
3. Field names only (`original_artwork`, style FKs) — no Timmy dependency  

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
