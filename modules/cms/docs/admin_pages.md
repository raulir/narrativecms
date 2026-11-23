# CMS admin pages: patterns and design

How a special admin page (a screen that is not a CMS list or a block / settings form) should be built and how it should look, so it reads as part of the CMS. Example built to these rules: **Shop → Orders** (`admin/orders/`, list; `admin/orders/{id}/`, detail page; shop module).

Related: [`agents.md`](agents.md) § Admin CMS menu, § Markup and CSS style; [`cms_panel_js.md`](cms_panel_js.md); [`access.md`](access.md).

**Status:** first write-up (2026-11-17). The existing CMS pages were built at different times and do not all follow it yet. Use it for new pages; bring an old page in line when it is reworked.

---

## Where a page lives

| Piece | Where |
|-------|-------|
| URL and controller | A method of `modules/cms/controllers/admin.php`: `admin/orders/` → `admin::orders()`, `admin/orders/152/` → `admin::orders(152)`. No generic "any module panel" route. |
| Page body | One panel of the owning module (`shop/orders_admin`), rendered through `_output($panel, $params)` (CMS header + menu + the panel in `main`). |
| Menu item | Added by the owning module's `config.json` `cms_menu`, never by the CMS. |
| Module missing | The controller method checks the module is enabled and prints a plain message ("Orders: the shop module is not installed") instead of the page. |
| Access | The controller method checks the CMS user (`cms_access_model::cms_user_has_access('<key>')`). The page panels check it **again**, because their ajax actions do not go through the admin controller. |

---

## Page layout

```
cms_toolbar                       title (cms_tool_text) ................ [filter] [filter] [filter] [search input] [Search]
page body                         table / cards
```

- **Toolbar first**, always: the CMS `cms_toolbar` block with a `cms_tool_text` title on the left and the controls on the right in `cms_toolbar_buttons`. Do not build a custom toolbar.
- **No padding on the page container.** The admin layout already frames the main panel, and `cms_toolbar` sets its own width (`calc(100% - 2.0rem)`, centred). Padding on the container doubles the gutter around the toolbar. Give the body (table, cards) the same width as the toolbar so its edges line up under the toolbar rule.
- Right-hand controls use `cms_right` (float right; the **first in the DOM is the rightmost**), so write them in reverse visual order.
- Controls are CMS classes: `cms_tool_button` for buttons, `admin_tool_select` for selects and the search text box. A page's own SCSS only sets what differs (e.g. the search box width).
- The selected button (current filter, open section) has `cms_tool_button_active`.
- Rarely used actions go into the gear menu (`cms_toolbar_buttons_hidden`), as on the block edit page.
- Keyboard shortcut for the main action: `data-cms_ctrl="s"` on Save.
- Body: plain bordered table rows (list) or bordered cards (detail page). Same font and colours as the rest of the CMS; no site fonts, no flex.
- A detail page's toolbar starts with a back arrow (a `cms_tool_button` with `cms/cms_paging_previous.png`) to its list, opened on the record's filter, then the record title as `cms_tool_text`; its actions on the right.

---

## Lists: filters, search, rows

**Filters are toolbar buttons, not links.** A click changes the rows by ajax, with no page load. One button is selected at a time. The filter is named in the URL (`?view=archive`): the page opens on it (a detail page's back arrow links there), and a click updates it with `history.replaceState`, so reload and Back keep the filter. Without it the default filter is selected.

**Search** works like the CMS content search:
- a text box in the toolbar and a **Search** button;
- results start at **3 characters**, shortly after typing stops (about 300 ms), without pressing the button; the button runs the same search;
- while results are shown, the **Search** button is the selected one and **no filter button is selected**;
- clicking a filter button **clears the search box** and shows that filter.

The server applies the same 3-character rule (shorter input is not a search), and searches in SQL (in the model), not by filtering a page of rows in PHP.

**Rows come from a separate helper panel** (`shop/orders_admin_rows`):
- first landing: the page template prints it with `_panel('<module>/<page>_rows', [default filter])`, so the page needs no ajax to show its first rows;
- filter, search and the next page of rows: `get_ajax_panel('<module>/<page>_rows', {view | q, offset})`, and the JS replaces (filter, search) or appends (next page) the returned rows;
- the helper takes only its own keys (`ajax.input`: `cms`, filter, `q`, `offset`) and checks access itself;
- next page: on scroll near the bottom, 50 rows a time; stop when a page returns fewer.

**Rows** look like the CMS list (`admin/cms_list/…`) and reuse its classes (`add_css` `cms_list.scss` and `cms_list_list.scss`):
- row `cms_list_sortable_item cms_list_list_item` inside a `cms_list_list_container` (height, borders, hover);
- a select tick `cms_list_list_tick` (`[ ]` / `[v]`, row class `cms_list_list_selected`) and the select-all tick `cms_list_list_tick cms_list_toolbar_tick` before the toolbar title;
- no drag handle, no `...` menu, no eye unless the page really sorts, copies or hides records;
- after the tick, the cells form one link (`a`, cells as `div`s) to the detail page; nothing else in the row is interactive;
- column headings in a row of their own between the toolbar and the rows, in the row font, bold, lined up with the cells (a hidden tick in front keeps the alignment);
- links through `_lh()` or `$GLOBALS['config']['base_url']`: an admin page has no `<base>`, so `href="admin/…"` on `admin/orders/` would resolve to `admin/orders/admin/…`;
- a short "Nothing to show" (`cms_list_list_message`) when a filter or search finds nothing;
- states and amounts as plain text like the other cells (Paid, Unfulfilled; `£0.00`, sign and two decimals); a problem marker (a small red "!") where something needs attention.

---

## Detail page

- Toolbar: back arrow to the list (on the record's filter) and the title; states in the title in parentheses, lower case: `#152 (paid) (unfulfilled)`.
- Body in two columns, 50:50, of bordered cards with a bold heading each: the main content on the left (lines, summary, history), facts and short edits on the right (customer, address, status). Same width as the toolbar, no padding on the container.
- Edits post with `get_ajax_panel` and a `cms_do`: the action runs, the panel comes back fresh, and the JS replaces the panel in place (no reload, no full-page form post).
- History, when the record has one: a comment box (plain text, no formatting) above the lines, newest first; each line a narrow time column (`17 Nov 2026 16:47`) and a wide text column, no heading row; comments bold; the CMS user's name after a line they caused.
- The technical CMS form (`admin/cms_page_panel/{id}/`) stays reachable from the menu; the page does not need to link it.

---

## Copy

Labels of an admin tool page (column headings, button texts) may be fixed English in the template, as on other CMS admin pages. Anything a site visitor or customer sees still comes from CMS fields.

---

## Checklist for a new admin page

- [ ] `admin::<page>()` method; module-missing message; access check.
- [ ] Menu item in the owning module's `config.json`.
- [ ] `cms_toolbar` with title and right-aligned CMS controls; selected state with `cms_tool_button_active`.
- [ ] Filters as buttons (ajax, filter kept in `?view=` without a page load); search from 3 characters with the selected-state rules above.
- [ ] Rows from a helper panel: `_panel()` on landing, ajax after; `ajax.input`; access check in the helper.
- [ ] Rows in the CMS list look: ticks, one link per row, bold headings row; absolute links.
- [ ] SQL in the model; search and filters in SQL.
- [ ] Panel JS: `<panel>_init` with the `<panel>_ok` guard (`_resize` / `_scroll` only if the page needs them).

## Not yet in line

- Older CMS pages (Data and backup, Global css, Search) use parts of this, not all.
