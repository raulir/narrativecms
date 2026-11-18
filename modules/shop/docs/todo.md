# Shop module backlog

## Cross-module: subscriptions

Shop settings host the **Subscription checkout provider** field (`subscription_checkout`, added by the subscription module extend of `shop/shop`).

Remaining Stripe / subscription architecture notes (validation gap, webhook hardening, legacy payment panel, etc.) are tracked in:

**[`modules/subscription/docs/stripe_vs_subscription_issues.md`](../../subscription/docs/stripe_vs_subscription_issues.md)**

Do not re-implement subscription checkout inside shop cart; keep `shop_checkout` (cart) and `subscription_checkout` (pricing Purchase) as separate provides services.

## Fulfilment

Email fulfilment (`provides.shop_fulfilment` → `shop/fulfilment_email`) is on `shop/category` (provider + recipient repeater). Other providers (printer API) can register the same service later.

- [ ] Attach print files to SMTP (today: CMS `/cms/file/` URL in the body when `print_file` exists)
- [x] **Empty fulfilment provider** — skip, no log. CMS does not fulfil those lines until the category has a provider.
- [x] **Two-axis order status** — physical `status` (draft / abandoned / unfulfilled / in_progress / fulfilled / **finished** / cancelled) and `payment_status`. Line is the sale atom (`product_item_id`). CMS emails only when paid + unfulfilled. Finished posts listed stock. Mapping: [`shop.md`](shop.md) Order status.
- [x] **Finished sticky** — admin save cannot leave `finished` (or a row with `stock_posted_time`). Shopify apply/rollup already stay finished.
- [x] **Shopify per-line fulfilment** — webhook + order sync map each Shopify line (`fulfillment_status` / `unfulfilledQuantity`) onto the CMS line. Bag status is fallback only. See [`shopify/docs/sync.md`](../../shopify/docs/sync.md).
- [ ] **Child / sub-orders** — split when payment or delivery rules differ (partial pay, mixed fulfilment).
- [ ] **`shop/payment` rows** — one record per capture / refund instead of only `payment_status` on the order.

## Shop-local

- [x] **`shop/image_item_ids` input root** (review 2026-11-12; CMS big plan item 3, done 2026-11-13). The admin field in the product `images` repeater had `.image_item_ids_container` as its root, not `.cms_input` with `data-cms_input_height` (the CMS input contract, `cms/docs/cms_input.md` § Form layout), and printed no label or help.
  - **Effect:** the form grid gave it one 3.5 rem row, so a product with several variants would have its checkbox list cover the fields below.
  - **Fix:** root `cms_input image_item_ids_container` with `data-cms_input_height` from the choices (`image_item_ids.php`: the 3 rem label plus 2 rem per choice, in 3.5 rem rows; 2 rows with no variants), and the "Variants" label and help through `cms_input_label()`.
  - **Check:** `ai/tmp/shop_image_item_ids_check.js` with 0, 1, 2 and 8 temporary variants (`shop_variants_fixture.php`, removed again): right height, label shown, choices inside the box.

Paid order → site user (2026-11-15, [`shop.md`](shop.md) § Paid order → site user):
- [x] **Delivery fields on the user** — shop extends `user/user`; a paid order finds or creates the user by buyer email and copies the delivery fields.
- [ ] **Module dependency: shop needs user** — for now the user module is expected to be installed with shop; without it nothing is attached. Declare it when modules get a "needs" list (CMS).
- [ ] **A buyer can overwrite another user through the email** — the buyer email typed at payment is not verified. Whoever checks out with an email that another user already used gets the order attached to that user and overwrites that user's saved delivery fields (name, address, phone). Password and access are not touched. Decide the rule: e.g. update an existing user that has a password only when the buyer is logged in as that user, or keep the order's details on the order only.
- [ ] **Use the saved delivery fields** — nothing reads them yet (prefill at checkout, account page with orders).
- [ ] **`ai/tmp/stripe_order_webhook_test.php`** leaves the user it now creates (`buyer@example.com`); remove it in that test's cleanup.

E-commerce review 2026-11-16 (running the shop day to day; full text `ai/shop_review_2026-11-16.md`):
- [x] **1. Admin cannot see what is in an order** — Shop → Orders page and order page (2026-11-17, § Orders admin in [`shop.md`](shop.md)); order history `shop_order_log`.
- [ ] **2. Buyer email picks and changes a user account** (input trust, highest priority of the open items) — after payment `attach_order_user` finds the site user by the email typed at Stripe (not verified), attaches the order, **overwrites that user's saved delivery name, address and phone**, and creates an account silently when there is none. Both now show in the order History, but nothing stops them. See also "A buyer can overwrite another user" below.
  - Fix:
    - an order bought **while logged in** attaches to that user and may update their saved delivery fields;
    - a guest order attaches to an existing user only as "order with this email": no change to their fields, shown in their account only once their email is verified;
    - creating an account for a guest buyer becomes a Shop setting "Create an account for buyers" (No / Yes); with Yes the confirmation email says so and links to setting a password.
  - Item 7 builds on this.
- [x] **3. Refunds and cancellations after payment** (2026-11-17, plan `ai/shop_review_2026-11-16.md`) — `set_order_refund` (Stripe `charge.refunded`: Partially refunded / Refunded, History, refund notice to the shop), cancel note on paid orders, **Return to stock** per line on finished orders. See § Refunds and § Return to stock in [`shop.md`](shop.md). Other installs: run `ai/setup_order_email.php` (refund notice texts). Not done: a real Stripe refund test (needs the account).
- [x] **4. Buyer hears nothing after the confirmation** — dispatch email with tracking number, sent from the order page on Fulfilled (2026-11-17, plan `ai/small_plan_dispatch_email.md`). Other installs: run `ai/setup_order_email.php` (stores the new Order emails texts).
- [x] **5. Consumer information (UK distance sale)** — terms line in the buyer emails, terms acceptance and note at Stripe Checkout (setting, off until the Dashboard Terms URL is set), VAT line on the product page and under the cart total from Shop settings → VAT (2026-11-17, plan `ai/shop_review_2026-11-16.md`). Other installs: run `ai/setup_consumer_info.php`.
- [x] **6. Cart quantity** — − / + on cart lines; + checks stock with `cart_add_fits`, − at 1 removes the line (2026-11-17, § Line quantity in [`shop.md`](shop.md)). Other installs: run `ai/setup_cart_quantity.php` (stores the two new cart labels).
- [ ] **7. Buyer cannot see their order** — Order received is a generic thank-you; there is no "My orders". After item 2.
  - Order received shows the order number and summary for the order just paid. The Stripe return URL passes a one-time token, not the order id. The page is David's (`david/hero` today, see the david todo).
  - A `shop/account_orders` panel for logged-in users: their orders with date, total, status and tracking number.
- [x] **Orders admin findings** (small review 2026-11-17, done the same day): payment status is text only on draft / abandoned carts (and refused by the model); deleting an order anywhere removes its lines and history through `shop/order` `on_delete` (CMS core hook added for it).
- [ ] **Order lines with `order_id` 0** — two local lines (308, 310) were created without an order (early test data); they hold no stock. Delete them by hand, or find what creates lines without an order if more appear.
- [ ] **8. Problems needing attention** — the Orders list already shows a red "!" for a payment note, and History records every email as queued or not. Still to do:
  - **New order email sent at once** (`send_now`, not queued): today the queue gives up after 5 tries and nobody is told. A failure goes to the technical admin address and the History.
  - **"Attention" filter on the Orders page:** a payment note, or paid and unfulfilled for more than N days (Shop setting).
  - **Last-unit line in "New order":** when the order took the last unit of a variant.

Review 2026-11-16 (checkout, paid orders, module structure; full text `ai/big_plan_2026-11-16.md`):
- [x] **Delivery line added before payment** — a visitor can call `shop/checkout` `cms_do=delivery_method` on their own cart. `set_order_paid` now replaces delivery lines already on the order with the one that was charged.
- [x] **Payable-order contract** (2026-11-16) — `shop_model::checkout_check($order_id)` and `get_payable_order($order_id)`; the cart popup, `stripe/checkout`, the paid-amount check and the email summary read them. `stripe_order_model` no longer reads order lines, currencies, dims or delivery rows. See [`shop.md`](shop.md) § Payable order.
- [x] **Stock hold vs payment page lifetime** (2026-11-16) — the provider calls `hold_cart_until` when it opens a payment page (`order.cart_hold_until`, read by `order_holds_stock`), so the hold no longer depends on the Cart hold setting. `set_order_paid` notes an oversold payment on the order.
- [x] **Order emails moved out of the model** (2026-11-16) — texts are `shop/order_email` settings (Shop settings → Order emails), the body is `templates/order_email.tpl.php`; `order_summary_text` is gone. Other installs: run `ai/setup_order_email.php` (creates the settings row, moves the three values from Shop settings).
- [x] **Draft carts are removed** (2026-11-16) — repeating task `shop/cart_cleanup` (daily) deletes unpaid drafts with no activity for Shop settings → Delete carts after (days), default 30. Other installs: `ai/setup_cart_cleanup.php` stores the setting and adds the task.
- [x] `cart.php` returns arrays (replies go through `ajax.output`), has `ajax.input`, and `cart.js` calls actions with `get_ajax` (2026-11-16). Providers that still print raw JSON keep working: `cart_reply()` reads both shapes.
- [x] Buyer phone in `shipping_phone`: left as it is on purpose. The payment page collects one phone, as the delivery contact; `fulfilment_email`, the user extend and `attach_order_user` already read it as that.
Stripe cart checkout for David (2026-11-15, plan `ai/plan_stripe_shop_checkout.md`):
- [x] **Cart checkout through Stripe** — `stripe/checkout` provides `shop_checkout` (Stripe Checkout page, delivery methods as shipping options, UK addresses). Paid by webhook or return URL. See [`stripe/docs/shop_checkout.md`](../../stripe/docs/shop_checkout.md).
- [x] **`set_order_paid` broken** — loaded the missing `mail/mail_model` / `shop/mail_order` and wrote Worldpay meta. Now the provider-neutral paid step (payment fields, buyer, address, delivery line, order emails through `cms_email_model`), idempotent. See [`shop.md`](shop.md) § Paid orders.
- [x] **Paid orders without a fulfilment provider finished at once** — `maybe_finish_order` skipped lines no provider fulfils, so such an order was Finished on payment: stock released but never posted (oversell), and shown finished before it was sent. Now every item line counts; admin status Fulfilled / In progress / Cancelled is applied to the lines, and Finished on a paid order marks open lines fulfilled first, so stock posts.
- [ ] **Delivery sets and tax multipliers** are not used by Stripe checkout (every shown delivery method is offered). Restrict methods per product type when a shop needs it.
- [x] **Duplicate panels removed (2026-11-16)** — one panel per service ([`shop.md`](shop.md) § Services). Removed: `productbuy`, `productdims`, `basket`, `basketmini`, `order_operations`, `shop/cms_input_provides`, `worldpay_check.json`, the model functions and Shop settings only they used (`dimension_*`, `price_suffix`). `shop/checkout` is kept as its own service. Other projects: run `ai/cleanup_removed_shop_panels.php` before taking this version.
- [ ] **On-site checkout rework** — `shop/checkout` (delivery method, address, review) is kept for payment providers that do not collect delivery details, but does not work as it stands: its payment tab is empty (it embedded another project’s `stripe/payment`), its settings row is not stored (the template prints undefined labels), `checkout.js` still calls the removed `shop/worldpay_check`, and delivery methods come from `delivery_model` (sets) while Stripe checkout offers every method. Rework: stored settings, one delivery source, address through the same cleaning as `set_order_paid`, payment through the payable-order contract (review 2026-11-16).
- [x] `get_order_by_cart_key` loads `cms_page_panel_model` itself (2026-11-16).
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
- [x] **Legacy pickers `shop/productbuy` / `shop/productdims` removed** (2026-11-16, with the other duplicate panels).
- [ ] **Dave / site styling** of `shop/products*` via extends
- [ ] **`original_artwork` on `shop/product`** — today Timmy defines the field and Shopify syncs it when Timmy is on. Shop `shop_image_model` already reads that name for `image_compose`. Move the field (and src hash) onto shop; Shopify extend only fills it.
- [ ] **Productthumb HTML cache** still lives in **shopify** (`invalidate_product_display_cache`). Shop image compose already calls it when shopify is installed; move the cache into shop/`product_thumb`.
