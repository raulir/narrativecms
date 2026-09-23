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
{export_name}.zip          # more than one main panel: name ends _xN (N = selected count)
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
  "_main": [338],
  "_export_options": {
    "include_database": true,
    "include_fk": true,
    "include_files": true,
    "optimised_images": false,
    "image_cutoff_px": 1200,
    "optimised_videos": false,
    "video_quality": "hd",
    "include_panel_files": false
  },
  "_panels": {
    "338": { "cms_page_panel_id": 338, "panel_name": "music/footer", "_translations": {}, "show": 0 }
  },
  "_settings": { "music/footer": 12 },
  "_files": {
    "2022/11/logo.png": {
      "resource_type": "image",
      "cms_image_id": 15,
      "filename": "2022/11/logo.png",
      "export_filename": "a1b2c3d4_logo.png",
      "hash": "sha1 of the bytes in the zip",
      "name": "logo",
      "title": "",
      "description": "",
      "category": "",
      "type": "",
      "meta": {},
      "optimised": 0
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
| `_main` | Array of root panel ids. A panel export sends one. The list gear **Export** sends the ticked rows on the loaded page (`export_ids`). One file. Heading stays **Export page panel** for one id, and is **Export page panels (N)** when there are more. |
| `_export_options` | Options used for this export (for import replay). `include_fk` defaults on. |
| `_panels` | One object per panel id: roots, nested `cms_page_panels` children (all the way down), direct fk targets of those panels when `include_fk` is on, and each panel type’s settings row. Same id once. Params are `language=''` (so `_translations` is in the JSON). |
| `_settings` | `panel_name` → settings panel id (`cms_page_id` 0, `parent_id` 0, `sort` 0) inside `_panels`. |
| `_files` | Images, videos, and uploads from every panel in `_panels`, including settings (settings field list, not item). `hash` is the sha1 of the zip bytes, not written back to the live row. `meta` is `cms_image.meta` (crop and adjust). |
| `_panel_files` | Optional manifest of copied panel source paths |

Fk targets are one step only: their own fk fields are not followed. Children are part of the main panel, so their fk fields are followed the same way. If a panel is reached first only as an fk target, and later as a root or a child (two list rows pointing at each other), its fk fields are added then. A file is listed once; import uses the filenames on the panels it chooses.

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
2. Build `old_id → new_id` for every `_panels` id (`_main` is an array). Remap `cms_page_panels` and `fk` values. Find settings via `_settings[panel_name]`. Do not apply the source `cms_page_id` on another site.
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