# Shopify product sync

## shop/product `panel_params` chain

On storefront product pages, extends run in module order. This project:

**shop → shopify → imagemaker (style FK) → timmy**

| Extend | Role |
|--------|------|
| `shopify/shop_product` | `get_product_by_id` recheck; merge catalogue fields; `shop_image_model` compose. Dims come from shop items, not raw options. |
| `imagemaker/shop_product` | Style FK only (definition). Compose is `provides.image_compose`. |
| `timmy/shop_product` | Presentation only (no catalogue overwrite) |

## Storefront Shopify checks

Stamp: **`shopify_checked_at`** (unix time of last successful Admin fetch).

| Surface | Shopify Admin |
|---------|----------------|
| **Grids / productthumb** (category, filter, related, latest) | **Never.** CMS row only. HTML file `cache/productthumbs/productthumb_{id}.html` is reused until this product’s `update_time` is newer than the file (or the file is missing). Save / Shopify refresh that changes catalogue fields purges that file (`invalidate_product_display_cache`). |
| **Product page** | Recheck if `shopify_checked_at` older than **`product_page_recheck_ttl`** (default 300s). Older than **`shopify_data_ttl`** (default 86400s = 1 day) forces a live Admin fetch; otherwise 300s disk then API. |
| **Cart add** | Always try live Admin refresh (`force=1`) **when the product has `shopify_id`**. Use live variant price when the call works. If Shopify is down, keep posted/CMS price (checkout checks again). CMS-only products (no `shopify_id`) skip refresh and stay visible. |
| **Cart open / badge** | None (local order lines). |
| **Admin Sync** | Admin API (not page traffic). |

`max_refresh_time` (default 30s) is the per-request budget for **product page** live Admin calls.

Products with no `shopify_id` are CMS-local (dims/stock via `shop/product_type`). `get_product_by_id` / `refresh_product` return the CMS row and must **not** hide them.

Shopify **options/variants** are written into shop dims on refresh **and** on TTL-hit PDP (`_product_from_cms`): shared `shop/product_dim` by slug, `shop/product_type` for the option set (skipped when there are no dims), one `shop/product_item` per sold variant (`shopify_variant_id`), image `ids` = item ids. Storefront does not read raw options. See [`modules/shop/docs/dims.md`](../../shop/docs/dims.md).

## Site config (Admin GraphQL)

Category taxonomy and `original_artwork` use **Admin GraphQL** (`graphql` / `get_product_admin_extras`). REST product list/import can work with only `shopify_api_*` keys; GraphQL needs a **store host**.

| Config key | Role |
|------------|------|
| `shopify_api_token` | Admin API token (shared with REST) |
| `shopify_store_domain` or `shopify_host` | Store host — **required for GraphQL** (REST may fall back to a default host) |
| `shopify_admin_api_version` | Optional Admin API version |
| `shopify_storefront_api_version` | Optional fallback if admin version unset (then code default) |
| `shopify_api_key` / `shopify_api_secret` | REST SDK Context only |

If host or token is missing for GraphQL, product sync status includes **`no graphql conf`** (e.g. `Found 69, new 0, …, refresh 0, no graphql conf - done`). REST sync still runs; category map + original artwork will not.

## Product list source

Sync loads products only from the custom collection with handle **`frontpage`** (admin title often **Main**). Products not in that collection never appear in CMS.

Limit: 250 products per collection list call.

## Status line

Example: `Found 69, new 0, stale 0, updated 0, refresh 1 - done`

| Field | Meaning |
|-------|---------|
| **new** | Shopify products not yet in CMS (created this run) |
| **stale** | Needed full refresh (`updated_at` / `sync_needed`) |
| **updated** | New + stale successfully written this run |
| **refresh** | Idle maintenance only — one oldest-checked product when new/stale are both 0 (rotates each click; not a stuck product) |

## Category mapping

Shopify **Standard Product Category** (GraphQL `product.category.fullName`, not REST `product_type`) is mapped to CMS `shop/category` via settings **category_maps**: text **Shopify match** + FK **Shop category** (`shop/category`). Defaults resolve Art/Cards by heading when present.

| Shopify path example | Typical CMS category |
|----------------------|----------------------|
| `… > Artwork > Posters…` | Art |
| `… > Cards > …` | Cards |

### Subcategories

1. Collections whose title ends with the configured **subcategory suffix** (settings; e.g. `category` or `range`) → `shop/subcategory` under the mapped shop category.
2. **Otherwise** (no matching collection, or only Main/frontpage) → catch-all **`Other art`**, **`Other cards`**, etc. (`Other` + lowercased CMS category heading). Every imported product gets a subcategory so it appears under Art/Cards filters.

### Category collections (mega menu)

`shop/category` has a **collections** repeater (`collection_id` → `shop/collection`). `shop/collection.collection_type_id` is an FK to `shop/collection_type` (heading = Shopify title suffix, e.g. `range`, `group`).

On product import/update, after organisation assign:

- Resolve category via `product.subcategory_id` → subcategory.`category_id`
- For each id in `product.collections`, **union-add** to that category’s `collections` (no duplicates)
- **Does not remove** collections when a product leaves them (edit category in CMS, or call `rebuild_category_collections_from_products()`)

Collection suffixes come from Shopify settings **collections** (each row an FK to `shop/collection_type`; the type **heading** is the suffix). Subcategory suffix (default **category**) is matched first — do not reuse that word as a collection type heading.

Mega menu (`timmy/categories`) shows one column per **Include collection types** row (type FK + string **label**), filtered by `collection_type_id`. It does **not** load all products.

The category page filter bar (right) is one dropdown per collection type that has collections in that category. Dropdown label is `shop/collection_type.menu_header` (e.g. `- range`). A lone subcategory (e.g. only Other cards) is not listed on the left — only **All {category}**.

One-off backfill after deploy: `php cache/_rebuild_category_collections.php` (or model method above).

## Original artwork (Timmy)

Metafield **`custom.original_artwork`** (`file_reference` → MediaImage/GenericFile) is scraped into product field **`original_artwork`** when the **timmy** module is installed. Hash `original_artwork_src_hash` avoids re-download when unchanged.

## Print file

Metafield **`custom.print_file`** (`file_reference` → GenericFile, one PDF / Illustrator `.ai` / ZIP) is scraped into **`shop/product.print_file`** whenever the metafield has a URL — not limited to Art. Hash `print_file_src_hash` skips re-download when unchanged. Empty metafield clears the CMS file. Fulfilment can use this later; sync does not filter by category.

## Admin GraphQL

Category + metafield URL use Admin GraphQL (`shopify_product_model::graphql` / `get_product_admin_extras`). Same access token as REST.

## Shopify order id (checkout)

Hosted Storefront checkout (`checkoutUrl`) has **no return / callback URL**. After the buyer pays, Shopify shows its own thank-you page. The CMS learns the Shopify order id from webhooks, with Admin GraphQL as fallback.

Shopify has two ids. **`shopify_order_id`** is the API id (`legacyResourceId`, e.g. `7816209891573`) — GraphQL GID, matching, Admin URL. **`shopify_order_name`** is the Admin name (`name`, e.g. `#1267`). Fulfilment emails and `{{shopify_order_id}}` / `{{shopify_order_name}}` placeholders use the **name**. Cron backfill fills name on paid orders that already have an API id.

Cart attribute **`cms_order_id`** (CMS `shop/order` panel id) is set on `cartCreate` / kept on reuse. Shopify copies cart attributes onto the order as **customAttributes** / REST **note_attributes**. **That attribute is the only match key.** Faire, POS, and other-channel Shopify orders have no `cms_order_id` — webhooks acknowledge them (HTTP 200) and ignore them. We never create a `shop/order` from Shopify.

The custom app for Admin GraphQL is **CMS** (legacy custom app). It already has **`read_orders`**. Cron stamps `cms_order_id` onto still-open Storefront carts that predate this field.

Product sync (`shopify_cron_sync` / settings **Sync products**) is catalogue only. **Orders** are a separate job (`shopify_cron_orders` / settings **Sync orders**). Same Admin token (`read_orders`). Webhooks are the fast path; cron and the settings button call the same apply.

| Path | What |
|------|------|
| **Webhook** `{base}shopify/webhook/` | `orders/create`, `orders/paid`, `orders/updated` (JSON). HMAC. Writes `shopify_order_id`, times, buyer/shipping, **payment_status**, paid **lines**, **per-line fulfilment**. Closes the draft; queues CMS fulfilment for still-unfulfilled paid lines. Shopify in progress/fulfilled **on that line** skips CMS email for it. `orders/edited` is a **diff only** — do not use it; **Order update** (`orders/updated`) is the full order. |
| **Reconcile** (`do=reconcile` / dead cart on checkout click) | Storefront cart gone → Admin recent orders (14 days) by **`cms_order_id` only** → apply if matched. If Admin query/cart query **fails**, leave the draft. If cart is gone and there is no Shopify order: **`status=abandoned`**, `payment_status=unpaid`, no `paid_time`, no fulfilment. |
| **Cron / settings Sync orders** `shopify/shopify_cron_orders` | (1) Unsynced drafts: attach or abandon. (2) Linked orders (`shopify_order_id`, not Finished): Admin detail → payment, lines, SKU pointer, **per-line fulfilment**, then shop rollup/Finished. (3) CMS fulfilment for leftover unfulfilled paid lines. Time-budget ~50s. |

Per-line Shopify → CMS line `status`: `fulfilled` / `unfulfilledQuantity = 0` → `fulfilled`; partial → `in_progress`; else `unfulfilled`; order cancelled → `cancelled`. Bag `displayFulfillmentStatus` is used only when line fields are missing.

Admin API token needs **`read_orders`** (already on the **CMS** custom app). Default Admin access is the last **60 days** of orders.

The CMS app **Webhook version** should match Admin/Storefront (**2026-10**). That version only serializes webhooks **subscribed on the app**. Store **Settings → Notifications → Webhooks** are a separate list (also JSON 2026-10). Do not subscribe `orders/create` / `orders/paid` on both — you would get two POSTs and two signing secrets. HMAC accepts either `shopify_webhook_secret` (Notifications) or `shopify_api_secret` (app).

### Webhook setup (Shopify admin)

1. In the custom app (or **Settings → Notifications → Webhooks**): add HTTPS webhooks.
2. URL: `{site base}shopify/webhook/` (example: `https://sandersderoeper.com/shopify/webhook/`).
3. Format: **JSON**. Events: **Order creation**, **Order payment**, and **Order update** (`orders/updated` — full order after edits; `orders/edited` is only a delta and is ignored).
4. Signing secret (the hex string under **Your webhooks will be signed with** on the Notifications → Webhooks page): host JSON **`shopify_webhook_secret`** on the **same host** as the webhook URL (live → `config/sandersderoeper.com.json`). This is **not** the custom-app client secret (`shopify_api_secret`). Without it, deliveries get HTTP 401.
5. Custom app **Admin API access token** needs scope **`read_orders`** for the cron/Admin fallback only (webhooks do not use that token).

HMAC uses the raw POST body. Failed HMAC → HTTP 401 (Shopify retries). Unknown topics and other-channel orders (no `cms_order_id`) return **200** and are ignored. A `cms_order_id` that is not a `shop/order` row is logged as an error.

Every POST (including Faire/other channels and HMAC failures) is appended to **`cache/webhook_debug.log`**: timestamp + topic/shop/hmac/webhook_id/bytes, then the raw body, then a blank line.

### Cron task

**CMS → Repeating tasks**: add **Shopify order sync (cron)** (`shopify/shopify_cron_orders`), e.g. every 15 minutes, and **CMS email queue** so fulfilment mail actually sends. Settings **Sync orders** runs the same batch.

Paid site orders (attribute `cms_order_id` only — Faire ignored): overlay Shopify line items, **delete** leftover cart lines the buyer removed at checkout, then queue fulfilment emails via `shop_fulfilment`. Each order line gets `fulfilment_sent_time`; a later `orders/updated` only sends new unsent lines.

Buyer name and shipping are stored on `shop/order` from the webhook (and GraphQL when PII is present) so Stripe/CMS-only paid flows can use the same fields later.

### Host config

| Config key | Role |
|------------|------|
| `shopify_webhook_secret` | HMAC for **Settings → Notifications → Webhooks** (the “signed with” hex). Required when those store webhooks are used. |
| `shopify_api_secret` | Custom-app client secret. HMAC fallback only if `shopify_webhook_secret` is empty (app-created webhooks). |
| `shopify_api_token` | Admin API token. Needs **`read_orders`** for order-id fallback (GraphQL `orders`), not for receiving webhooks. |
