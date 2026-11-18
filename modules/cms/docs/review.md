# Architecture / quality review

Repeatable review after a **bigger slice of work** (about **4–16 hours**) or when an **older area** never had this pass. Not for one-line CSS or a single field rename.

Related: [`agents.md`](agents.md), [`admin_pages.md`](admin_pages.md) (special admin pages), [`cms_module_extends.md`](cms_module_extends.md), [`provider_pattern.md`](provider_pattern.md), [`cms_panel_js.md`](cms_panel_js.md), [`cms_panel_params.md`](cms_panel_params.md), [`cms_input.md`](cms_input.md).

**Review does not mean implement.** Write findings and todos on the **owning** module. Implement only if asked, except tiny contract fixes (e.g. list-only types out of `config.json` `panels`).

---

## When to run

| Run | Skip |
|-----|------|
| End of a feature that touched several layers or modules | Typo, one SCSS value, one CMS label |
| Before calling a 4–16h piece “done” | Work still mid-flight with no stable shape |
| Revisiting old code that grew in the wrong place | |

Say what was reviewed (module + feature), not “the whole site”.

---

## How

1. **Map the feature** — what the visitor or editor actually does; which panels fire.
2. **Horizontal** — which **module** owns data, UI, HTTP, cache (table).
3. **Vertical** — which **layer** inside that module (table).
4. **Hot path / page load** — checklist below. A change that is fine on one admin action can still be wrong if it runs on every front request.
5. **Contracts** — checklist below.
6. **Dead code** — functions, files, branches, and settings that this slice left with no caller. A public entry point the CMS still calls by name (`<panel>_init`, a `do` value, a model method used by another module) is not dead.
7. **Output** — a short list of **improvements** (see below). Use keep / unused / wrong layer / contract as working notes, not the delivered result.

Do not restyle or “clean up” unrelated code during the review write-up.

---

## Horizontal (module A vs B)

Who owns **data**, who **extends**, who **provides**, who is a **soft** `in_array('module')` vs a hard load.

| Check | Fail if |
|-------|---------|
| Domain vs provider | Shop/weather/CMS UI calls a vendor model or HTTP directly. Use `provides` + `run_action` ([`provider_pattern.md`](provider_pattern.md)). |
| Extends | Extra fields/behaviour on another module’s panel without `config.json` `extends`. |
| Field names | Module B reads a field only defined on module C (e.g. imagemaker requiring a Timmy-only name) with no documented contract. |
| Site slug | Reusable module hard-codes the site package (Timmy) for behaviour, not just an optional extend. |
| `config.json` `panels` | List-only types (`cms_list`, no public/ajax template) listed so they appear in the page picker. Hidden is not a substitute. See [`agents.md`](agents.md) § `panels`. |
| Soft dependency | `load->model('other/…')` with no module-enabled guard when the other package is optional. |
| Dual keys | Serve-time “old or new field/panel name”. One-off migrate scripts are fine. |
| Helper home | A `system/helpers/` file whose functions serve one module (only that module, or its domain such as CMS content or CMS Update). It belongs in `modules/<module>/helpers/<module>_…_helper.php` ([`agents.md`](agents.md) § Where a helper file lives). |

---

## Vertical (layers in one module)

| Layer | Owns | Does not |
|-------|------|----------|
| `config.json` | Placeable/embed panels, menu, extends, provides | List-only catalogue types |
| `definitions/` | CMS fields, list meta, settings vs item | Runtime queries |
| `models/` | Lists, APIs, cache, compositing, orchestration | Markup, CSS; reading `$_POST` / `$_GET` (request values come as arguments from the panel) |
| `panels/` | Thin `panel_params` / `panel_action` / `on_update` | Heavy GD/HTTP (that is the model) |
| `templates/` | Markup; visitor copy from CMS fields | Business rules |
| `css/` | Layout and look; instance colours as CSS variables on the root | JS-driven show/hide that CSS can do |
| `js/` | Behaviour; `*_init` + empty `*_resize` / `*_scroll` on public panels | Layout that belongs in SCSS |
| `docs/` | How it works + `docs/todo.md` | Duplicating CMS agents.md |

Panel `panel_params` is **frontend-only** ([`cms_panel_params.md`](cms_panel_params.md)). Admin forms use definitions + stored params.

---

## Hot path / page load

Review must include **impact on general page load time**, not only correctness of the new feature. Ask: does this run on every front request (`cms.php`, `cms_config_basic`, session boot, every panel render)?

| Check | Fail if |
|-------|---------|
| Boot / `cms.php` / basic config | `mkdir`, `is_dir`/`file_exists`, extra helper/model load, or other I/O that only some requests need |
| Rare callers | Work that belongs on 2–3 explicit paths is put on the public bootstrap “just in case” |

Example: a missing `tmp/sessions` folder looked like a boot bug. The `mkdir`/`exists` check was only needed on **cms user login `do`**, **CMS Update**, and **install**. Putting it in `cms.php` or basic config made every page load pay for a case those three callers already handle.

Prefer: trust install/update to create runtime dirs; do rare work on the rare path.

---

## Contracts (short)

- **Untrusted input** — anything from the request (visitor post, cookie, query, webhook, third-party payload) is validated or cleaned **on the way in**, before it is stored or used: names, labels, images and prices come from CMS data, not from the post; free text is stripped and capped (`cms_utf8_string()`, `strip_tags`). Stored data is then trusted at render (AGENTS.md § CMS field output). Fail if posted values reach the DB, HTML, admin views or emails unchecked. Boot cleaning (`cms_clean_request()`) only removes control characters; it is not validation. Do not store, mail or log the whole `$params` bag: it also holds settings, block fields and internal `_*` flags such as `_access_ok`. Pick the expected keys.
- **Ajax params** — on `ajax_api/get_panel` the order is panel settings (lowest), then posted values that pass the input filter, then the saved block when the request names a visible block of this panel. The same `ajax.input` filter applies to a full-page form POST on render; the block or admin page config sits on top. A posted key overwrites a settings value of the same name, never a stored block value. A panel whose action takes posted values lists them in `ajax.input` and sanitises each one. Fail if a posted value is used unsanitised, or if a settings value that must not be visitor-controlled (price, recipient, flag, key) can be overwritten by a post, either because its name is in `ajax.input` or because the panel has no `ajax.input`. A missing `ajax.input` on its own is deprecated and minor (see below). See [`agents.md`](agents.md) § Panel controllers.
- **Ajax output** — a reply carries only the `cms_*` keys plus the keys in the definition's `ajax.output`. Fail if `ajax.output` lists a secret, a settings value the visitor must not see, or a key that echoes posted values back. Also fail if the JS reads a reply key that is not listed: it is dropped without an error. A panel that `print`s JSON and `exit`s skips the filter; that is minor, so return an array instead. See [`agents.md`](agents.md) § Panel controllers.
- **Actions** — a state change (save, delete, send, charge) runs only on `cms_do`, which never comes from the query string. On render the other query keys do reach `$params`, so fail if a `panel_action` changes state on any other key: a plain link could trigger it.
- **No silent fail** — empty skip is fine; log or operator-visible reason (`_reason`, status line).
- **Minor:** `window.alert` / `confirm` / `prompt` for public notices (use an HTML popup or a hidden element the JS shows, copy from settings), a panel that takes ajax input with no `ajax.input` in its definition (deprecated: every posted key passes), a new full-page form post where `get_ajax` would do, `$_POST` / `$_GET` read in a panel (panels read `$params`, system code reads `$_POST` / `$_GET`), and function comments over 3 lines or more than about 3 comment lines per 10 lines of code (AGENTS.md § Comments). The raw request skips `_*` stripping, the `ajax.input` allowlist and the params precedence. See AGENTS.md § JavaScript and § Panel controllers. List them only when there is room in the top 5, or group several into one item. Do not let them push out a serious finding.
- **Legacy syntax** in the reviewed code: `array()` becomes `[]`, and `<?php print(…) ?>` becomes `<?= ?>`. See [`agents.md`](agents.md) § Legacy PHP syntax. Fix it in the code under review, not across the module.
- **Visitor copy** from CMS fields, not hard-coded UI sentences in templates/JS.
- **Templates** use `div` unless the tag’s own behaviour is needed (`a` for a real URL, `input`, `textarea`, `select`, `form`, `label`, `img`, `video`). No `span` or `button` for layout or click targets. See [`agents.md`](agents.md) § Markup and CSS style.
- **Public FE:** no new flex, no ARIA/`role`, no `cursor:` (site cursors).
- **Frontend panel JS:** `<panel>_init`, `<panel>_resize`, `<panel>_scroll` (stubs OK) — [`cms_panel_js.md`](cms_panel_js.md).
- **Code names** match the owner. `cms_*` / `_cms_*` are CMS and system. `<module>_*` / `_<module>_*` are module-wide. One panel uses `<panel>_*` or, preferred, `<module>_<panel|library>_*`. Entry points stay `<panel>_init` (and resize, scroll, destroy, `*_ok`). Short template helpers such as `_ib()`, `_lh()`, and `_print_r()` keep that form. See [`agents.md`](agents.md) § Code names.
- **Module SCSS** (`css/<module>.scss`) only if several panels share tokens; otherwise variables live on the panel SCSS.
- **Shared classes** stay inside one module: common class first, then the panel class (`music_heading login_heading`). Another module gets that look from the site extend SCSS via a mixin, not by borrowing the site class. See [`agents.md`](agents.md) § Markup and CSS style.
- **HTTP:** streams, not curl; secrets never logged.
- **`htmlspecialchars`** is not the default for CMS field output, or for hardcoded project parameters (a class name or flag passed from project code).
- **`_ib()`** always has the second size argument. A call with only the image path fails review. That number is the pixel cap for the derivative.
- **`rem` sizes** use at most two decimal places (`10.13rem`, not `10.125rem`). A scaled size is rounded to two places. `letter-spacing` may use three (`0.005rem`). See [`agents.md`](agents.md) § Markup and CSS style.
- **Grid data sources** (`ds_*`) — `L` rows carry a stable `id`, not the array index, and `C` returns the new row's `id`. See [`cms_input.md`](cms_input.md) § grid.
- **Dead code** left by the change fails review: an unused function, file, branch, or setting, or a caller that only reaches code which itself has no caller. Say what to remove. Do not remove it in the review turn. A name the CMS or another module still calls is live.

---

## Output shape

The **result** is a list of suggestions to improve the code or system — **only if something should change**.

For **small and medium** review rounds, cap at about **5** items: the most important issues, most severe or highest leverage first. Skip nits, style, and “nice someday” unless they are in that top set. If nothing needs fixing, say so in one line (no empty Keep/Wrong-layer tables).

**User input trust issues are serious.** When the review finds one (request data stored or rendered without being checked, see Contracts § Untrusted input), it must be in the delivered list. It does not have to be item 1, but it may not be cut to keep the list at 5. Several such findings can be one item.

Larger reviews (whole module, go-live) may go slightly over 5; still rank and stop when extra items would dilute the list.

```markdown
## Improvements
1. …
2. …
```

Write those suggestions into the session plan, up to 5, one numbered block each (what is wrong, why it matters, concrete fix). That plan is what gets commented on. Do not implement the suggestions in the review turn. If nothing should change, the plan says so in one line. Optional one-line “Fine as-is” if a risky area was checked and left.

Then add accepted items to `modules/<owner>/docs/todo.md` (not root `AGENTS.md`) if they are not being implemented in the same turn.

The list is the **big plan**; an item the user picks becomes a **small plan** written above it in the same `ai/` document. See [`agents.md`](agents.md) § Plans: big plan and small plan.

Optional: a short “Architecture” note in that module’s main doc if the map helps the next person.
