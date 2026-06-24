# cms_popup_yes_no panel

Panel: `cms/cms_popup_yes_no` (template-only, no controller)

## Usage

```javascript
get_ajax_panel('cms/cms_popup_yes_no', {'text': 'Are you sure?<div>...</div>'}, function(data){
	panels_display_popup(data.result._html, {
		'yes': function(){
			// confirmed action
		}
	});
});
```

- Pass custom HTML in ajax param `text` (default: `Are you sure?`)
- Wire only `yes` in `panels_display_popup` params; dismiss needs no callback
- Dismiss same as **No**: top-right close (`cms_close.png`), **No** button, or **Esc** — all handled in [`panels_display_popup()`](../../modules/cms/js/cms_site_main.js)

## Files

| Role | Path |
|------|------|
| Template | `modules/cms/templates/cms_popup_yes_no.tpl.php` |
| SCSS | `modules/cms/css/cms_popup_yes_no.scss` |
| JS | `modules/cms/js/cms_popup_yes_no.js` (opacity helper only; behaviour in `cms_site_main.js`) |

## Examples

Image delete confirm — [`cms_images.js`](../js/cms_images.js); page delete — [`cms_page.js`](../js/cms_page.js).