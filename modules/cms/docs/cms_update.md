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

### Row markers (#429)

| Marker | When | Shown as |
|--------|------|----------|
| Master | package is in this host’s `update.master` / `is_master` (`row.is_master`) | Bold module name |
| Inactive | module folder on disk but not in the site settings `modules` list (`row.active`) | Dimmed name + *inactive* |
| Not allowed | master has a different release but `update.allow` does not include the package (`row.not_allowed`) | *Not allowed* instead of Update. Remove is hidden too (it also needs `update.allow`) |

The row carries `data-label` (the module name). The Remove / Release confirmations read that rather than the first cell’s text.

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

### Local edits warning

Whenever a version is stamped into `tmp/version_{module}.json` (after an Update, on a hash match with the current or an older release, or on Release on a master host), the per-file hashes of that version are saved as `version_files`. Rescans keep them. Installs stamped before this existed get them on the next page load where they match a release.

`cms_update_list` rescans the package first, so files edited since the page loaded count. It then returns the plan: `files` (each with `local_edit`), `local_edits`, and `local_changes_unknown`.

- **Local edit:** a U or D file whose local hash differs from `version_files`, or that is not in it (a file added locally). The popup marks it *local edit, will be overwritten / deleted*.
- **Unknown:** there is no `version_files`, but the tree differs from the stamped version or was never stamped. The edited files cannot be named.

In both cases the popup asks **Continue update / Cancel** before anything is downloaded. Cancel discards the empty update.

**List protocol.** The current script sends `protocol: 2` and gets the plan object. Without it (an older updater script still open in a browser after a core update), `cms_update_list` returns `get_needed_files()` unchanged: the file array, or the error object, which that script checks first and stops on. A plan object there would read as "no files" and report a false success. An error must never be turned into `[]`, because on Install that path would enable a module that was never downloaded. After a successful **core** update, closing the popup reloads the page so the script matches the new PHP.

**Quiet file operations.** The CMS error handler also prints `@`-suppressed warnings when `errors_visible` is on, which would break the JSON answers. Updater file operations therefore go through `_q()` (a temporary no-op handler) and report failure by return value.

### Master requests

All calls to the remote master go through `_master_post()`, with a timeout of 10 s for version/modules, 30 s for the file list and 60 s for file batches. An unreachable master or a non-JSON answer returns `['error' => …, 'transport_error' => true]`. The updater page makes one `do=modules` request (`get_master_index()`) for all module rows plus one `do=version` for core, instead of one request per package. `do=modules` carries `current_hash` and `history` like `do=version`. When the master cannot be reached, the rows show the reason in the Master column instead of *Local only*.

### Download is all-or-nothing

A package’s files belong together, so an Update or Install is applied only when **every** file arrived:

1. `cms_update_list` compares the master Release list with the local tree, saves it as the update manifest (`tmp/update_{id}.json`, filename → letter `U` / `A` / `D`, plus the release md5 for `U` / `A`) and clears staging left by an earlier run. A master file with no hash aborts the list.
2. `cms_update_file` stages U/A files from master into `tmp/update/`. It accepts only manifest files inside the package. A D needs no download. A missing body is an **error**, never a delete (an empty file is a valid body). A staged file whose md5 is not the release hash is deleted and returned as an error, so the two retries run.
3. The popup retries a failed file **2 times** (1 s, then 2 s apart). If a file still fails, it calls `cms_update_discard`. Staging and manifest are removed, the live tree is not touched, and the popup says *Update download failed … no files were changed*.
4. `cms_update_copy` applies the update to the live tree:
   - every U/A file of the manifest must be staged. If not, the download is incomplete: staging is discarded and nothing changes;
   - **pre-flight:** every target must be writable before anything changes. That means a file to overwrite, the folder of a file to delete (on Windows also the file itself, since a read-only file cannot be unlinked), and for a new file the nearest existing parent folder, with no file sitting where a folder is needed. Otherwise nothing changes and the popup lists each file with its reason;
   - live files the update overwrites or deletes are copied to `tmp/update_backup_{id}/` first. If that fails, nothing changes;
   - on the first failed write or delete it **rolls back** everything attempted: originals are restored from the backup, added files are removed, and only folders this apply created are removed (if empty again). A file still identical to its backup is left alone; an unreadable hash counts as changed. The popup names the file and says *rolled back, no files were changed*;
   - after a pre-flight, backup or rolled-back failure the **staged download is kept**. The popup offers **Retry apply**, which runs the copy again without downloading. The next update list replaces it;
   - if the rollback itself fails, the backup folder is **kept** and named in the error so files can be restored by hand. Further applies of that package are refused until the folder is restored or removed.

   Before anything is written, `cms_update_copy` also checks each staged U/A file against the release md5. A mismatch does not touch the live tree. After a copy that wrote every file, the tree hash is compared with the master release. A mismatch is rolled back and is not reported as success. Staging, manifest and backup are removed only after that check passes. `update_cleanup` (empty folders), version stamp, confirm and schema run only after a successful copy.

All file paths (master list, posted names, staging) must stay inside the package: `modules/{area}/`, or for core `system/`, `modules/cms/`, `index.php`, `LICENSE` (`_update_path_allowed()`).

- No DB drift: **“No schema updates available for this module”**
- Drift: same fix/sync controls as the full schema page for that one module

Fragment request uses `schema_module` + `fragment=1` (not `module` — panel loader overwrites `module` with the panel package name `cms`).

Install / Update: enable (if install) → confirm row HTML → schema in popup. A module install or update closes without a page reload (the table rows update in place). A core update from this script reloads the page when the popup closes, so the next run uses the new updater script.

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
