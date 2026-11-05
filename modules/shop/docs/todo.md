# Shop module backlog

## Cross-module: subscriptions

Shop settings host the **Subscription checkout provider** field (`subscription_checkout`, added by the subscription module extend of `shop/shop`).

Remaining Stripe / subscription architecture notes (validation gap, webhook hardening, legacy payment panel, etc.) are tracked in:

**[`modules/subscription/docs/stripe_vs_subscription_issues.md`](../../subscription/docs/stripe_vs_subscription_issues.md)**

Do not re-implement subscription checkout inside shop cart; keep `shop_checkout` (cart) and `subscription_checkout` (pricing Purchase) as separate provides services.

## Fulfilment

Email fulfilment (`provides.shop_fulfilment` → `shop/fulfilment_email`) is on `shop/category` (provider + recipient repeater). Other providers (printer API) can register the same service later.

- [ ] Attach print files to SMTP (today: CMS `/files/get/` URL in the body when `print_file` exists)
- [x] **Empty fulfilment provider** — skip, no log. CMS does not fulfil those lines until the category has a provider.
- [x] **Two-axis order status** — physical `status` (draft / abandoned / unfulfilled / in_progress / fulfilled / **finished** / cancelled) and `payment_status`. Line is the sale atom (`product_item_id`). CMS emails only when paid + unfulfilled. Finished posts listed stock. Mapping: [`shop.md`](shop.md) Order status.
- [x] **Finished sticky** — admin save cannot leave `finished` (or a row with `stock_posted_time`). Shopify apply/rollup already stay finished.
- [x] **Shopify per-line fulfilment** — webhook + order sync map each Shopify line (`fulfillment_status` / `unfulfilledQuantity`) onto the CMS line. Bag status is fallback only. See [`shopify/docs/sync.md`](../../shopify/docs/sync.md).
- [ ] **Child / sub-orders** — split when payment or delivery rules differ (partial pay, mixed fulfilment).
- [ ] **`shop/payment` rows** — one record per capture / refund instead of only `payment_status` on the order.

## Shop-local

Second dims + cart review (2026-11-04):
- [ ] **Split checkout for mixed carts** (later) — the mixed-cart check was removed (2026-11-04): one cart may hold local items and Shopify (or other provider) lines. Checkout must then split by source: one checkout per provider (e.g. Shopify lines to `shopify/checkout`, local lines to the local flow), with one order per part or child orders (see Fulfilment "Child / sub-orders"). Until then a mixed cart goes to the single `shop_checkout` provider as today.
- [x] **Draft carts held stock forever** — shop setting `cart_hold_minutes` (default 60), `order.cart_active_time`, `cart_refresh_hold` on add/open/remove, line warning when stock is gone. See [`shop.md`](shop.md) § Cart hold.
- [x] **Header cart builds the popup on every page** — header reads only `get_order_quantity` (SQL `SUM` on the `quantity` table column); lines and popup only on open/remove/visible. Homepage with a 1-line cart: 147 → 117 queries (no cart: 101).
- [x] **Unsanitised visitor input on order lines** — `create_order_line` takes `item`/`image` from the product and cleans `attributes` (`clean_line_attributes`); local adds need a product item. See [`shop.md`](shop.md) § Line attributes. Other installs: clean old lines once on deploy.
- [x] **Product card "sold out" ignores item stock** — `product_thumb` uses the default variant (offered + sellable, shared held map). Product field "Total available" removed (definition + stored params); card labels from a stored `shop/product_thumb` settings row, no hard-coded fallbacks.
- [x] **Same variant adds a second line** — `create_order_line` merges into the line with the same `product_item_id` and attributes (`_merge_into_order_line`). Product field `allow_multiline` ("Allow multiple lines", default No): Yes keeps one line per add.
- [ ] **Price change notice when the cart is closed** (later) — merging another add of the same variant sets the line to the current price immediately (`_merge_into_order_line`). The "Price changed from {{old}} to {{new}}" notice is only rendered when the cart popup is open or Checkout is clicked, so this add is silent. Acceptable for now. Later, show that notice somewhere the visitor can see it without the cart already being open.
- [x] **HTML notices instead of `window.alert`** — no alerts left in shop JS. `cart_add_items` resolves `{ ok, error }` and the caller shows it (`product.js`: `.product_add_error`); the cart popup has a hidden `.cart_popup_message` (`cart_show_message`) for checkout messages.
- [x] **No direct post reads in shop panels** — `basket`, `checkout`, `order_operations` read `$params`. Fixed on the way: `order_operations` could hide any order line by id (now only the visitor's own order, via `shop_model::delete_order_line`; `order_model` removed); checkout accepted any id as delivery method and added one delivery line per cart line; checkout address fields are cleaned and the email validated. Legacy `productbuy` / `productdims` still read post (removed with them).
- [x] **Cart contracts / layers** — markup in `cart.tpl.php`; JS messages from cart settings; no template re-checks; stored settings rows for `shop/cart` and `shop/product` (no serve-time defaults); `cart_ok` guard; one key each (`quantity`, `line_id`, `$params` only, no `expected_price`, `order_line_item_id` without `ref_id`). Order lines migrated (`qty` params removed). See [`shop.md`](shop.md) § Cart contracts. Other installs: Shopify module must read `quantity`, post `line_id`.

- [x] **`shop/currency_selector`** — embeddable currency dropdown (`currency_ids` optional → all; `default`; `add_empty`); writes `.currency_selector_container[data-value]` + `#currency_selector_value`
- [x] **`shop/products` grid** — placeable shell + `products_menu` + ajax `products_grid` + `product_thumb` (local catalogue filters only)
- [x] **Local dims / product types** — see [`dims.md`](dims.md). Cascade. No paid clones. (Mixed carts were blocked; allowed since 2026-11-04.) Phase 1 review: dual keys dropped, cart errors from settings, `product_type.panel` kept as legacy.
- [x] **Shopify variants → shop dims** — sync upserts `product_dim` / `product_type` / `product_item` + `shopify_variant_id`. Storefront and imagemaker rules use shop items (gallery `ids` = item ids). Raw Shopify options stay in disk cache for sync only.
- [x] **Custom dim-value admin input** — the product page Variants grid uses `shop/dim_value_select` dropdowns for the product type’s dims.
- [x] **Variant reserved column** — the Variants grid shows `held_qty_map` per item (unfinished orders and held carts). Not editable.
- [x] **Dims: default variant** — one rule (`default_master_item`) for `shop/product` and `product_thumb`. `storefront_masters` drops incomplete and repeated-combination rows from storefront and cart, and logs them. Grid cells no longer refuse an in-between duplicate. See [`dims.md`](dims.md).
- [x] **Dims: held stock hot path** — `held_qty_map`: one order line query per product (`product_id`), counted by `product_item_id` only. Presentation and the default-variant pick share it. Product page with 5 count-mode variants: 219 → 175 queries.
- [x] **Panel-table filters** — `order_line.order_id`, `order_line.product_id`, `product_item.product_id` are indexed panel-table columns, so those `get_list` filters run in SQL. See [`shop.md`](shop.md) § Panel tables.
- [x] **Order lookups in SQL** — `order.json` has `cart_key` (readonly), and `cart_key`, `user_id`, `status` are panel-table columns, so the guest and account cart lookups filter in SQL.
- [x] **Dims: picker by value id** — picker, variant JSON, cart and `normalize_dims_map` run on `product_dim_id` → value id only. Fixed the `data-variants` attribute, which was broken (unescaped JSON quotes) so the picker never had variants.
- [ ] **Imagemaker dim rules on ids** — rules still store dim slug + value id or label (`shop_image_model::_item_dim_match`). Needs the image-compose provider's definition to use a dim fk + value id, plus a data migration.
- [x] **Dims: dead code** — order-clone `order_id` filters and "clone" help text removed; `dim_value_select` keeps only the grid-log path (no `do=set_dim`, `__construct` redirect, ajax branch, or `dimension` key).
- [ ] **Legacy: remove `shop/productbuy` / `shop/productdims`** once no other project places them. Not placed on any page here. Remove with them: their tpl/js/scss/definitions, `config.json` `productdims` entry and the two "Shop settings" menu entries (`productbuy_settings`, `productdims_settings`), `shop_model::get_product_variations` / `get_dim_label` / `get_dim_value_data` wrappers, `shop_dim_model::get_product_dim_variations` and `cartesian_items_for_type`.
- [ ] **Dave / site styling** of `shop/products*` via extends
- [ ] **`original_artwork` on `shop/product`** — today Timmy defines the field and Shopify syncs it when Timmy is on. Shop `shop_image_model` already reads that name for `image_compose`. Move the field (and src hash) onto shop; Shopify extend only fills it.
- [ ] **Productthumb HTML cache** still lives in **shopify** (`invalidate_product_display_cache`). Shop image compose already calls it when shopify is installed; move the cache into shop/`product_thumb`.
