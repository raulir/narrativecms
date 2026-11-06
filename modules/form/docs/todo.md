# Form module backlog

**Legend:** `[ ]` open · `[x]` done

---

## Urgent

- [ ] **`form/do_send` is an open mail relay** — an anonymous post `panel_id=form/do_send&cms_do=send_form&emails[]=…&title=…` without `id` merges no block, and `form/basic` has no settings, so `emails`, `autoreply_text`, `mailchimp_api_key`, … come from the post and the site mails the posted data to any address. Fix: require `id` to be a shown `form/basic` block and take recipients, texts and keys from it only; post the form fields under one key and keep only the block's `elements` names; add `ajax.input` (`cms`, `id`, `recaptcha_token`, `cms_page_title`, the fields key); read `$params`, not `input->post()`. Found 2026-11-06 (cms ajax review)

## Panel JavaScript — `*_ok` / `*_destroy`

Contract: [`cms_panel_js.md`](../../cms/docs/cms_panel_js.md).

- [ ] **`*_ok` refactor — form module** — basic form, form_admin, …
