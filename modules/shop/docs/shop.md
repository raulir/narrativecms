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

## Orders admin

Shop → **Orders** (first item under Shop; `admin/orders/`, one order `admin/orders/{id}/`; method `orders` of the CMS admin controller, which says so when shop is missing; the menu item comes from shop). The technical lists are Shop → Shop settings → **Order details** and **Order line details**. Built to [`cms/docs/admin_pages.md`](../../cms/docs/admin_pages.md). `shop/orders_admin` is the page (CMS toolbar: **Orders** = unfulfilled / in progress / fulfilled, **Archive** = finished / cancelled / abandoned, **Carts** = drafts; search from 3 characters by order number or part of the buyer email, over all of them). `shop/orders_admin_rows` prints the rows: by `_panel()` on landing, by ajax for filters, search and the next 50 (`shop_model::get_admin_orders`). The filter is in the URL (`admin/orders/?view=orders|archive|carts`): it opens that filter, and a filter click updates it without a page load. `shop/order_admin` is one order (`order_id`): a CMS toolbar with a back arrow to the order's filter and `#152 (paid) (unfulfilled)`; two columns, left **Items**, **Summary**, **History**, right **Customer**, **Delivery**, **Status**. The Status card sets both statuses by hand with one Save: **Payment** (`shop_model::set_order_payment_status`; marking paid sets `paid_time` when empty) and **Order** (`set_order_status`; payment is saved first). A finished order shows its order status as text; a draft or abandoned cart can be moved to an order status but not back. On a draft or abandoned cart the payment status is text only, and `set_order_payment_status` refuses it (`payment_editable`): a cart marked paid by hand would make `set_order_paid` skip the real payment and its emails. Choosing **Fulfilled** on an order that is not Fulfilled yet shows **Email the buyer** (a CMS tick `[v]` / `[ ]`; ticked, or unticked with "Sent …" when the dispatch email went out before) and **Tracking number**. Save stores payment, tracking, then status, then sends the dispatch email (§ Paid orders). The Delivery card shows the tracking number. Status save and comments post by ajax and the panel comes back fresh in place. All three require a logged-in CMS user with access `shop_orders`, including over ajax. Status changes go through `shop_model::set_order_status` (the same rules as the technical order form: the status goes onto the lines; Finished posts listed stock once and cannot be undone).

### Order history (`shop_order_log`)

Table `shop_order_log` (`modules/shop/schema/`): `order_id`, `time` (timestamp), `type` (`event` / `comment`), `cms_user_id` and `user_name` (the CMS user, empty when the system did it; the name is stored because config admins have no `cms_user` row), `line`. Written only through `shop_model::log_order()`; a failed write goes to the error log and never stops the caller (a payment must not fail on its history). Removed with the order: `shop/order` `on_delete` calls `shop_model::delete_order_parts` (lines and history), so this holds for `delete_order`, cart cleanup, a merged guest cart and a delete from the technical list alike (CMS `on_delete`, [`system.md`](../../cms/docs/system.md)).

| Logged | Where |
|--------|-------|
| Cart created | `get_current_order` (new draft) |
| Status change (`Status: Unfulfilled → Fulfilled`) | `log_order_state()` before each order update that changes it: `set_order_status`, `rollup_order_status`, `finish_order`, `close_cart_order`, `abandon_cart_order`, `set_order_paid` |
| Payment change (`Payment: Unpaid → Paid, £315.00 (stripe pi_…)`), payment note | `log_order_state()`: `set_order_paid`, `abandon_cart_order`, `set_order_payment_status` (order page), the technical order form (`shop/order` `on_update`) |
| Site user account created / order linked to a site user | `attach_order_user` |
| Emails: New order, Order confirmation, Fulfilment request, Dispatch (sent, could not be queued, or no buyer email) | `send_order_emails`, `shop/fulfilment_email`, `send_dispatch_email` |
| Tracking number set / removed | `set_order_tracking` (order page) |
| Stock taken off the catalogue | `post_listed_stock` |
| Refund (amount, total refunded of paid) and the payment change; refund email to the shop | `set_order_refund` (Stripe `charge.refunded`) |
| Returned to stock | `return_line_to_stock` (order page) |
| Admin comment (plain text, shown bold) | `add_order_comment`, from the order page |

The order page shows the history newest first: time (`17 Nov 2026 16:47`), the line, and the CMS user's name after it. A new state change or email must call `log_order_state()` / `log_order()` where it happens. Comments replace the old admin note field (removed 2026-11-17; there were no notes to move).

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

One-off backfill of empty headings: `php ai/_migrate_shop_order_heading.php`  

### Panel

| Piece | Role |
|-------|------|
| **`shop/cart`** | Basic cart: badge, popup, add, line quantity − / +, remove, checkout button |
| **Site extends** (e.g. Timmy `//shop_cart`) | Design SCSS + label settings — place **`shop/cart`** on pages, not a site-owned cart panel |

Buy buttons call **`cart_add_items()`** from `shop/js/cart.js` (local only — no Storefront latency on add).

### Cart contracts

- **Settings:** all labels and visitor messages are `shop/cart` settings (stored row; no serve-time defaults in `cart.php`), including the JS messages `add_error`, `checkout_error`, `checkout_missing_error`, passed as `data-*` on `.cart_container` and read with `.data()`.
- **Markup** lives in `cart.tpl.php`; `cart.php` passes data (`dims`, `attributes` list, `description`, `notice`, `warning`).
- **Requests:** `cart_add_items(items, attributes)` posts `product_id`, `product_item_id`, `quantity`, `dims`, `attributes`. No price is posted; the server prices every line. Remove posts `line_id`. Only the keys in `cart.json` `ajax.input` reach the panel, so a post cannot overwrite a cart setting.
- **Action replies:** actions that need no HTML (`add`, `set_visible`, the provider's `shop_checkout` and `reconcile`) are called with `get_ajax`. The panel returns an array; the browser gets only the keys in `ajax.output`, under `result` (`ok`, `quantity` = cart total, …), and an error text as `cms_error`. `cart.js` reads them through `cart_reply()`. `open` / `remove` / `checkout_check` use `get_ajax_panel`, because they need the popup HTML.
- **Notices (no `window.alert`):** `cart_add_items` resolves `{ ok: 1, quantity }` or `{ ok: 0, error }` (error from the server, or the cart setting `add_error`); the caller shows it — `product.js` toggles `.product_added_visible` / `.product_add_error_visible`. Checkout messages go to the cart popup's hidden `.cart_popup_message` via `cart_show_message(text)`.
- **Line deletes** go through `shop_model::delete_order_line($order_id, $line_id)`, which only deletes a line of the visitor's current order.
- **Naming:** a row of an order or cart is a **line** (`line_id`, `shop/order_line`), never an item. "Item" is the product item (`product_item_id`).
- **Request values** are read from panel `$params` only (ajax params already contain the post), never `$this->input->post()`.
- **Line fields:** `quantity` only (definition field and table column; `qty` is gone). `product_item_id` is the sold item (`order_line_item_id`); `ref_id` is read only for delivery lines (`order_line_ref_id`).

### Header vs popup

On a normal page the header cart reads only the quantity (`get_order_quantity`, one SQL `SUM`) and renders the badge shell. Lines, prices, dims and the popup are built only when details are wanted: `do=open` (cart opened), `do=remove`, `do=set_quantity`, or the popup left open in the session (`cart_visible`). Local and Shopify lines may be in the same cart; there is no mixed-cart check.

### Line prices

A line keeps the price it got when added, until one of these re-prices it from the current variant price (`get_item_price_in_currency`, in the line's own currency):
- **Add** of an item that merges into an existing line (`_merge_into_order_line`): the line gets the new qty **and** the current price.
- **Cart opened** (`do=open`) and **Checkout clicked** (`do=checkout_check`): `shop_model::refresh_line_prices` updates every item line whose price changed. The line shows the cart setting **Price changed** ("Price changed from {{old}} to {{new}}."); the total follows.

`cart.js` `cart_checkout()` (the Checkout button) calls `do=checkout_check` first. The reply sets `.cart_container[data-checkout_ready]` to `1` only when no price changed and no line lost its stock; then the provider panel (`do=shop_checkout`) is called. Otherwise the updated popup is shown and checkout stops, so the visitor sees the new prices before paying; the next click goes through.

### Cart hold (reserved stock)

A draft cart reserves stock-counted items for **Shop settings → Cart hold (minutes)** (default 60) after the visitor's last cart activity (`shop/order.cart_active_time`). Older drafts reserve nothing (`shop_model::order_holds_stock`).

Activity is cart `do=add`, `do=open` (sent by `cart_load_details()` when the cart is opened), and `do=remove`. Each runs `shop_dim_model::cart_refresh_hold`: the cart's local item lines are checked against stock held by **other** orders. If all fit, the hold is refreshed. If a line no longer fits (stock taken while the cart was stale, item hidden or not offered), the hold is **not** refreshed, so a stale cart never takes stock back from a cart that now holds it, and that line shows the cart setting **Item not available** ("Item {{item}} is not available anymore.", `{{item}}` = product item title, e.g. `Poster (A3, Black)`). Removing the line refreshes the hold for the rest. Shopify lines are skipped (Shopify is the stock of record).

**Payment page open:** a checkout provider calls `shop_model::hold_cart_until($order_id, $time)` when it opens a payment page (Stripe: until the session expires). The draft holds its stock at least until then (`order.cart_hold_until`), whatever the Cart hold setting is. If a payment still arrives after the stock went to another order, `set_order_paid` adds "Not enough stock at payment (oversold): …" to the order's payment note.

Cart add uses the same rule before the line is saved (`shop_dim_model::cart_add_fits`): listed stock minus other orders' holds must cover this cart's qty of the item **plus** the new qty. So a stale cart that already has the last unit cannot add another.

### VAT line (product page, cart total)

Shop settings → **VAT**: `vat_rate` (empty = N/A, else the rate in %, prices include it), `vat_product_text` (`{{rate}}`), `vat_cart_text` (`{{amount}}`, `{{rate}}`), `vat_na_text` (shown when N/A; empty hides the line). `shop_model::get_vat()` reads the rate (a value that is not a rate 0–99 counts as N/A and is logged once); `vat_text('product')` / `vat_text('cart', $total, $currency)` give the line, the cart's VAT part being total × rate / (100 + rate), £0.00. Printed as `product_vat` under the price (in `product_price_block`, `shop/product.php` → `vat_note`) and `cart_popup_vat` under the cart total (`cart.vat`, none for a mixed-currency cart). Not in the emails, the Orders admin or Stripe. The older **Show tax** setting is the on-site checkout's only.

### Line quantity (− / +)

Each popup line has `cart_popup_item_quantity` (`data-line_id`, `data-quantity`) with **−** / **+** (cart settings `quantity_minus_label`, `quantity_plus_label`). `cart.js` `cart_set_quantity()` posts `do=set_quantity` (`line_id`, `quantity`) and replaces the popup with the reply; one request at a time (`cart_loading`). **−** at 1 removes the line (`do=remove`).

`shop_model::set_order_line_quantity($order_id, $line_id, $quantity)`: only an item line of the visitor's own draft cart, quantity 1–99. More of a local item must pass `cart_add_fits` (the add rule, with the difference as the new qty); if not, the line keeps its quantity and shows the cart setting **Not in stock** (`stock_error`). Less is never checked. The line keeps its price; the hold is refreshed (`cart_refresh_hold`). Cart quantity changes are not written to the order history.

### Abandoned carts

Unpaid draft carts with no visitor activity for **Shop settings → Delete carts after (days)** (default 30, 0 = never) are deleted with their lines by the repeating task **Shop cart cleanup** (`shop/cart_cleanup`, add it daily under CMS repeating tasks; `shop_model::delete_stale_carts`). Orders that left draft are never deleted.

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
| `partially_refunded` | — (local orders: a part refund reported by the payment module, `set_order_refund`) |
| `refunded` | `REFUNDED` |
| `voided` | `VOIDED` |

**Stock (count mode):** listed `product_item.number` minus qty on unfinished holding lines (can be negative in reports). Abandoned / cancelled / refunded / **finished** do not hold (partially refunded still holds). On finish, fulfilled/in_progress qty is subtracted from listed `number` once (`stock_posted_time`).

**Refunds** (`shop_model::set_order_refund($order_id, $refunded_total)`, the contract for any payment module): `$refunded_total` is the **cumulative** refunded amount in major units. The same total as the stored `refunded_amount` changes nothing (repeated or late events). Otherwise: `refunded_amount` stored, payment `partially_refunded` (below `paid_amount`) or `refunded`, History lines ("Refund in stripe: £15.00 (refunded £15.00 of £330.00)" and the payment change), and the **refund notice** to Shop settings → Notification emails (`send_refund_email`; Order emails → Shop refund notice; template `$mail = 'refund'`). The order status is not changed: the admin cancels or finishes the order. Stripe calls it from `charge.refunded` (orders found by `payment_ref`, `get_order_by_payment_ref`). The order page shows **Refunded −£…** in Summary, and choosing **Cancelled** on a paid order shows a note that cancelling does not refund (the refund is made in Stripe).

**Return to stock** (finished orders): goods sent back after Finished. On the order page, each item line whose quantity was posted (count-mode item, line fulfilled / in progress, `shop_model::line_returnable`) has **Return to stock**: `return_line_to_stock` adds the whole line quantity back to the variant's `number` once, stamps the line `stock_returned_time` and logs "Returned to stock: …". Then the line shows "Returned to stock {date}".

Child / sub-orders (when payment or delivery rules differ) and `shop/payment` rows are later.

### Fulfilment (`provides.shop_fulfilment`)

Shop **provides** `shop/fulfilment_email`. Each `shop/category` picks a fulfilment provider and a repeater of recipient emails.

CMS emails run only when **payment is paid** and **status is unfulfilled** (Shopify webhook/cron, or `set_order_paid` for Stripe and other payment modules). If Shopify is **in progress** or **fulfilled**, CMS does not send and cron does not keep checking those lines. Group unsent lines by provider → `run_action`. Email provider sends **one queued mail per recipient**, containing only that recipient’s lines. `fulfilment_sent_time` is stored on each line and the line `status` becomes `in_progress`. Empty category fulfilment = CMS does not fulfil that line (no log, no stamp); set Email fulfilment on categories that should mail. Order status rolls up from lines; paid + all item lines fulfilled/cancelled → **Finished**. Lines no provider fulfils count too (the shop sends them by hand), so a paid order without providers stays **Unfulfilled** until the admin sets its status. Admin status Fulfilled / In progress / Cancelled is applied to the lines; Finished on a paid order marks its open lines fulfilled first, so listed stock is posted.

Print file: if `shop/product.print_file` exists, the email includes the CMS download URL (`/cms/file/…`, same as admin file download). Missing file: omit, still send.

Need **CMS email queue** on repeating tasks. Subject/intro: Shop settings → Email fulfilment.

**Reuse remote cart** (e.g. `shopify_cart_id` on order via Shopify extends `shop/order`): same cart keeps address/vouchers. If local lines fingerprint changed, **replace** remote lines from site. If remote cart is **dead** (Shopify order exists, or checkout abandoned), **close the site cart** (`status` no longer empty), clear cookie, empty cart — user starts a new draft.

Shopify is **not** cart-of-record while browsing — no Storefront on every add.

---

## Checkout and payments

```
Local cart (cookie → order draft)
  → Checkout button: cart do=checkout_check, then the shop_checkout provider (Shop settings → Checkout provider)
      shopify/checkout → Shopify checkout
      stripe/checkout  → Stripe Checkout page (card, email, phone, delivery address, delivery method)
  → provider marks the order paid: shop_model::set_order_paid()
```

Payment modules use the **payable order** contract below, not order lines or product data. Stripe cart checkout: [`stripe/docs/shop_checkout.md`](../../stripe/docs/shop_checkout.md). The on-site checkout page (`shop/checkout`) is a separate service: see § Services.

### Payable order (contract for `shop_checkout` providers)

A checkout provider never walks order lines itself. It uses two `shop_model` calls:

**`checkout_check($order_id)`** → `['price_changed_lines', 'lost_item_ids', 'ready']`. Re-prices the lines and re-checks stock (and refreshes the cart hold). The cart runs it on open and on the Checkout click; the provider runs it again before taking payment and stops when `ready` is false (the visitor sees the updated cart on the next click).

**`get_payable_order($order_id)`** → what there is to pay, amounts in major units (e.g. pounds):

| Key | Content |
|-----|---------|
| `currency` | ISO code, lower case (`gbp`); empty when the lines have more than one currency (`mixed_currency`) |
| `currency_row` | the `shop/currency` row, for `format_amount` |
| `lines` | every line: `line_id`, `type` (`item` / `delivery`), `item_id`, `name`, `dims` (text), `unit_price`, `quantity`, `total`, `currency_row` |
| `subtotal` / `delivery_total` / `total` | item lines / delivery lines / both |
| `delivery_options` | delivery methods the provider may offer: `delivery_id`, `name`, `price` (shown `shop/delivery` rows, list order) |
| `fingerprint` | hash of the lines; a provider stores it at checkout start and compares at payment |

The cart total, the provider's line items, the paid-amount check and the order email summary all read this one array, so a pricing rule (discount, VAT) is added in one place.

**Provider panel:** `cms_do=shop_checkout` → reply `{ ok, redirect }`. On payment the provider calls `set_order_paid` (below).
### Paid orders (`set_order_paid`)

`shop_model::set_order_paid($order_id, $payment)` is the one place an order becomes paid, for any payment module. It returns false (and does nothing) when the order is already paid, so a provider may call it from both its webhook and its return page.

| `$payment` key | Stored as |
|----------------|-----------|
| `provider`, `ref`, `amount`, `note` | order `payment_provider`, `payment_ref` (e.g. Stripe `pi_…`), `paid_amount` (delivery included), `payment_note` |
| `buyer` [`name`, `email`, `phone`] | `buyer_name`, `buyer_email` (valid address only), `shipping_phone` |
| `shipping` [`name`, `address1`, `address2`, `city`, `postcode`, `county`, `country`] | `shipping_*` |
| `delivery` [`delivery_id`, `price`] | one delivery line (`ref_id` = `shop/delivery` row, `line_type` delivery, no product item, price as charged) |

All text is cleaned with `clean_visitor_text` (the buyer typed it on the provider's page). Then: status **Unfulfilled** + payment **Paid**, lines stamped unfulfilled, `request_fulfilment`, status rollup, and the order emails.

`payment_note` is set by the provider when the payment does not match the cart (amount, currency, lines changed after checkout started). The order is still paid (the money is taken); the notification email starts with the note.

### Paid order → site user (`attach_order_user`)

Shop extends `user/user` (`//user_user`, `definitions/user_user.json`) with the delivery fields of the order, same names: `shipping_name`, `shipping_address1`, `shipping_address2`, `shipping_city`, `shipping_postcode`, `shipping_county`, `shipping_country`, `shipping_phone`. They show on the admin user form under **Shop**.

`set_order_paid` calls `attach_order_user($order_id)` right after the buyer and address are stored. Nothing happens when the `user` module is not enabled. The buyer email is the user's identity:

| Order | User |
|-------|------|
| already has `user_id` (cart bought while logged in) | that user, whatever email was typed at payment |
| no user, a shown user has the buyer email (also tried in lower case) | that user |
| no user, no such email | new `user/user`: email in lower case, first / last name from the buyer name, phone, **no password**, `email_verified` no, default access for new users |
| no valid buyer email, or a hidden user holds the email | no user; logged with `error_log_user` |

Then the order's `shipping_*` values are written onto the user (each paid order overwrites them), the user's `phone` is filled when empty, and the order gets `user_id`. The buyer is not logged in by this. A created user has no password; the buyer sets one through the password reminder, which mails that address.

The payment provider does not verify the email. A guest who types somebody else's email gets the order attached to that account and replaces its saved delivery fields. Password, access and other user fields are not touched.

Test: `ai/tmp/shop_order_user_test.php`.

**Order emails** (`send_order_emails`, queued through `cms_email_model`, plain text):
- notification to Shop settings → **Notification emails**, with the payment note (if any), the payment reference and the admin link;
- confirmation to the buyer, starting with the intro.

**Dispatch email** (`send_dispatch_email`, same queue and template, `$mail = 'dispatch'`): to the buyer when the admin sets the order **Fulfilled** on the order page with **Email the buyer** ticked. It has the dispatch intro, `Tracking number: …` when the order has one (`tracking_number`), then the order summary. It stamps `dispatch_email_time`. Not sent on any other status, nor from the technical form.

**Terms line:** the buyer emails (confirmation, dispatch) end with Order emails → **Terms line** ("Our terms and conditions of sale are available at {{site_url}}", `{{site_url}}` = the site's front page, absolute, from `order_email_data`). Empty: no line. The New order email has none.

All texts (subjects, intros, every label) are settings of **`shop/order_email`** (Shop settings → Order emails). The body is `templates/order_email.tpl.php`; the model only passes data (`order_email_data`: lines from the payable order, total, buyer, address). A site module can replace the template through an extend (`//shop_order_email`). It is a text template, so it has no container / content elements.

They need **CMS email queue** on CMS repeating tasks, and SMTP (or PHP `mail()`) in CMS settings.

---

## Services (one panel per service)

`shop` plus one payment module is a complete small shop. A site module extends the panels for design and customisation; it does not replace them. **Each logical service has one panel.** A second panel for a service that already has one is a duplicate: extend the existing panel instead.

| Service | Panel(s) | Site extends via | State |
|---------|----------|------------------|-------|
| Product list (grid + filters) | `shop/products` shell → `shop/products_menu`, `shop/products_grid` (ajax) | `//shop_products*` | works |
| Product card | `shop/product_thumb` | `//shop_product_thumb` | works |
| Product page (images, text, price, variant picker, Add to cart) | `shop/product` | `//shop_product` | works |
| Variants / stock (dims, types, items, reserved) | lists `product_dim`, `product_type`, `product_item`; `shop_dim_model`; admin inputs `dim_value_select`, `item_yes_no`, `image_item_ids` — [`dims.md`](dims.md) | — | works |
| Catalogue structure | lists `category`, `subcategory`, `collection`, `collection_type`, `producttext` | `//shop_category`, `//shop_subcategory` | lists; landing pages come from the site |
| Currency | list `currency`; `shop/currency_selector` | — | works |
| Cart (header count, lines, quantity − / +, remove, Checkout button) | `shop/cart` | `//shop_cart` | works |
| Checkout handoff (cart → payment) | service `shop_checkout`, chosen in Shop settings; provider panel, e.g. `stripe/checkout` | provider module | works |
| On-site checkout page (delivery method, address, review — for payment providers that do not collect them) | `shop/checkout`, `delivery_model`, `vendor/countries.json` | `//shop_checkout` | **needs rework** (todo "On-site checkout rework"): no payment step, settings not stored |
| Delivery | lists `delivery`, `delivery_set`; `product_type.delivery_set_id` | — | works (sets are read by the on-site checkout only) |
| Payment | payment module (`stripe/checkout`, webhook, return URL) | — | works |
| Paid order + order emails | `shop_model::set_order_paid`, `send_order_emails`, `send_dispatch_email`; texts and template `shop/order_email` | `//shop_order_email` | works |
| Orders admin | `shop/orders_admin`, `shop/orders_admin_rows`, `shop/order_admin` (`admin/orders/`); history table `shop_order_log`; technical lists `order`, `order_line`; `shop/order` `on_update` | — | works |
| Fulfilment | service `shop_fulfilment` → `shop/fulfilment_email` | provider module | works |
| Shop settings | `shop/shop`; provider pickers use `cms/cms_input_provides` | — | works |
| Search / user extends | extend sources `search_searchajax`, `user_user` | — | for projects with those modules |

Removed as duplicates (2026-11-16): `productbuy`, `productdims` (→ `shop/product`), `basket`, `basketmini`, `order_operations` (→ `shop/cart`), `shop/cms_input_provides` (→ the CMS input). A project that still places one of them moves to the service panel first; `ai/cleanup_removed_shop_panels.php` reports placed ones and removes their stored settings.
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
