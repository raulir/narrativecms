# Architecture / quality review

Repeatable review after a **bigger slice of work** (about **4–16 hours**) or when an **older area** never had this pass. Not for one-line CSS or a single field rename.

Related: [`agents.md`](agents.md), [`cms_module_extends.md`](cms_module_extends.md), [`provider_pattern.md`](provider_pattern.md), [`cms_panel_js.md`](cms_panel_js.md), [`cms_panel_params.md`](cms_panel_params.md).

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
6. **Output** — a short list of **improvements** (see below). Use keep / unused / wrong layer / contract as working notes, not the delivered result.

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

---

## Vertical (layers in one module)

| Layer | Owns | Does not |
|-------|------|----------|
| `config.json` | Placeable/embed panels, menu, extends, provides | List-only catalogue types |
| `definitions/` | CMS fields, list meta, settings vs item | Runtime queries |
| `models/` | Lists, APIs, cache, compositing, orchestration | Markup, CSS |
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

- **No silent fail** — empty skip is fine; log or operator-visible reason (`_reason`, status line).
- **Visitor copy** from CMS fields, not hard-coded UI sentences in templates/JS.
- **Public FE:** no new flex, no ARIA/`role`, no `cursor:` (site cursors).
- **Frontend panel JS:** `<panel>_init`, `<panel>_resize`, `<panel>_scroll` (stubs OK) — [`cms_panel_js.md`](cms_panel_js.md).
- **Module SCSS** (`css/<module>.scss`) only if several panels share tokens; otherwise variables live on the panel SCSS.
- **HTTP:** streams, not curl; secrets never logged.
- **`htmlspecialchars`** is not the default for CMS field output.

---

## Output shape

The **result** is a list of suggestions to improve the code or system — **only if something should change**.

For **small and medium** review rounds, cap at about **5** items: the most important issues, most severe or highest leverage first. Skip nits, style, and “nice someday” unless they are in that top set. If nothing needs fixing, say so in one line (no empty Keep/Wrong-layer tables).

Larger reviews (whole module, go-live) may go slightly over 5; still rank and stop when extra items would dilute the list.

```markdown
## Improvements
1. …
2. …
```

Write those suggestions into the session plan, up to 5, one numbered block each (what is wrong, why it matters, concrete fix). That plan is what gets commented on. Do not implement the suggestions in the review turn. If nothing should change, the plan says so in one line. Optional one-line “Fine as-is” if a risky area was checked and left.

Then add accepted items to `modules/<owner>/docs/todo.md` (not root `AGENTS.md`) if they are not being implemented in the same turn.

Optional: a short “Architecture” note in that module’s main doc if the map helps the next person.
