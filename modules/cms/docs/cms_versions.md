# Page panel versions

Created: 2026-11-18 (narrativecms issue #43)

Before a save changes a page panel, what the panel held is kept as a **version**: an export zip in the same format as [Export](cms_export.md). The page panel gears menu → **Versions** lists them, compares one with the live panel, and restores one.

## Settings (CMS settings)

| Field | Default | |
|-------|---------|--|
| `page_panel_versioning` (**Page panel versions**) | On | Off: saves keep no version, and the gears menu has no Versions. Versions already kept stay on disk. |
| `page_panel_versions` (**Page panel versions kept**) | 5 | Versions kept per page panel. 0 keeps one (the last). Over 25 is taken as 25. A lower number prunes the oldest versions at that panel's next kept version. |

## When a version is kept

- **Save** of a stored block (`cms_page_panel` → `cms_page_panel_save`): before `save_cms_page_panel_admin`, `stage()` collects what the block holds now and copies its CMS file uploads, because a save deletes an upload it removes. After the save, `keep_staged()` writes the version only when the save changed the content (hash before ≠ hash after), and it is not the same as the newest version. A refused or failed save drops the staged copy. An error in versions is logged and never stops the save. A new block has nothing earlier, so there is no version.
- **Restore** keeps a version of the current content first (`snapshot()`), unless that equals the newest version.

The content hash covers `_panels` of the version, with key order ignored (a save can store the `_translations` languages in another order). `update_time`, `update_cms_user_id`, `_title` and `_cache*` are left out, so a save that changes nothing keeps no version.

## Where

`tmp/cms_page_panel_versions/<panel module>/<cms_page_panel_id>/`:

- `{YmdHis}_{hash8}.zip`, one per version: `data.json` (the export format), plus the copied files at the zip root.
- `versions.json`: the index, newest first: `file`, `time`, `cms_user_id`, `user`, `hash`, zip `size`, `files` count. A row whose zip is gone is dropped on read.

Staging during a save: `tmp/cms_page_panel_versions/_staging/<random>/`, removed at the end of that save.

## What a version holds

Export options: database on, files on, **fk targets off** (other panels, which a restore does not overwrite), **settings row off**, no optimisation, no panel source files. Children (`panels` / `cms_page_panels` fields) are included all the way down.

Every image, video and CMS file of the panel and its children is copied, except a file over **2 MB**. That one is in `_files` with `copied: 0`, `size`, an empty `hash` and no `export_filename`, and has no bytes in the zip. No warning is shown. Copied files have `copied: 1`, `hash` and `export_filename` (`hash8_name.ext`), as in Export.

## Popup

- **Versions** (`cms/cms_page_panel_versions`, opened from `cms/cms_page_panel_button_versions`): date and time, user, zip size, with **[Compare] [Restore]**. **Cancel** closes it.
- **Compare** opens a second popup (`cms_popup_version_compare`) on top, with only **Close**:
  - **Fields:** one row per text value of the definition's fields that differs, in the columns **This version | Current**, each cut to its first 100 characters. Repeater rows are labelled `Label 2 › Sub label`, and translations `Label (es)`. The default language's branch mirrors the fields, so it is left out. Children are grouped under their admin title, with a note when a child is only on one side.
  - **Resources only in this version (last row):** files of the version that the live panel does not have, or has with another size.
- **Restore**:
  1. A version of the current content is kept first.
  2. `cms_page_panel_import_model::apply_zip(version zip, [panel_name], id, 'overwrite', [], false)` writes the version over the panel. These are the Overwrite rules in [cms_export.md](cms_export.md):
     - the live id, sort, page and show stay;
     - a child is overwritten where the live parent points at it, and created again when it was deleted since;
     - a file missing on disk is written back from the zip (a file kept as metadata only cannot be).
  3. Settings are not merged.
  4. Children the panel has now and the version does not are deleted, as the editor deletes a removed child.
  5. The editor reloads with the restored values.
- **A settings row** (`cms_page_id`, `parent_id` and `sort` all 0) is versioned too. Compare and restore use its definition's `settings` fields.

## Code

| | |
|--|--|
| [`cms_page_panel_version_model.php`](../models/cms_page_panel_version_model.php) | `enabled`, `max_versions`, `stage` / `keep_staged` / `drop_staged`, `snapshot`, `list_versions`, `compare`, `restore` |
| [`cms_page_panel_export_model.php`](../models/cms_page_panel_export_model.php) | the export walk (`collect` without copying, `preview`, `export`), shared with the Export popup |
| [`cms_page_panel_import_model.php`](../models/cms_page_panel_import_model.php) | `apply_zip()`: one main of a zip on disk (the Import popup's `apply()` checks its session token, then calls it) |
| [`cms_page_panel.php`](../panels/cms_page_panel.php) | the save hook (`_version_stage`) |
| [`cms_page_panel_versions.php`](../panels/cms_page_panel_versions.php), [`cms_page_panel_button_versions.js`](../js/cms_page_panel_button_versions.js) | popup actions `cms_page_panel_versions_list` / `_compare` / `_restore` (`version_panel_id`, `version_file`) |

Checks (local): `ai/tmp/cms_versions_check.js` (editor saves, popups, restore, limits, off), `ai/tmp/cms_versions_max.php` (0 / 25 limits), `ai/tmp/cms_import_new_check.js` (Import As new after the `apply_zip` split).

Not checked on a real site: children (no definition on the dev site has a `cms_page_panels` field), and a video over 2 MB.
