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
| **timmy** | Site frontend, customisation fields (extends product), Timmy-only chrome. Product/cart settings under **Shop → Timmy**. |
| **stripe** | Collect payment for a payable order/session |
| **booking** | Treatments / treatment categories (moved from legacy stock) |

Site modules **may** still add behaviour that checks whether `shopify` is installed. **Basic shop flows always go through `shop`** (never call Storefront from a site panel as the long-term pattern).

---

## Catalogue

### Unified product list

| Piece | Name |
|-------|------|
| Admin list | `shop/product` only |
| Base definition | `modules/shop/definitions/product.json` |
| Shopify fields | `shopify` extends → `target: shop/product`, `source: //shop_product` → `shopify/shop_product` |
| Site fields (Timmy) | `timmy` extends → `target: shop/product`, `source: //shopify_product` (or site `shop_product`) |

Stored rows use **`panel_name = shop/product`**. Extends only merge definition/JS/CSS into the target; they do not create a second list.

### Categories

| List | Panel |
|------|--------|
| Product categories | `shop/category` |
| Product subcategories | `shop/subcategory` |
| Product texts | `shop/producttext` |

### Local stock / variants (in shop)

| List | Panel |
|------|--------|
| Product items (SKU rows) | `shop/product_item` |
| Product dimensions | `shop/product_dimension` |
| Stock groups | `shop/stock_group` (was `stock/product_stock`) |
| Dimension value select (admin helper) | `shop/dim_value_select` |

Products may still store a `product_stock_id` FK pointing at a stock group row by `cms_page_panel_id`.



---

## Cart (local first)

### Durable identity

- **Cookie** `shop_cart` (60 days): opaque `cart_key` on the draft `shop/order`  
- **DB**: `shop/order` + `shop/order_line` hold lines, qty, attributes, price snapshots  
- PHP session may cache `order_id` for the request; cookie is source of recovery after session dies  

### Panel

| Piece | Role |
|-------|------|
| **`shop/cart`** | Basic cart: badge, popup, add/remove, checkout button |
| **Site extends** (e.g. Timmy `//shop_cart`) | Design SCSS + label settings — place **`shop/cart`** on pages, not a site-owned cart panel |
| **`shop/basket` / `basketmini`** | Older full-page basket UI (local shop checkout flow) |

Buy buttons call **`cart_add_items()`** from `shop/js/cart.js` (local only — no Storefront latency on add).

### Line attributes

Freeform key/value on lines for site customisation (Timmy fields) without shop knowing art details.

### Checkout via module `provides` + shop setting

Connectors advertise checkout without hard-coding module names:

```json
// shopify/config.json
"provides": [
  { "service": "shop_checkout", "panel": "//checkout", "label": "Shopify checkout" }
]
```

Boot aggregates into `$GLOBALS['config']['provides']['shop_checkout'][panel]`.

**Shop settings** (`shop/shop` → field `shop_checkout`): dropdown built by custom input `shop/cms_input_provides`. Stores the **panel name** (e.g. `shopify/checkout`). Default empty.

| Setting | Cart checkout button |
|---------|----------------------|
| **empty** | Red error: “Select shop checkout provider!” |
| **panel set** | `do=shop_checkout` → provider materialise → redirect |

### One-way line sync + status-only reverse

| Direction | Data | When |
|-----------|------|------|
| **Site → provider** | Lines (variants, qty, attributes) | Checkout click only |
| **Provider → site** | **Never** lines | — |
| **Provider → site** | **Status** (open vs dead/completed) | Throttled `do=reconcile` on cart page / open |

Local cart is always truth for products. User edits on Shopify checkout (remove line, etc.) do **not** change the site cart; cart id stays until checkout completes.

**Reuse remote cart** (e.g. `shopify_cart_id` on order via Shopify extends `shop/order`): same cart keeps address/vouchers. If local lines fingerprint changed, **replace** remote lines from site. If remote cart is **dead** (paid/completed), **close site order** (`status=paid`), clear cookie, empty cart — user starts a new draft.

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
| `shop/product` | Product page (base; site may extend) |
| `shop/productthumb` / `shop/products` | Grid (to be consolidated over time) |
| `shop/category` (+ subcategory) | Category landings |
| `shop/cart` | Cart badge + popup (sites extend design) |
| `shop/basket`, `shop/basketmini` | Full basket (legacy local) |
| `shop/checkout` | Local checkout |
| `shop/productbuy`, `shop/productdimensions` | Add-to-cart / variants (local) |

Timmy may keep themed copies (`timmy/product`, `timmy/productthumb`, …) that **use** shop data and cart, or gradually extend shop panels.

---

## Timmy-specific notes

- Customisation, overlay images, imagemaker → **timmy** product extends.  
- Frontend templates/SCSS → **timmy**.  
- Optional: `if (shopify module loaded)` for Shopify-only UX.  
- Product links / lists should resolve **`shop/product`**.  
- Category URLs: **`shop/category`**, **`shop/subcategory`**.

---

## Shopify-specific notes

- Sync creates/updates **`shop/product`** rows.  
- Extension panel: **`shopify/shop_product`**.  
- Cart driver when selected: Storefront cart + checkout.  
- No ownership of generic order admin for pure-Shopify checkout (optional mirror later).

---

## Catalogue ownership

| End state | Location |
|-----------|----------|
| product, category, subcategory, producttext | **shop** |
| product_item, product_dimension, stock_group, dim_value_select | **shop** |
| treatments | **booking** |
| brands, lines, menu* | removed (site-specific if needed later) |

---

## Phase roadmap (high level)

1. Unified **shop/product** (+ category/subcategory + stock leftovers) — done  
2. Cart provider API + local driver  
3. Shopify cart driver behind provider  
4. Timmy UI on shop cart API  
5. Local checkout + stripe hook  
6. Basic shop grid/PDP panels complete for greenfield 

---

## Related docs

- [CMS module extends](../../cms/docs/cms_module_extends.md)  
- [CMS schema / panel tables](../../cms/docs/cms_schema.md)  
