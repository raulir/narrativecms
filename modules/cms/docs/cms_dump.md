# Data and backup (`cms/cms_dump`)

Admin page: **`admin/dump/`** (menu id `cms_dump`).

Panel: [`cms_dump.php`](../panels/cms_dump.php). Related: route rebuild [`cms_rebuild_routes`](../panels/cms_rebuild_routes.php), image purge [`cms_images_unused_purge`](../panels/cms_images_unused_purge.php).

**Ticket:** #26 Database backup inside CMS  

**Legend:** `[ ]` open · `[x]` done  

DB table snapshots must **not** use MySQL `*_bu` tables — zip SQL under `cache/db/` ([`agents.md`](agents.md) § Database backups).

Full environment backups live under **`cache/backup/`** as:

```
dump_<project>_<YYYY>_<MM>_<DD>.zip
dump_<project>_<YYYY>_<MM>_<DD>_N.zip
```

plus sidecar `.json` and embedded **`dump.json`** inside the zip. Never overwrite or auto-delete on generate/upload. **`<project>`** = sanitized DB name (e.g. `music`).

Legacy files `dump_YYYY_MM_DD*.zip` still list.

---

## Page rename / identity

- [x] **Rename admin page to “Data and backup”** — menu label (`config.json` `cms_dump` name), toolbar title. URL stays `admin/dump/`.

---

## Page layout

Three sections (schema-manager style):

1. **Generate backup** — collapsible Options; always-visible Generate
2. **Backups and restore** — collapsible list (**Backups**); header Upload (file + button)
3. **Other functions** — rebuild routes, unused image purge

---

## Generate backup — shipped

- [x] Section header: **Generate** + **Options** (toggle body)
- [x] Defaults: all schema-owned tables + last two resource months
- [x] Options: two columns — tables (by module) and resources (Y and Y−1 by month, newest first; older years as year-only; year master checkbox)
- [x] Pseudo-checkboxes (`[v]` / `[ ]`) same pattern as panel export settings
- [x] Output: `cache/backup/dump_<project>_YYYY_MM_DD[_N].zip`
- [x] Sidecar JSON + **`dump.json` inside zip** (created, filesize, project, tables, resources, resize_*)
- [x] Optional **resize images** (editable max side, default 1400 px): writes `_{name}.{px}.{ext}` next to original, packs under original name in zip; reuses existing size file on later dumps
- [x] Never delete/overwrite existing backups on generate

### Tables encoding (JSON)

- All tables of a module selected → `"module"`
- Partial → `"module/table"` entries

### Resources encoding (JSON)

- Year-only selection → `"YYYY"`
- Month selection → `"YYYY/MM"`
- **Current calendar year always stored as year/month** (never a bare year), even if all 12 months are selected

### Schema coverage

Dump inventory uses `cms_schema_model::get_schema_tables_by_module()` (JSON schemas + definition panel tables). Core table `cms_page` has an explicit schema file.

---

## Backups and restore — shipped (phase 2)

- [x] Section title **Backups and restore**
- [x] Collapsible body via **Backups** (header rightmost)
- [x] Header **Upload** — stores zip into `cache/backup/` with new allocated name (does **not** apply)
- [x] Inventory from sidecar, else `dump.json` inside zip
- [x] Per row: **Restore** / **Download** / **Delete** (confirm on Restore + Delete)
- [x] Restore: extract resources + import `db.sql` with `DROP TABLE IF EXISTS` (no `*_bu`)

### Open / later

- [ ] **Restore confirm: select which tables to overwrite** — dialog (or step) listing tables from dump meta / `db.sql`; only drop+import checked tables; resources still full extract unless further scoped
- [ ] **More warnings / help texts** on generate, upload, restore, routes, images
- [ ] **Preflight PHP limits** before upload/restore
- [ ] **Validate upload contents** (structure, path traversal)
- [ ] **Resources checksum registry**
- [ ] **Separate resources by month** as separate download packs
- [ ] **Import resources without optimised images** (skip `_name.WIDTH.ext`)

---

## Sections status

| Section | Status | Notes |
|---------|--------|--------|
| Generate backup | [x] | `dump_<project>_date.zip` + sidecar + `dump.json` in zip |
| Backups and restore | [x] | Upload to library; list; restore/download/delete |
| **Rebuild public URL routes** | [x] | Other functions |
| **Purge unused images** | [x] | Other functions |

---

## Implementation pointers

| Piece | Path |
|-------|------|
| Panel | [`modules/cms/panels/cms_dump.php`](../panels/cms_dump.php) |
| Template | [`modules/cms/templates/cms_dump.tpl.php`](../templates/cms_dump.tpl.php) |
| Styles | [`modules/cms/css/cms_dump.scss`](../css/cms_dump.scss) |
| JS | [`modules/cms/js/cms_dump.js`](../js/cms_dump.js) |
| Menu entry | [`modules/cms/config.json`](../config.json) → `cms_dump` |
| Backup dir | `cache/backup/` |
| SQL dump helper | [`system/vendor/mysqldump/mysqldump.php`](../../../system/vendor/mysqldump/mysqldump.php) |
| Tables inventory | `cms_schema_model::get_schema_tables_by_module()` |
