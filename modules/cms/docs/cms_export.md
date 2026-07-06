# CMS page panel export / import

## Export (phase 1 — implemented)

Per-panel export from the CMS page panel editor toolbar (hidden dropdown → **Export**).

### UI flow

1. **Export settings** — checkboxes, size estimates, optimisation controls
2. **Exporting...** — white overlay during zip build
3. **Results** — timing/size stats + download link (`/admin/export/{filename}/`)

Settings changes (toggles, image cutoff px, video hd/ld) refresh size estimates via `cms_page_panel_export_preview` AJAX.

### Zip file layout

```
{export_name}.zip
├── data.json
├── {hash8}_{name}.jpg          # media at zip root (flat)
├── {hash8}_{name}.mp4
└── _panel_files/               # only when panel source export enabled
    └── {module}/{panel}/
        ├── panels/{panel}.php
        ├── templates/{panel}.tpl.php
        ├── definitions/{panel}.json
        └── js/{panel}.js
```

### data.json schema

```json
{
  "_main": 338,
  "_export_options": {
    "include_database": true,
    "include_files": true,
    "optimised_images": true,
    "image_cutoff_px": 1200,
    "optimised_videos": true,
    "video_quality": "hd",
    "include_panel_files": false
  },
  "_panels": {
    "338": { "cms_page_panel_id": 338, "panel_name": "music/footer", "_translations": { ... }, ... }
  },
  "_files": {
    "2022/11/logo.png": {
      "hash": "...",
      "export_filename": "a1b2c3d4_logo.png",
      "name": "logo",
      "resource_type": "image",
      "optimised": true,
      "category": ""
    }
  },
  "_panel_files": {
    "music/footer": [
      "modules/music/panels/footer.php",
      "modules/music/templates/footer.tpl.php"
    ]
  }
}
```

| Key | Description |
|-----|-------------|
| `_main` | Root `cms_page_panel_id` exported |
| `_export_options` | Options used for this export (for import replay) |
| `_panels` | Panel rows + cached params (`language=''`, includes `_translations`) |
| `_files` | **All binary resources** — images, videos, `cms_file` uploads |
| `_panel_files` | Optional manifest of copied panel source paths |

**Legacy note:** Older exports may contain `_images` instead of unified `_files`. Import should read both.

### Panel normalisation on export

- `show` → `0` (import as hidden draft)
- `cms_page_id` `999999` → `0` (list placeholder)

### Video optimisation

When **optimised videos** is on, export uses a fallback from `{filename}.data/` if smaller than the original upload:

| `video_quality` | Fallback tried (first existing, smaller than original) |
|-----------------|--------------------------------------------------------|
| `hd` | `fallback_hd.mp4`, then `fallback.mp4` |
| `ld` | `fallback.mp4` only |

### Image optimisation

When **optimised images** is on and `original_width` or `original_height` exceeds **cutoff px** (default 1200), export includes a GD-resized copy (max dimension = cutoff, aspect preserved).

---

## Import (not implemented — reference for future work)

### Existing stub code

| File | Status |
|------|--------|
| [`cms_page_panel_import.php`](../panels/cms_page_panel_import.php) | `panel_action` returns zero stats; no file handling |
| [`cms_page_panel_import.tpl.php`](../templates/cms_page_panel_import.tpl.php) | Upload UI shell |
| [`cms_list.js`](../js/cms_list.js) lines 131–190 | Import flow **commented out** |
| [`cms_list.tpl.php`](../templates/cms_list.tpl.php) | Import button only when `environment == 'NOP'` |

### Intended import flow (from commented JS)

1. Open list import popup
2. User selects `.zip`
3. `POST ajax_api/get_panel` — `panel_id=cms/cms_page_panel_import`, `do=cms_page_panel_import`, multipart file
4. Show stats: time, panels, images, files, new_images_*
5. Backend should: unzip → read `data.json` → remap panel IDs → dedup media by hash → `create_cms_page_panel()` / `update_cms_page_panel()`

### Suggested import implementation todos

1. Parse `data.json`; honour `_export_options`
2. Build `old_id → new_id` map for `_panels` and child `cms_page_panels` references
3. Import `_files`: match by hash in `cms_image` / `cms_file`; copy into `img/` if missing
4. Restore `_translations` via raw `cms_page_panel_param` rows or cache rebuild (`_update_cached_params`)
5. Remap `link.cms_page_id` fields (optional manual step or slug lookup table in zip)
6. Panel source files — informational only unless doing code deploy (do not overwrite live code silently)
7. Re-enable UI: per-panel import button and/or list import in `cms_list.js`

### Related (not panel import)

- [`cms_dump.php`](../panels/cms_dump.php) — full DB SQL + files zip for environment clone
- [`cache/import_spanish_translations.php`](../../../cache/import_spanish_translations.php) — CSV translation upserts (ad-hoc)

### Known export bugs fixed in phase 1

- `add_file()` metadata was written to `_images` — all resources now use `_files`
- `rrmdir()` recursive call uses `$this->rrmdir()`
- Zip preserves `_panel_files/` subdirectory paths