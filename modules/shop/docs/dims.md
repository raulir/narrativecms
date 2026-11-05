# Product dims

**Dim** = a product option axis (size, frame, colour) and its values. One system for CMS-only and Shopify-synced products. Shopify **writes** these lists on sync; shop / Timmy / imagemaker **read** shop dims only.

Do **not** confuse with unrelated `dimension` fields: basketmini quantity/unit, `order_line.dimension` (minutes/kgs), shop settings `dimension_prefix` / `dimension_suffix` / `dimension_extra` / `dimension_rounding` (UOM). Imagemaker **product dimension rules** store `product_dimension` + `dimension_value` (live DB names); match is CMS dim **slug** + value id or label.

## Lists

| List | Panel |
|------|--------|
| Product dims (Size, Frame, … + values) | `shop/product_dim` |
| Product types (stock mode, delivery, dims used) | `shop/product_type` |
| Product items (SKU / stock rows) | `shop/product_item` |
| Dim value select (admin helper) | `shop/dim_value_select` |
| Storefront | `shop/product` on the list page `shop_product` |

`product_dim`, `product_type`, and `product_item` are **list-only**. Shopify extend of `shop/product_item` adds readonly **`shopify_variant_id`** (checkout connector, not a second dim system).

## How to set up (CMS-only)

1. **Shop → Product dims** — one row per axis. `id` is the slug (`size`, `frame`). Values have `id` + label.
2. **Shop → Product types** — attach those dims. **No limit** or **Amount available in stock**.
3. Set **Product type** on the product, or leave empty and set it on the subcategory or category (cascade).
4. **Count mode:** one **Product item** per product × dim combo. Order empty (master). **Number** is remaining stock.
5. Open the product page (`shop/product`). It uses `shop_dim_model->get_product_presentation`. `shop/productbuy` and `shop/productdims` are legacy.

## Shopify sync

On product refresh **and** on a TTL-hit PDP load (`_product_from_cms` from disk cache), `shopify/shopify_dim_model` upserts:

1. **Dims** — option names (not `Title`) → slug (`Frame` → `frame`). Find or create `shop/product_dim`. Merge new values (`Black` → `black`). Do not delete values other products use.
2. **Type** — if there is at least one dim, find or create `shop/product_type` with that dim set. `stock_control` **none** (Shopify is inventory of record). Set `product.product_type_id`. Title-only products get **items but no type**.
3. **Items** — one visible master per variant (sold combos only). `shopify_variant_id`, price, sku, dims. Variant gone: `show=0` on the master.
4. **Images** — `product.images[].ids` = **product_item_id**s (from Shopify `variant_ids`).

Raw options/variants stay in Shopify disk cache for sync. They are **not** copied onto storefront `$params`.

**Round-trip:** Shopify import writes CMS products + dims + items. The cart is those CMS items (`ref_id`, `dims`). Checkout maps each item back to a Storefront variant via **`shopify_variant_id`** on the item (shopify extend) — not option names. Unsold picker combinations are rejected (no stale variant). Same idea as `original_artwork`: a documented field name, not Shopify option parsing.

**Checkout connector:** `create_order_line` copies `shopify_variant_id` from the resolved item. Materialise prefers the item connector, then the line field.

## Architecture

```
shop/product_dim          values[] { id, label, description }
shop/product_type         dims[] → product_dim; stock_control; …
shop/product              product_type_id
shop/product_item         product_id + dims[] + listed number
                          + shopify_variant_id (shopify extend)
shop/order_line           product_item_id → master; qty; fulfilment status
shop_dim_model            cascade, presentation, cart source, held stock
```

**Cascade (CMS-only):** product → subcategory → category. Empty at all levels: no limit, no dims.

**Presentation:** variants on `shop/product` are master items only. **No limit:** saving the product, or saving a no-limit type's dims, creates a master item for every combination that is missing. **Amount in stock:** combinations without an item are not shown and have no add button. **Picker:** a variant is available when it is offered (`availability` Yes) and sellable (stock check). Option values with no available variant get `product_dim_value_unavailable` (opacity 0.5) and cannot be clicked. Dims cascade in page order: dim k only counts variants that match the values chosen in the dims before it. When a click leaves a later dim's value unavailable, that dim moves to the next available value (wrapping), or to none. No full selection: Buy gets `product_buy_disabled`. Same rule in `shop_dim_model::picker_state` (first render) and `product_picker_refresh` in `product.js` (clicks); keep them the same. Variants include `product_item_id` and, when set, `shopify_variant_id`. No paid clones.

**Storefront masters** (`storefront_masters`): visible masters with a value for every type dim, first row per combination. A grid row still at `--`, or a second row with the same options, is left out of the product page, the grid card and the cart, and logged once per request. The Variants grid does not refuse a repeated combination per cell, because Save applies cells one at a time and an in-between state may match another row.

**Ids only:** pickers, variants and the cart use `product_dim_id` → value `id`. Picker markup: `data-dim_id` / `data-value_id`. The label is display text. Variant JSON: `dims: { "15": "canvas_l" }`. Cart `dims` is posted the same way. `normalize_dims_map` drops anything that is not a dim panel id with an exact value id. A posted combination with no storefront master returns `missing_variant_error`.

**Default variant** (`default_master_item`): first sellable, else first offered, else first. `shop/product` opens on it. `shop/product_thumb` prices it and shows **sold out** when it is not available (offered and sellable, the same rule as the picker), so a card is sold out exactly when no variant can be bought.

**Cart:** add by sold `product_item`. Line **points at** that item (`product_item_id`; `ref_id` also for delivery). Dims, sku, and default price are read from the item only — not copied onto the line. `create_order_line` copies `shopify_variant_id` from the item when present; otherwise `local_item`. A cart may hold local and Shopify lines together (checkout split is a todo). Adding the same item again (same customisation attributes) adds to that line's `quantity`, unless the product has **Allow multiple lines** = Yes (`allow_multiline`), then each add is a new line. Stock checks sum all lines of the item either way.

**Imagemaker rules:** match dim slug + value (id or label) on master items. Gallery `ids` are product item ids.

## Stock control

| Mode | Behaviour |
|------|-----------|
| **No limit** | No item accounting. Shopify-synced products use this. Pickers from masters if they exist, else cartesian. |
| **Amount in stock** | `number` is **listed** stock. Available = listed − qty on **unfinished** order lines that point at the item (draft + live paid). Can go negative. Held qty comes from `held_qty_map`: one SQL-filtered query on `shop/order_line` by `product_id` (panel-table column), counted per `product_item_id` (lines without `product_item_id` do not hold stock). Pass the map when checking several masters. When the order is **Finished**, listed `number` is reduced and those lines drop out of inventory. No clones. |

**Allow sale when not in stock:** master `number` may go negative. Off: reject add when available is below qty. Available already counts the visitor's own draft cart, so repeated adds cannot pass listed stock. A draft cart holds only for **Cart hold (minutes)** after its last activity; see [`shop.md`](shop.md) § Cart hold.

## Legacy / unused

| Piece | Status |
|-------|--------|
| `product_type.panel` (Custom buy panel) | Legacy. Not read. Possible future custom buy panel. |
| `shop/dim_value_select` | Dropdown on the product page variant grid. The product item form value is still a text field. |
