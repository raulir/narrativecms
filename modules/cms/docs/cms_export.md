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

- `show` is kept. Import creates a new row hidden, and overwrite keeps the show value already on the site.
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

## Import

List gear **Import** opens the popup [`cms_page_panel_import`](../panels/cms_page_panel_import.php). The panel accepts the upload, stores the session token, and renders [`cms_page_panel_import.tpl.php`](../templates/cms_page_panel_import.tpl.php). The walk lives in [`cms_page_panel_import_model.php`](../models/cms_page_panel_import_model.php).

The zip is saved under `tmp/` as `import_{token}.zip`. The session stores that path and the list’s panel types. Only `data.json` is read. Mains whose `panel_name` is not this list type are ignored. If none remain, the popup says no panels of that type were found.

Each remaining main is one block: zip id and title, and a CMS id and title only when that id already exists here with the same `panel_name`. Missing fk targets are ticks, off by default. **As new** is always offered. **Overwrite** is offered only when the CMS line is shown.

**As new** creates a hidden row (`show` 0), `cms_page_id` 0, sort at the end of that panel type, and new ids. Children are imported and remapped. **Overwrite** writes fields but keeps the live id, sort, page, show, create time, and create user. The update time and update user become the importing user at that moment. A child is overwritten only when the live parent already points at that id. Otherwise a new child is created and the field is pointed at it.

Ticked fk targets are created before the main tree, each as a new panel. A live row that only shares that id is not overwritten. The same parent pass used for children then sets `parent_id` on the target’s children. Fk fields on the main and on its children use the new ids. Apply accepts a ticked id only when it was on that main’s missing-target list. If that ticked panel’s definition file is not in this CMS, the whole main import is refused and nothing is written. An empty stored type list is refused the same way as a main that is not of this list.

A failed apply returns `ok: 0` and `message`. Panels created during that attempt are deleted. A row that was only overwritten is left as it was. The popup shows the message under the buttons and clears the busy state. The same line is shown when the request itself fails. Choosing a file uses that line when the request fails. An upload that is too large says so, instead of asking for a file again.

The zip is removed when the import popup closes, and when every block in the popup has been imported.

Settings only gain values, new fields, and translations that are not already stored. Existing settings values are not overwritten. `_panel_files` and definitions are not written.

Images and files already on the panel are not deleted. On overwrite, a field that already has the same value is left alone. A different value reuses an image or file with the same filename, or an image with the same hash. If that record’s file is missing from disk, the zip bytes are written onto that filename and no second record is created. Otherwise a new record is created and the bytes are written there.

### Related (not panel import)

- [`cms_dump.php`](../panels/cms_dump.php) — full DB SQL + files zip for environment clone
- [`cache/import_spanish_translations.php`](../../../cache/import_spanish_translations.php) — CSV translation upserts (ad-hoc)

### Known export bugs fixed in phase 1

- `add_file()` metadata was written to `_images` — all resources now use `_files`
- `rrmdir()` recursive call uses `$this->rrmdir()`
- Zip preserves `_panel_files/` subdirectory paths