# Imagemaker backlog

- [x] **Product / taxonomy style FK** — `imagemaker` extends `shop/product`, `shop/subcategory`, `shop/category` with subtitle “Imagemaker” + `imagemaker_style_id`
- [x] **Productthumb composite** — cascade style; `original_artwork` onto style background; `product_{id}_{hash8}.png`; 15s `$GLOBALS['timer']` skip; HTML cache hash
- [x] **Product page gallery** — chain shop → shopify → imagemaker → timmy; composite after variant images; Timmy no longer overwrites `images`
- [ ] **Per-variant imagemaker images** — later: composite (or style) per Shopify variant; place with variant `ids` ahead of the default shared composite
- [ ] **Colour / masks on thumbs** — after warp, optional `add_colour` with `colour_mask_1` / product colours (and alt / `colour_mask_2`) for productthumb (and related storefront surfaces)
- [ ] **Cron / purge** — optional cleanup of unused `upload_path/imagemaker/*` (including stale `product_*` PNGs)
