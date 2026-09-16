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

- [x] **`shop/currency_selector`** — embeddable currency dropdown (`currency_ids` optional → all; `default`; `add_empty`); writes `.currency_selector_container[data-value]` + `#currency_selector_value`
- [x] **`shop/products` grid** — placeable shell + `products_menu` + ajax `products_grid` + `product_thumb` (local catalogue filters only)
- [x] **Local dims / product types** — see [`dims.md`](dims.md). Cascade, mixed cart blocked. No paid clones. Phase 1 review: dual keys dropped, cart errors from settings, `product_type.panel` kept as legacy.
- [x] **Shopify variants → shop dims** — sync upserts `product_dim` / `product_type` / `product_item` + `shopify_variant_id`. Storefront and imagemaker rules use shop items (gallery `ids` = item ids). Raw Shopify options stay in disk cache for sync only.
- [ ] **Custom dim-value admin input** — `product_item.dims[].value` is still a text field. Wire `shop/dim_value_select` (or a custom input) to list only values from that row’s `product_dim`.
- [ ] **Dave / site styling** of `shop/products*` via extends
- [ ] **`original_artwork` on `shop/product`** — today Timmy defines the field and Shopify syncs it when Timmy is on. Shop `shop_image_model` already reads that name for `image_compose`. Move the field (and src hash) onto shop; Shopify extend only fills it.
- [ ] **Productthumb HTML cache** still lives in **shopify** (`invalidate_product_display_cache`). Shop image compose already calls it when shopify is installed; move the cache into shop/`product_thumb`.
