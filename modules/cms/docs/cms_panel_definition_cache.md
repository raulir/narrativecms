# Panel definition HTML cache (`"cache"`)

For **ajax** and **embed** panels (`ajax_panel` / `_panel`), a panel definition may declare:

```json
"cache": "300"
```

HTML is stored for that many **seconds** under:

```text
cache/{module}/{panel}/{md5_12}.html
```

Hash input: panel name + stable request params (sorted keys; skip keys starting with `_` and a few internals).

## Rules

- **`cms/*` panels are never cached** this way.
- Skipped when `force_download` cache flag is on, or `no_html` / `_no_cache` from panel action.
- **Page composition** still uses the existing per-`cms_page_panel_id` panel cache (`cache/_*.txt`); definition cache is for term/param-driven ajax panels without a page-instance cache file.

## Example

[`search/searchajax`](../../search/definitions/searchajax.json) uses `"cache": "21600"` (6 hours) for search result HTML.

See also [`cms_module_extends.md`](cms_module_extends.md) (template replace for themed result markup).
