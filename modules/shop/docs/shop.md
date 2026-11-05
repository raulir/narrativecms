# Shop module — platform e‑commerce core

## Purpose

`shop` is the **central e‑commerce module** for smaller sites: catalogue, cart contract, default local basket, checkout shell, orders, delivery, and basic storefront panels.

Installing **`shop` + one payment module** (e.g. `stripe`) should be enough for a functional shop (local catalogue, cart, checkout, pay).

Site modules (e.g. `timmy`) own branding and site-specific product UX. Connectors (e.g. `shopify`) own remote APIs and may **replace** cart/checkout drivers when present.

## Non-goals

- Shopify Admin/Storefront protocol (→ `shopify`)
- Brand-specific SCSS / customisation UI (→ site module)
- Card gateway UI (→ `stripe` or other payment modules)
- Heavy WMS / multi-warehouse (optional later module or extends)

---

## Module map

| Module | Owns |
|--------|------|
| **shop** | Products, categories, cart **contract** + **local** cart driver, checkout shell, orders, delivery, basic product/category views. Admin top-level **Shop** menu. |
| **shopify** | API tokens, sync/purge, product fields via **extends**, **`provides.shop_checkout`** handoff (`shopify/checkout`). Admin under **Shop → Shopify**. |
| **imagemaker** | **`provides.image_compose`**: warp overlay onto a print background. Shop calls it for product composites (`shop_image_model`). Optional Style FK via imagemaker **extends** of product/category/subcategory. |
| **timmy** | Site frontend, customisation fields (extends product), Timmy-only chrome. Product/cart settings under **Shop → Timmy**. |
| **stripe** | Collect payment for a payable order/session |
| **booking** | Treatments / treatment categories (moved from legacy stock) |

Site modules **may** still add behaviour that checks whether `shopify` is installed. **Basic shop flows always go through `shop`** (never call Storefront from a site panel as the long-term pattern).

---

## Catalogue

### Category collections

`shop/category` field **collections** (repeater of FKs to `shop/collection`): collections listed under that category. Each `shop/collection` has **`collection_type_id`** → `shop/collection_type`. Mega menu columns are chosen in Timmy categories settings (**Include collection types**: type FK + label string). Shopify sync **adds** collections when products in this category use them; CMS can edit the list. Removal of unused collections is manual (or a rebuild tool).

### Unified product list

| Piece | Name |
|-------|------|
| Admin list / PDP id | **`shop/product` only** |
| Base definition | `modules/shop/definitions/product.json` |
| Shopify fields | `shopify` extends → `//shop_product` → `shopify/shop_product` |
| Site (Timmy) | `timmy` extends → `//shop_product` → `timmy/shop_product` (item fields, settings, template/CSS/JS, `panel_params`) |

Stored rows use **`panel_name = shop/product`**. Config extends merge definition, assets, settings values, and PHP controllers into the target — they do not create a second catalogue list.

### Categories

| List | Panel |
|------|--------|
| Product categories | `shop/category` (Timmy UI via `//shop_category`) |
| Product subcategories | `shop/subcategory` (Timmy UI via `//shop_subcategory`; Shopify fields via shopify `//shop_subcategory`) |
| Product collections | `shop/collection` (`collection_type_id` → `shop/collection_type`) |
| Collection types | `shop/collection_type` (heading = Shopify title suffix; **Menu header** = category filter dropdown label, e.g. `- range`) |
| Product texts | `shop/producttext` |

### Local stock / dims

CMS-only size/frame/colour options, product types, and listed stock on product items: **[`dims.md`](dims.md)**.



---

## Cart (local first)

### Durable identity

- **Cookie** `shop_cart` (60 days): opaque `cart_key` on the draft `shop/order`  
- **DB**: `shop/order` + `shop/order_line` hold lines, qty, attributes, price snapshots. Each line stores `currency_id` for the unit `price`. Display uses that currency. Empty `currency_id` on old lines uses the shop currency at display time. A later currency change does not reprice the line.
- **Shop currency** for a new price is `shop_model::get_shop_currency()`. Today that is the default currency. A later resolver (user preference, country, and similar) belongs there and is not built yet.
- **Order ID** (`heading`) and Worldpay `number` are the CMS panel id as a decimal string. The same row is the cart until checkout (`status` empty) and the order after (`status` unfulfilled / in progress / fulfilled).  
- Times: **Cart created** (`created`) when the draft row is created; **Order created** (`order_created`) when it leaves draft; **Paid** (`paid_time`).  
- PHP session may cache `order_id` for the request; cookie is source of recovery after session dies  
- **User module optional** — guest cart uses the cookie. Do not load `user/user_model` unless `user` is in enabled modules (`shop_model::get_front_user()`).  

One-off backfill of empty headings: `php grok/_migrate_shop_order_heading.php`  

### Panel

| Piece | Role |
|-------|------|
| **`shop/cart`** | Basic cart: badge, popup, add/remove, checkout button |
| **Site extends** (e.g. Timmy `//shop_cart`) | Design SCSS + label settings — place **`shop/cart`** on pages, not a site-owned cart panel |
| **`shop/basket` / `basketmini`** | Older full-page basket UI (local shop checkout flow) |

Buy buttons call **`cart_add_items()`** from `shop/js/cart.js` (local only — no Storefront latency on add).

### Cart contracts

- **Settings:** all labels and visitor messages are `shop/cart` settings (stored row; no serve-time defaults in `cart.php`), including the JS messages `add_error`, `checkout_error`, `checkout_missing_error`, passed as `data-*` on `.cart_container` and read with `.data()`.
- **Markup** lives in `cart.tpl.php`; `cart.php` passes data (`dims`, `attributes` list, `description`, `notice`, `warning`).
- **Requests:** `cart_add_items(items, attributes)` posts `product_id`, `product_item_id`, `quantity`, `dims`, `attributes`. No price is posted; the server prices every line. Remove posts `line_id`.
- **Notices (no `window.alert`):** `cart_add_items` resolves `{ ok: 1, quantity }` or `{ ok: 0, error }` (error from the server, or the cart setting `add_error`); the caller shows it — `product.js` toggles `.product_added_visible` / `.product_add_error_visible`. Checkout messages go to the cart popup's hidden `.cart_popup_message` via `cart_show_message(text)`.
- **Line deletes** (`cart`, `basket`, `order_operations`) go through `shop_model::delete_order_line($order_id, $line_id)`, which only deletes a line of the visitor's current order.
- **Naming:** a row of an order or cart is a **line** (`line_id`, `shop/order_line`), never an item. "Item" is the product item (`product_item_id`).
- **Request values** are read from panel `$params` only (ajax params already contain the post), never `$this->input->post()`.
- **Line fields:** `quantity` only (definition field and table column; `qty` is gone). `product_item_id` is the sold item (`order_line_item_id`); `ref_id` is read only for delivery lines (`order_line_ref_id`).

### Header vs popup

On a normal page the header cart reads only the quantity (`get_order_quantity`, one SQL `SUM`) and renders the badge shell. Lines, prices, dims and the popup are built only when details are wanted: `do=open` (cart opened), `do=remove`, or the popup left open in the session (`cart_visible`). Local and Shopify lines may be in the same cart; there is no mixed-cart check.

### Line prices

A line keeps the price it got when added, until one of these re-prices it from the current variant price (`get_item_price_in_currency`, in the line's own currency):
- **Add** of an item that merges into an existing line (`_merge_into_order_line`): the line gets the new qty **and** the current price.
- **Cart opened** (`do=open`) and **Checkout clicked** (`do=checkout_check`): `shop_model::refresh_line_prices` updates every item line whose price changed. The line shows the cart setting **Price changed** ("Price changed from {{old}} to {{new}}."); the total follows.

`cart.js` `cart_checkout()` (the Checkout button) calls `do=checkout_check` first. The reply sets `.cart_container[data-checkout_ready]` to `1` only when no price changed and no line lost its stock; then the provider panel (`do=shop_checkout`) is called. Otherwise the updated popup is shown and checkout stops, so the visitor sees the new prices before paying; the next click goes through.

### Cart hold (reserved stock)

A draft cart reserves stock-counted items for **Shop settings → Cart hold (minutes)** (default 60) after the visitor's last cart activity (`shop/order.cart_active_time`). Older drafts reserve nothing (`shop_model::order_holds_stock`).

Activity is cart `do=add`, `do=open` (sent by `cart_load_details()` when the cart is opened), and `do=remove`. Each runs `shop_dim_model::cart_refresh_hold`: the cart's local item lines are checked against stock held by **other** orders. If all fit, the hold is refreshed. If a line no longer fits (stock taken while the cart was stale, item hidden or not offered), the hold is **not** refreshed, so a stale cart never takes stock back from a cart that now holds it, and that line shows the cart setting **Item not available** ("Item {{item}} is not available anymore.", `{{item}}` = product item title, e.g. `Poster (A3, Black)`). Removing the line refreshes the hold for the rest. Shopify lines are skipped (Shopify is the stock of record).

Cart add uses the same rule before the line is saved (`shop_dim_model::cart_add_fits`): listed stock minus other orders' holds must cover this cart's qty of the item **plus** the new qty. So a stale cart that already has the last unit cannot add another.

### Line text

A cart line shows the **product name** (the product `heading`, for example Flashy shoes), not the CMS list title of the product item.

Under that, dims are **key/value pairs**: the dim name and the chosen value (`colour: red`, `size: XXL`). That text comes from `shop_dim_model->dims_description`. It is not the catalogue list title `Product (value, value)`.

### Line attributes

Freeform key/value on lines for site customisation (Timmy fields) without shop knowing art details.

**Visitor input is cleaned at write** (`shop_model::create_order_line`), so cart, checkout, admin and fulfilment emails can print stored line data as is:
- `item` and `image` always come from the product (posted `item` / `image` are ignored).
- `attributes` go through `clean_line_attributes`: stored as a list of `{ key, value }`, tags stripped, valid UTF-8, keys max 100 and values max 1000 characters (values keep line breaks), at most 20. The line `description` is built from those cleaned values.
- A local add needs a storefront product item (`product_item_id`); otherwise the cart returns the missing-variant error. No line without a product.

**Other installs:** order lines saved before this change may still hold posted `item` / `attributes` / `description`. Clean them once (re-run `clean_line_attributes` on stored attributes, rebuild `description`, set `item` from the product) when deploying.

### Checkout via module `provides` + shop setting

Connectors advertise checkout without hard-coding module names:

```json
// shopify/config.json
"provides": [
  { "service": "shop_checkout", "panel": "//checkout", "label": "Shopify checkout" }
]
```

Boot aggregates into `$GLOBALS['config']['provides']['shop_checkout'][panel]`.

**Shop settings** (`shop/shop` → field `shop_checkout`): dropdown built by custom input `shop/cms_input_provides` (or `cms/cms_input_provides`). Stores the **panel name** (e.g. `shopify/checkout`). Default empty.

**Subscription checkout** (parallel service, not cart):

```json
// stripe/config.json
"provides": [
  { "service": "subscription_checkout", "panel": "//subscription_checkout", "label": "Stripe subscription checkout" }
]
```

Field **`subscription_checkout`** on shop settings is added by **subscription** module extend of `shop/shop`. Pricing **Purchase** calls that panel (`do=subscription_checkout`). See [`subscription/docs/subscription.md`](../../subscription/docs/subscription.md) and [`stripe/docs/stripe_checklist.md`](../../stripe/docs/stripe_checklist.md).

| Setting | Cart checkout button |
|---------|----------------------|
| **empty** | Red error: “Select shop checkout provider!” |
| **panel set** | `do=shop_checkout` → provider materialise → redirect |

### Product images via `image_compose`

Shop settings field **`image_compose`** (`cms/cms_input_provides`, service `image_compose`). Empty + exactly one registered provider → use it; none → skip compose.

`shop/shop_image_model` resolves style (product → subcategory → category FKs from the imagemaker extend), calls `run_action($panel, do=compose)`, caches `imagemaker/product_{id}_{hash8}.png`, and inserts the result into the PDP gallery / productthumb. Overlay key is product **`original_artwork`** (still a Timmy/Shopify field until moved onto `shop/product`). Shopify and Timmy must not load `imagemaker_model`.

### One-way line sync until paid; then paid Shopify lines win

| Direction | Data | When |
|-----------|------|------|
| **Site → provider** | Lines (variants, qty, attributes) | Checkout click only |
| **Provider → site** | **Never** lines while browsing | — |
| **Provider → site** | **Status** + Shopify **order id** + **paid lines** + buyer/shipping | Webhook `orders/create`, `orders/paid`, `orders/updated`; cron. Match **only** `cms_order_id`. Faire/other channels ignored. |

Local cart is truth until the order is **paid**. Then CMS lines are overlaid from Shopify; leftover cart lines the buyer removed at checkout are **deleted** (unless already fulfilment-sent).

### Order status

Two independent fields on `shop/order`. Cart identity stays **`status === ''`**. Never write physical `status` to `paid` — that was the old overloaded value. Legacy rows with `status=paid` and empty `payment_status` are treated as **unfulfilled + paid**.

The sale atom is the **order line**. It points at `shop/product_item` (dims, sku, default price). Line `status` is fulfilment of that line. Order `status` is a **rollup**, except **Finished**.

**Status** (physical / fulfilment):

| CMS `status` | Meaning | Shopify |
|--------------|---------|---------|
| `''` (draft) | Active cart | No order yet |
| `abandoned` | Checkout never became an order | Storefront cart gone, no Admin match |
| `unfulfilled` | All fulfilable lines unfulfilled | `UNFULFILLED` / partial / on hold |
| `in_progress` | Some lines in progress or mixed | `IN_PROGRESS`, `PENDING_FULFILLMENT` |
| `fulfilled` | All fulfilable lines fulfilled (or cancelled+fulfilled) | `FULFILLED` |
| `finished` | Final. Paid and lines resolved, or set by hand (loss, return, …). Listed stock posted; lines ignored in inventory. Cannot be undone. | — |
| `cancelled` | Cancelled | `cancelledAt` / `cancelled_at` set |

**Payment** (`payment_status`):

| CMS | Shopify `displayFinancialStatus` / `financial_status` |
|-----|------------------------------------------------------|
| `unpaid` | unpaid / empty |
| `pending` | `PENDING`, `AUTHORIZED`, `EXPIRED` |
| `partially_paid` | `PARTIALLY_PAID` |
| `paid` | `PAID`, `PARTIALLY_REFUNDED` |
| `refunded` | `REFUNDED` |
| `voided` | `VOIDED` |

**Stock (count mode):** listed `product_item.number` minus qty on unfinished holding lines (can be negative in reports). Abandoned / cancelled / refunded / **finished** do not hold. On finish, fulfilled/in_progress qty is subtracted from listed `number` once (`stock_posted_time`).

Child / sub-orders (when payment or delivery rules differ) and `shop/payment` rows are later.

### Fulfilment (`provides.shop_fulfilment`)

Shop **provides** `shop/fulfilment_email`. Each `shop/category` picks a fulfilment provider and a repeater of recipient emails.

CMS emails run only when **payment is paid** and **status is unfulfilled** (Shopify webhook/cron, or `set_order_paid` for Stripe/CMS later). If Shopify is **in progress** or **fulfilled**, CMS does not send and cron does not keep checking those lines. Group unsent lines by provider → `run_action`. Email provider sends **one queued mail per recipient**, containing only that recipient’s lines. `fulfilment_sent_time` is stored on each line and the line `status` becomes `in_progress`. Empty category fulfilment = CMS does not fulfil that line (no log, no stamp); set Email fulfilment on categories that should mail. Order status rolls up from lines; paid + all fulfilable lines fulfilled/cancelled → **Finished**.

Print file: if `shop/product.print_file` exists, the email includes the CMS download URL (`/files/get/…`, same as admin file download). Missing file: omit, still send.

Need **CMS email queue** on repeating tasks. Subject/intro: Shop settings → Email fulfilment.

**Reuse remote cart** (e.g. `shopify_cart_id` on order via Shopify extends `shop/order`): same cart keeps address/vouchers. If local lines fingerprint changed, **replace** remote lines from site. If remote cart is **dead** (Shopify order exists, or checkout abandoned), **close the site cart** (`status` no longer empty), clear cookie, empty cart — user starts a new draft.

Shopify is **not** cart-of-record while browsing — no Storefront on every add.

---

## Checkout and payments

```
Local cart (cookie → order draft)
  → provides.shop_checkout?  shopify/checkout materialise → Shopify pay
  → else: shop/checkout → stripe (optional)
```

Payment modules hook **payable totals / order ids**, not product grids.

---

## Storefront panels (basic shop)

Enough for a working small shop without a site theme module:

| Panel | Role |
|-------|------|
| `shop/product` | Product page (base; site/connector extend via `//shop_product`) |
| `shop/products` | Placeable product grid + filter bar (local catalogue) |
| `shop/products_menu` | Filter header (category ▾, subcategory pills, collection ▾) — embedded |
| `shop/products_grid` | Ajax product list — embedded; uses `shop/product_thumb` |
| `shop/product_thumb` | Basic product card (image, heading, price) — local only |
| `shop/category` (+ subcategory) | Category landings; site extends with `//shop_category` |
| `shop/cart` | Cart badge + popup (sites extend design) |
| `shop/basket`, `shop/basketmini` | Full basket (legacy local) |
| `shop/checkout` | Local checkout |
| `shop/productbuy`, `shop/productdims` | Legacy pickers. The storefront is `shop/product`. |

### Products grid filters

Place **`shop/products`** on a page. The shell renders `products_menu` + `products_grid`. Filter changes ajax-reload both (menu for pills/collection list; grid for thumbs).

| Control | Behaviour |
|---------|-----------|
| Category dropdown | All categories (default) or one category; changing category clears subcategory + collection |
| Subcategory pills | Only when a category is selected; “All” + each subcategory |
| Collection dropdown | Only if any collections apply in context; intersects with category/subcategory |

Queries live on **`shop_model`**: `get_products_for_filters`, `get_collections_for_filters`, collection helpers. No Shopify/imagemaker dependency.

Site modules (e.g. dave) can restyle via extends on these panels later.

Timmy storefront PDP remains **`//shop_product` extends** of `shop/product`. Timmy may still use its own `timmy/productthumb` / `timmy/products` until migrated.

---

## Timmy-specific notes

- Product dim pickers on the PDP → [`dims.md`](dims.md) (`shop_dim_model` presentation; Timmy colour swatches).  
- Customisation, overlay images, PDP labels → **`timmy/shop_product`** extend.  
- Layered print/thumb generation → optional **`imagemaker`** module (own admin list + model API; not a shop extend until wired). See [`modules/imagemaker/docs/imagemaker.md`](../../imagemaker/docs/imagemaker.md).  

- Frontend template/SCSS/JS for product page → same extend (full template replace).  
- Product links / lists / list-item targets: **`shop/product` only**.  
- Category URLs: **`shop/category`**, **`shop/subcategory`**.

---

## Shopify-specific notes

- Sync creates/updates **`shop/product`** rows.  
- Extension panel: **`shopify/shop_product`**.  
- Cart driver when selected: Storefront cart + checkout.  
- No ownership of generic order admin for pure-Shopify checkout (optional mirror later).  
- Product list, taxonomy→category map, `original_artwork` meta: see [`modules/shopify/docs/sync.md`](../../shopify/docs/sync.md).

---

## Catalogue ownership

| End state | Location |
|-----------|----------|
| product, category, subcategory, producttext | **shop** |
| product_item, product_dim, product_type, dim_value_select | **shop** |
| treatments | **booking** |
| brands, lines, menu* | removed (site-specific if needed later) |

---

## Panel tables (SQL filters)

`get_list` filters on a param field in PHP after loading every row of that panel. Fields used in hot-path filters are panel-table columns (`"table": "1"`, indexed int), so the filter runs in SQL. See [`cms_schema.md`](../../cms/docs/cms_schema.md) § Panel tables.

| Table | Column | Used by |
|-------|--------|---------|
| `shop_product` | `subcategory_id` | catalogue filters |
| `shop_product_item` | `product_id` | `shop_dim_model::get_master_items` (product page, grid cards, cart add) |
| `shop_order_line` | `order_id` | `shop_model::get_order_lines` and other order line lists (cart on every page) |
| `shop_order_line` | `product_id` | `shop_dim_model::held_qty_map` (count-mode stock) |
| `shop_order_line` | `quantity` | `shop_model::get_order_quantity`: one `SUM` for the header badge, no line loads |
| `shop_order` | `cart_key` (varchar 64), `status` (varchar 32) | `shop_model::get_order_by_cart_key` (guest cart on every page) |
| `shop_order` | `user_id`, `status` | account cart in `get_current_order` / `get_current_order_if_any` |

After adding a table field: CMS → Schema → fix module `shop` (creates the column and copies params into it).

---

## Related docs

- [Product dims](dims.md)  
- [CMS module extends](../../cms/docs/cms_module_extends.md)  
- [CMS schema / panel tables](../../cms/docs/cms_schema.md)  
- [Shop todo](todo.md)  
- [Subscription / Stripe architecture notes](../../subscription/docs/stripe_vs_subscription_issues.md)  
