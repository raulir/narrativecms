# CMS module updater

Admin: **CMS → Update** (`cms/cms_update`).

## Master endpoint (module API)

Public, session-free API (early include in `system/cms.php`):

| | |
|--|--|
| URL | `{base}cms/updater/` (default `cms_update_url`) |
| Script | [`modules/cms/api/updater.php`](../api/updater.php) |
| Body | POST form fields |

| `do` | Purpose |
|------|---------|
| `version` | Released version/hash for `module` (empty = core) |
| `modules` | Packages this host publishes (`update.master`) |
| `files` | Manifest file list for area (from release only) |
| `file` | File body/bodies from release snapshot |

### `do=file`

**Legacy (one file)** — old clients:

- POST `filename` = relative path  
- Response: `{ "file": "<base64>" }` or `{ "file": "", "error": "…" }`

**Batch** — new clients (master supports first):

- POST `filenames[]` = array of paths (or JSON array string)  
- Max **40** paths per request  
- Response:

```json
{
  "files": { "path/a.php": "<base64>", "path/b.js": "<base64>" },
  "errors": { "missing.php": "No such file" }
}
```

Paths must appear in the release manifest; `..` rejected. Partial success allowed.

**Client** (admin Update): stages files in batches of 20 (up to 2 batches in flight) via `cms_update_file` + `filenames[]` → master batch `do=file`. If the master only understands single-file responses, the client model falls back to one request per file inside `get_master_files_content`.

Logic: [`cms_update_model::get_file`](../models/cms_update_model.php) / `get_files_content` / `update_files`.

## Config major.minor

Each module may declare package major.minor in `modules/{name}/config.json`:

```json
{
	"name": "Music",
	"version": "0.2"
}
```

- Core package (system + `modules/cms`) reads `modules/cms/config.json` → e.g. `"6.0"`
- Missing / invalid → `0.0`
- Full published version is `major.minor.patch` (e.g. `6.0.0`, `6.0.1`)

When config maj.min **changes**, the next Release starts patch at **0** again. Same maj.min → patch +1.

## Release vs Update

| Role | Control | Effect |
|------|---------|--------|
| **Master** (`update.master` / `is_master`) | **[Release]** | Scan live tree → `cache/master/{id}/` + `version.json` |
| **Client** (`update.allow`) | **[Update]** | Download **released** files from remote master only |

Unreleased live edits are invisible to clients until Release.

### Master host table labels

| Column | Unreleased | Released (in sync) | Released + live edits |
|--------|------------|--------------------|------------------------|
| **Local** | Config maj.min (e.g. `0.0`) | Full version + date (`0.0.0 - 16 Jul 2026`) | Same + ` (local changes)` |
| **Master** | `Master` | `Master (0.0.0 - 16 Jul 2026)` — version clients get | Same published version |

Release button presence already means “can publish”; no “(not released)” suffix. Version `0.0.0` is shown as-is (never rewritten to “unknown”).

## Layout on master

```
cache/master/cms/           # core: system/ + modules/cms/ (+ index.php, LICENSE)
  version.json
  system/...
  modules/cms/...
cache/master/music/
  version.json
  modules/music/...
```

- Release id: area `''` → folder `cms`; else module name  
- Master API reads **only** the release snapshot (not live tree)

Local working hashes remain under `cache/version.json` / `cache/version_{module}.json`.

## Client update popup + schema

After file transfer (Update or Install), the progress popup:

1. Confirms the area (refreshes the updater table row)
2. Checks **schema for that module only** (`area` empty → module `cms`)
3. Shows a **Schema** section: OK message, or the same fix/sync UI as the full schema page for that one module

Height matches a **4-row** image selector (`4 × 15.8rem` + chrome). File ticks and the **Schema** block share **one scrollable body** — after transfer, the view can scroll down to schema.

When the module DB matches definitions: **“No schema updates available for this module”**. Otherwise the usual per-module fix/sync UI.

Schema fragment: [`cms_schema`](cms_schema.md) with `module` + `fragment=1` → [`templates/cms_schema/fragment.tpl.php`](../templates/cms_schema/fragment.tpl.php).

Install enables the module then shows schema; **page reload waits until Close** so the user can fix tables first.

**Release** on master does not run schema checks (no DB apply).

## Related code

- Model: [`cms_update_model.php`](../models/cms_update_model.php)
- Admin UI: [`cms_update.js`](../js/cms_update.js), [`cms_update_row.tpl.php`](../templates/cms_update_row.tpl.php), [`cms_update_popup.tpl.php`](../templates/cms_update_popup.tpl.php)
- Schema: [`cms_schema_model`](../models/cms_schema_model.php), [`cms_schema.js`](../js/cms_schema.js)
