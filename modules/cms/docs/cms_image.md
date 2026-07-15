# CMS image editor

## Overview

The `cms_image` panel is the crop/adjust editor overlay. It opens on top of the image selector grid (`cms_images`).

Full stack (input field → popup → grid → this editor): [`cms_input_image.md`](cms_input_image.md). Architecture rules: [`agents.md`](agents.md).

## Crop editor

Crop is defined as percentage coordinates relative to the **source (parent) image**:

- `x` — percentage of image width from the left edge
- `y` — percentage of image height from the top edge
- P1 = top-left corner (`x1`, `y1`)
- P2 = bottom-right corner (`x2`, `y2`)
- Defaults when opening a **parent** image: `0.0, 0.0` and `100.0, 100.0`
- Values may be outside `0`–`100` (e.g. negative) to add transparent border area on export

### UI interaction

- **Four corner handles** on the crop rectangle (TL, TR, BL, BR)
- **Corner drag** — resizes the crop; no clamping (handles can move outside image bounds)
- **Pan drag** — drag the image and crop box together; display-only view offset (crop `%` unchanged). Image can extend beyond the area edges (clipped)
- **Manual input** — four text fields; coordinates shown with one decimal place (`0.0`, `55.0`, `-8.9`)

Opening a **parent** image always shows default crop values (crop is not stored on parent).

Opening a **child** image shows the parent as preview with the child's saved crop parameters, zoom, and pan.

## Zoom editor

Zoom is an editor-only view aid for precise crop adjustment. Export still uses crop `%` on full source pixels.

### UI

- Label **Zoom** on its own row, below Crop
- Row: numeric input (`0.0` format, one decimal, trailing zero always) and custom slider on the same line
- Slider: logarithmic scale from `0.5` to `16.0`, with equally spaced tick marks and labels at `0.5`, `1.0`, `2.0`, `4.0`, `8.0`, `16.0`
- Lines and borders: `rgba(0,0,0,0.5)`
- Handle: `1rem × 2.5rem` white box with dark border; draggable along the track
- Mouse wheel over the image area zooms in/out (×1.1 / ÷1.1 per notch)

Input, slider handle, and image display stay in sync.

### Sizing

At zoom `1.0`, the image is scaled so its diagonal equals the image area width:

`base_scale = area_width / sqrt(natural_w² + natural_h²)`

Displayed size: `natural_dimension × base_scale × zoom`

A square image in a square area is shown at roughly `area_width / sqrt(2)` per side, leaving margin at the edges.

Default zoom for a **parent** is `1.0` with pan `0, 0`. Allowed range is `0.5`–`16.0`.

### Crop overlay

The crop box scales with the image when zoom changes; crop `%` values are unchanged. Pan moves the image and crop box together without changing crop `%`.

## Brightness and contrast

Range **0.00–1.00**, default **0.50** (= no change). Values stored to two decimal places on child meta and **baked into the exported child file**.

### UI

- **Brightness** and **Contrast** rows below Zoom
- Each: numeric input (`0.00` format) and linear slider with ticks labelled **min** / **normal** / **max** at `0.00`, `0.50`, `1.00`
- Input, slider handle, and preview stay in sync; input can be cleared while typing (commit on blur)

### Filter math (preview = export)

Uses W3C CSS `brightness()` and `contrast()` formulas. UI value `v` maps to amount `N`.

**Brightness:** `v ≤ 0.50` → `N = v / 0.50`; `0.50 < v ≤ 0.70` → linear to `brightness(1.25)` (prev. UI `0.60`); `0.70 < v ≤ 0.90` → linear to `brightness(2.67)` (prev. UI `0.81`); `v > 0.90` → linear to `brightness(20)` at UI `1.00`

**Contrast:** `v ≤ 0.50` → `N = v / 0.50`; `0.50 < v ≤ 0.80` → `N = 1 + (v − 0.50) / 0.30 × 0.6` (subtle, up to `contrast(1.6)`); `v > 0.80` → `N = 1.6 + (v − 0.80) / 0.20 × 1.4` (UI `1.00` → `contrast(3)`)

Preview: CSS `filter` on the image source. Export: same per-channel math in GD after crop (brightness then contrast; PNG alpha preserved).

| UI | Brightness | Contrast |
|----|------------|----------|
| 0.00 | black | flat grey |
| 0.50 | normal | normal |
| 0.70 | `brightness(1.25)` | — |
| 0.80 | — | `contrast(1.6)` |
| 0.90 | `brightness(2.67)` | — |
| 1.00 | toward white | `contrast(3)` |

## Coloured overlay

Default colour **`#000000`**, default opacity **`0.00`** (no overlay until raised). Values stored on child meta and **baked into the exported child file**.

### UI

- **Overlay** section below Contrast
- **Colour** row: embedded `cms_input_colour` panel (hex `#RRGGBB`)
- **Opacity** row: numeric input (`0.00` format) and linear slider with ticks at `0`, `0.2`, `0.4`, `0.6`, `0.8`, `1.0`
- Live preview: `rgba` wash over the crop rectangle (including areas outside the source image within the crop box)
- Preview hidden when opacity ≤ `0.005`

### Export blend

After crop, brightness, and contrast, a full-canvas pass blends:

`out_rgb = src_rgb × (1 − opacity) + overlay_rgb × opacity`

Skipped when opacity ≤ `0.005`. Extended crop areas outside the source image receive the overlay (JPEG white / PNG transparent pixels are blended toward the overlay colour; PNG extended pixels become opaque when opacity > 0).

## Rotation

Range **-180° to 180°**, default **0°**. Integer degrees stored on child meta and **baked into the exported child file**.

### UI

- **Rotation** section below Overlay
- Numeric input (integer degrees) and linear slider with ticks at **-180, -90, 0, 90, 180**
- Square fixed-step toggle after the slider (no label): when on (default), slider and input snap to **45°** steps (`-180, -135, -90, -45, 0, 45, 90, 135, 180`); when off, free integer degrees
- Crop `%` values stay in **unrotated** source space when rotation changes

### Preview

- Crop box and handles stay fixed on screen
- Only the image layer rotates, pivoting around the **crop-area centre** so the crop-centre on the image stays aligned with the crop-box centre
- CSS: `translate(cx,cy) rotate(deg) translate(-cx,-cy)` on `.cms_image_image_source` (brightness/contrast filter unchanged)

### Export

Rotation is applied **first** (before brightness, contrast, overlay). Output canvas size is unchanged (crop pixel dimensions from unrotated `%`).

Each target pixel is filled by **inverse mapping** into the source (bilinear sample) to avoid gaps:

```
rcx = dx + 0.5 − cw/2,  rcy = dy + 0.5 − ch/2
sx = ccx + rcx·cos(θ) − rcy·sin(θ)
sy = ccy + rcx·sin(θ) + rcy·cos(θ)
```

`θ` matches CSS clockwise degrees. When rotation is `0`, the fast `imagecopy` path is used.

Export uses a tiered fast path when rotation ≠ `0`:

1. `imageaffinecopy` (inverse matrix, bilinear) when GD provides it
2. Otherwise pad source so crop-centre is at canvas centre → `imagerotate` → `imagecopy` crop (native GD, much faster than per-pixel PHP)
3. Fallback: per-pixel inverse map with bilinear sampling

### Save / export model

Child files are named `{parent_basename}_v1.{ext}`, `{parent_basename}_v2.{ext}`, etc.

**Saving a parent** (non-full crop) creates a **new** child at the next available `_vN` suffix. Crop is not stored on the parent.

**Saving a child** overwrites that child's file and updates its meta (including crop). Lazy resized derivatives (`_name.WIDTH.ext`) are deleted before re-export.

**Child `meta` stores:**
- `parent_cms_image_id`
- `parent_filename`
- `crop` — the parameters used for this child
- `zoom` — editor view zoom (`0.5`–`16.0`, one decimal place)
- `pan_x`, `pan_y` — editor pan offset in area pixels
- `brightness`, `contrast` — `0.00`–`1.00`, two decimal places; baked into child file on export
- `overlay_colour` — hex colour (e.g. `#000000`); baked into child file on export
- `overlay_opacity` — `0.00`–`1.00`, two decimal places; baked into child file on export
- `rotation` — integer degrees `-180`–`180`; baked into child file on export
- `rotation_fixed` — `'1'` or `'0'`; UI preference for 45° step snapping (default `'1'`); not baked into export

**Parent `meta` stores:**
- `child_ids` — array of `cms_image_id` for each child (`_vN` variant). Appended when a child is created; removed when a child is deleted. Legacy parents without this list are rebuilt lazily from the `_vN` filename pattern when the image picker loads.

**Parent `meta` does not store** crop coordinates, zoom, pan, brightness/contrast, overlay, or rotation.

Saving a child re-exports when crop **or** brightness **or** contrast **or** overlay **or** rotation changes. Unchanged crop and adjust values update meta only (zoom/pan/author etc.).

Child save is handled by `save_cms_image_child()` in the model. A new child from a parent save returns `child_filename` in the ajax response (editor reopens on that file).

## Image picker usage counts

The image selector grid (`cms_images_page`) shows how often each file is referenced in the CMS (panel params + page header images).

| Type | Display | Meaning |
|------|---------|---------|
| Parent with children | `N (M)` | parent used N times; all children combined M times |
| Parent without children | `N` | self-usage only |
| Child | `(N)` | this child used N times |

Examples: `1 (2)`, `0 (0)`, `0 (1)`, `(0)`, `(15)`.

Delete confirmation uses total usage (self + children for parents, self only for children): e.g. `1 (3)` warns **in use at 4 places**.

Export uses GD:
- Crop area can extend beyond the source image
- Areas outside the source are transparent (PNG) or white (JPEG)

After a new child is created from a parent save, the editor reopens on that child. Saving a child closes the editor and returns to the image grid.

## Video sources

When the source file is a video (`type = video`, `.mp4`), the editor uses the same crop, zoom, pan, brightness, contrast, overlay, and rotation controls as for images. All of these are **preview-only** in the editor — CSS `filter`, `transform`, and overlay on the video element inside `.cms_image_image_source`.

### Preview

- Source markup comes from `_ib()` on the parent video (poster + `data-cms_video*` attributes).
- `cms_image_video_init()` replaces the poster with a muted looping `<video>` (native mp4 fallback).
- Layout dimensions come from ffprobe when available, else cover.jpg aspect ratio, else 16:9 placeholder; refined from `loadedmetadata` in JS.
- Save overlay (white cover + label) shows on child save or non-full crop, same as images; label is **Saving ...** instead of **Exporting ...**.

### Child save (meta-only)

Video children are **not** re-encoded. Saving creates or updates a `cms_image` row named `{parent}_vN.mp4` with the same child `meta` shape as images (crop, zoom, pan, brightness, contrast, overlay, rotation, etc.) but **no physical child mp4 file**.

On save, a matching cover frame is exported to `{child}.data/cover.jpg` when ffmpeg is available (frame extract from the parent mp4, then the same GD crop/rotation/brightness/contrast/overlay pass as image children). Without ffmpeg, cover export is skipped and the child save still succeeds; poster falls back to the parent cover. Re-save skips re-export when crop and adjust are unchanged and the cover already exists.

`original_width` / `original_height` on child meta are taken from the parent video dimensions.

### Frontend view (child transforms)

Playback, background fit, poster, and warden behaviour: [`cms_video.md`](cms_video.md) (frontend serving). Editor-only **zoom / pan** are not applied on the frontend.

## Files

| Role | Path |
|------|------|
| Input field + selector | [`cms_input_image.md`](cms_input_image.md) |
| Controller | `modules/cms/panels/cms_image.php` |
| Template | `modules/cms/templates/cms_image.tpl.php` |
| Model | `modules/cms/models/cms_image_model.php` |
| Save ops | `modules/cms/panels/cms_images_operations.php` |
| JS | `modules/cms/js/cms_image.js`, `modules/cms/js/cms_images.js`, `modules/cms/js/cms_media_view.js`, `modules/cms/js/cms_video.js` |
| SCSS | `modules/cms/css/cms_image.scss`, `modules/cms/css/cms_video_view.scss` |
| `_ib` | `system/helpers/image_helper.php` |

## TODO (future image transforms)