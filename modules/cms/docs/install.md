# Clean install (`_install/install.php`)

Browser wizard for a **new** Narrative CMS site from an empty document root.

## Placement

| Location | Installs into |
|----------|----------------|
| `{docroot}/_install/install.php` (recommended) | **Parent** `{docroot}/` — never inside `_install/` |
| `{docroot}/install.php` | Same directory as the script |

Open e.g. `http://yoursite.localhost/_install/install.php`.

After a successful install, the script is **kept by default**. Step 4 offers a dump-style checkbox **[ ] Delete install script after successful install** (off by default).

## Requirements

- PHP with `mysqli`, writable document root  
- MySQL/MariaDB admin credentials able to `CREATE DATABASE` / `CREATE USER`  
- Network access to the master updater URL (default `https://update.narrativecms.com/cms/updater/`)  
- Master must have a **published Release** of the core package (`cache/master/cms/` on the master host)

Local master: set `$update_url` at the top of `install.php` (e.g. `http://cms.localhost/`).

## Wizard steps

1. **General** — page title, project name, environment (DEV / STG / LIVE)  
2. **Database** — host, new DB name, admin user, new app DB user/password  
3. **CMS admin** — superuser username/password (stored in host `config/{server}.json`, not only MySQL)  
4. **Checks** — writable root, DB access, name/user free, master reachable; optional delete-install checkbox  
5. **Install** — files → database (schema + seed) → config  
6. **Done** — links to homepage and `/admin/`

## What the installer does

### Files

- POST `do=files` / `do=file` to `{master}/cms/updater/` for the **core** package (`module` / `area` empty → release snapshot `cache/master/cms/`)  
- Prefers **batch** `filenames[]` (20 paths per request); falls back to single-file `filename=`  
- Progress: `{install_root}/cache/install.txt` (`n/total` or `done`)  
- Writes under **install root** only (`system/`, `modules/cms/`, `index.php`, …)

### Database structure

After files are on disk:

1. Create database and app user (`CREATE USER` / `GRANT`)  
2. Bootstrap a minimal CMS loader and call **`cms_schema_model::fix_schema('cms')`**  
3. Tables come from [`modules/cms/schema/*.json`](../schema/) (e.g. `cms_page`, `cms_page_panel`, `cms_page_panel_param`, `cms_route`, …) — **not** hardcoded DDL  

No legacy tables (`cms_slug`, `cms_api`, …).

### Seed data (SQL only)

Minimal content so the site boots:

| Data | Notes |
|------|--------|
| Homepage `cms_page` | `position=main`, slug `homepage` |
| Settings panels | `cms/cms_settings`, `cms/cms_cssjs_settings` |
| Params | Includes `language=''`; settings JSON blob |
| Route | `cms_route`: `homepage` → page `1` |

Admin login uses config **`admin_username` / `admin_password`** (config superuser).

### Config

Writes `{install_root}/config/{SERVER_NAME}.json` and `.htaccess`.  
`base_url` is the **site** path (parent of `/_install/` when the script lives there).

## After install

1. Open `/admin/` with the credentials from step 3  
2. Use **CMS → Update** to install extra modules (music, shop, …) when the master publishes them  
3. **CMS → Schema** for other modules after they are on disk  
4. Optionally remove `_install/` manually if you did not check “delete install script”

## Related

- Updater protocol: [`cms_update.md`](cms_update.md)  
- Schema tool: [`cms_schema.md`](cms_schema.md)  
- Host config / no-config redirect into installer: [`system/core/cms_config_basic.php`](../../../system/core/cms_config_basic.php)
