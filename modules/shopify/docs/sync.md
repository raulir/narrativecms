# Shopify product sync

## shop/product `panel_params` chain

On storefront product pages, extends run in module order. This project:

**shop → shopify → imagemaker → timmy**

| Extend | Role |
|--------|------|
| `shopify/shop_product` | `get_product_by_id` recheck; merge catalogue fields; set `options`, `variants`, `shopify_images` |
| `imagemaker/shop_product` | Optional composite gallery image |
| `timmy/shop_product` | Presentation only (no catalogue overwrite) |

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

### Category collections (ranges for mega menu)

`shop/category` has a **collections** repeater (`collection_id` → `shop/collection`).

On product import/update, after organisation assign:

- Resolve category via `product.subcategory_id` → subcategory.`category_id`
- For each id in `product.collections`, **union-add** to that category’s `collections` (no duplicates)
- **Does not remove** collections when a product leaves them (edit category in CMS, or call `rebuild_category_collections_from_products()`)

Mega menu (`timmy/categories`) lists **Shop by range** from `category.collections` only — it does **not** load all products.

One-off backfill after deploy: `php cache/_rebuild_category_collections.php` (or model method above).

## Original artwork (Timmy)

Metafield **`custom.original_artwork`** (`file_reference` → MediaImage/GenericFile) is scraped into product field **`original_artwork`** when the **timmy** module is installed. Hash `original_artwork_src_hash` avoids re-download when unchanged.

## Admin GraphQL

Category + metafield URL use Admin GraphQL (`shopify_product_model::graphql` / `get_product_admin_extras`). Same access token as REST.
