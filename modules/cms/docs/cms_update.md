# CMS module updater

Admin: **CMS → Update** (`cms/cms_update`).

**Clean site install** (empty docroot, browser wizard): [`install.md`](install.md) — `_install/install.php` downloads the core release and applies cms schema + seed.

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
| **Master** (`update.master` / `is_master`) | **[Release]** | Scan live tree → `tmp/master/{id}/` + `version.json` |
| **Client** (`update.allow`) | **[Update]** | Download **released** files from remote master only |

Unreleased live edits are invisible to clients until Release.

### Installed table order (upper list)

1. **Narrative CMS** (core, empty area)  
2. **Masters** — packages in this host’s `update.master` / `is_master`  
3. **Local only** — present on disk, not on remote master  
4. **Installed** — other client packages tracked against remote master  

### Master host table labels

| Column | Unreleased | Released (in sync) | Released + live edits |
|--------|------------|--------------------|------------------------|
| **Local** | Config maj.min (e.g. `0.0`) | Full version + date (`0.0.0 - 16 Jul 2026`) | Same + ` (local changes)` |
| **Master** | `Master` | `Master (0.0.0 - 16 Jul 2026)` — version clients get | Same published version |

Release button presence already means “can publish”; no “(not released)” suffix. Version `0.0.0` is shown as-is (never rewritten to “unknown”).

### Client host table labels (Local column)

The live tree hash is compared with the master’s current release **and** its `history` (from `do=version`):

| Local tree | Local label | Update |
|------------|-------------|--------|
| Hash = current release | Master version + date (`0.0.13 - 5 Nov 2026`) | Hidden |
| Hash = older release in `history` | That release + its date (`0.0.12 - 28 Oct 2026`) | Shown |
| No match, version stamped earlier | Stamped version + date + ` (local changes)` | Shown |
| No match, never stamped | `0.0.0 (local changes)` | Shown |

On a hash match the version, hash and release time are written to `tmp/version.json` / `tmp/version_{module}.json` (only when they differ; `update_time` is kept), so later rebuilds keep the real version. A later local edit then shows e.g. `0.9.11 - 28 Oct 2026 (local changes)`. Never guess a version from a near-match: a tree that differs from every release by one file stays unmatched.

### Release history (master)

Release writes `history` into the new `tmp/master/{id}/version.json`: the previous release plus its carried-forward history, newest first, entries `{version, current_hash, version_time}` only (no file lists), one per hash, the new release’s own hash excluded, capped at 40. `do=version` returns it as `history`; older clients ignore the key.

Packages released before this existed have no history until their next Release, which records only the release on disk at that moment. Older releases that clients still match must be seeded by hand on the master, e.g. in `tmp/master/shop/version.json`:

```json
"history": [
	{"version": "0.0.12", "current_hash": "fa0fc9c0d2d378d1b4c3ac7436934436", "version_time": 1793199249}
]
```

The next Release carries seeded entries forward.

## Layout on master

```
tmp/master/cms/           # core: system/ + modules/cms/ (+ index.php, LICENSE)
  version.json
  system/...
  modules/cms/...
tmp/master/music/
  version.json
  modules/music/...
```

- Release id: area `''` → folder `cms`; else module name  
- Master API reads **only** the release snapshot (not live tree)

Local working hashes remain under `tmp/version.json` / `tmp/version_{module}.json`. On first use, `cache/master/` and `cache/update/` are moved to `tmp/` if the new location is empty.

## Client update popup + schema

After file transfer (Update or Install):

1. Confirm area (refresh updater table row)
2. Check **schema for that package only** (`area` empty → `cms`)
3. Append result in the **same scroll stream** as file ticks (scroll down after transfer)

No extra “Schema” heading in the popup — the fragment is the schema content only.

- No DB drift: **“No schema updates available for this module”**
- Drift: same fix/sync controls as the full schema page for that one module

Fragment request uses `schema_module` + `fragment=1` (not `module` — panel loader overwrites `module` with the panel package name `cms`).

Install / Update: enable (if install) → confirm row HTML → schema in popup → **Close without page reload** (table rows update only).

### Remove (client installed modules)

**Remove** appears before Update on non-core rows. Not shown for:

- Core (Narrative CMS / empty area)
- Packages this host **masters** (`update.master` / `is_master`) — take the package out of master config first

**Does:** unregister from `cms/cms_settings` modules, delete `modules/{name}/`, delete local `cache/version_{name}.json`.

**Does not:** drop database tables / panel table data.

If master still has a **released** package for that name (and `update.allow` includes it), the row is added under **Available to install** without a full updater rescan.

## Related code

- Model: [`cms_update_model.php`](../models/cms_update_model.php)
- Admin UI: [`cms_update.js`](../js/cms_update.js), [`cms_update_popup.tpl.php`](../templates/cms_update_popup.tpl.php)
- Schema fragment: [`cms_schema/fragment.tpl.php`](../templates/cms_schema/fragment.tpl.php)
