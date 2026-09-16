# Imagemaker backlog

- [x] **Product / taxonomy style FK** — `imagemaker` extends `shop/product`, `shop/subcategory`, `shop/category` with subtitle “Imagemaker” + `imagemaker_style_id`
- [x] **Productthumb composite** — cascade style; `original_artwork` onto style background; `product_{id}_{hash8}.png`; 15s `$GLOBALS['timer']` skip; HTML cache hash
- [x] **Product page gallery** — chain shop → shopify → imagemaker → timmy; composite after variant images; Timmy no longer overwrites `images`
- [x] **`provides.image_compose`** — shop consumes via `shop_image_model` / `run_action`; shopify/timmy do not load `imagemaker_model`
- [x] **Product dimension rules** — settings repeater `frame`/`black` → style; PDP gallery `ids`; hide Shopify images for those variants; cascade style remains main image + thumbs
- [x] **Product dimension rules via shop dims** — `shop_image_model` matches `product_dimension` / `dimension_value` to master item dim slug + value (not raw Shopify options). Field names unchanged. See [`modules/shop/docs/dims.md`](../../shop/docs/dims.md).
- [ ] **Colour / masks on thumbs** — after warp, optional `add_colour` with `colour_mask_1` / product colours (and alt / `colour_mask_2`) for productthumb (and related storefront surfaces)
- [ ] **Cron / purge** — optional cleanup of unused `upload_path/imagemaker/*` (including stale `product_*` PNGs)
