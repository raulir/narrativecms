# Data management and backup (`cms/cms_dump`)

Admin page: **`admin/dump/`** (menu id `cms_dump`).

Today’s UI still ships as **Data dumps** — rename planned (below). Panel: [`cms_dump.php`](../panels/cms_dump.php). Related: route rebuild [`cms_rebuild_routes`](../panels/cms_rebuild_routes.php), image purge [`cms_images_unused_purge`](../panels/cms_images_unused_purge.php).

**Ticket:** #26 Database backup inside CMS  

**Legend:** `[ ]` open · `[x]` done  

DB table snapshots must **not** use MySQL `*_bu` tables — zip SQL under `cache/db/` ([`agents.md`](agents.md) § Database backups).

---

## Page rename / identity

- [ ] **Rename admin page to “Data management and backup”** — menu label (`config.json` `cms_dump` name), toolbar title, any docs that still say only “Data dumps”. Keep URL `admin/dump/` unless a separate migration is planned.

---

## UX / design (#26)

- [ ] **Nicer design** — modern CMS admin layout for this page (sections, status, actions; not raw table + bare forms)
- [ ] **More warnings** — clear danger copy before overwrite, restore, truncate, purge, rebuild
- [ ] **Help texts** — short help on each major action (generate, upload, restore, routes, images)

---

## Backups (#26)

- [ ] **Multiple backups** — keep and list several named/timestamped backups (not only overwrite `_dump.zip` / `_dump_2.zip` / `_dump_db.zip`)
- [ ] **Option to restore without downloading** — apply a backup already on the server from the list (no download/re-upload)
- [ ] **Table-level backups to `cache/db/*.zip`** — already used by route rebuild; generalise listing/restore if useful on this page

### Dump generation

- [ ] **Separate resources by month** — resource/image packs split by `YYYY/MM` (easier partial transfer/restore)
- [ ] **Option to import resources without optimised images** — originals only; skip derivative `_name.WIDTH.ext` (rebuild optimised sizes lazily on target)

### Upload / restore safety

- [ ] **Preflight PHP limits** — before upload/restore, check `upload_max_filesize`, `post_max_size`, `memory_limit`; fail early with clear message
- [ ] **Validate upload contents** — after resources upload, verify structure/permissions/paths (no path traversal, expected layout, writable targets)
- [ ] **Resources checksum registry** — store checksums of resource files; show when update/restore is needed (local vs registered / package mismatch)

---

## Sections already / partly on this page

| Section | Status | Notes |
|---------|--------|--------|
| Full / 2‑month / DB-only zip generate + download | exists | Ad-hoc `_dump*.zip` in `cache/` |
| Upload dump zip | exists | Needs limit/content checks (above) |
| **Rebuild public URL routes** | [x] shipped | `cms/cms_rebuild_routes` ajax; backup `cache/db/cms_route_*.zip` then rebuild ([`routing.md`](routing.md)) |
| **Purge unused images** | [x] shipped | Months + category; soft-move to `cache/tmp/img/` ([`cms_image.md`](cms_image.md)) |

---

## Implementation pointers

| Piece | Path |
|-------|------|
| Panel | [`modules/cms/panels/cms_dump.php`](../panels/cms_dump.php) |
| Template | [`modules/cms/templates/cms_dump.tpl.php`](../templates/cms_dump.tpl.php) |
| Styles | [`modules/cms/css/cms_dump.scss`](../css/cms_dump.scss) |
| Menu entry | [`modules/cms/config.json`](../config.json) → `cms_dump` |
| SQL dump helper | [`system/vendor/mysqldump/mysqldump.php`](../../../system/vendor/mysqldump/mysqldump.php) |
