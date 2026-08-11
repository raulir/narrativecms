# Shop module backlog

## Cross-module: subscriptions

Shop settings host the **Subscription checkout provider** field (`subscription_checkout`, added by the subscription module extend of `shop/shop`).

Remaining Stripe / subscription architecture notes (validation gap, webhook hardening, legacy payment panel, etc.) are tracked in:

**[`modules/subscription/docs/stripe_vs_subscription_issues.md`](../../subscription/docs/stripe_vs_subscription_issues.md)**

Do not re-implement subscription checkout inside shop cart; keep `shop_checkout` (cart) and `subscription_checkout` (pricing Purchase) as separate provides services.

## Shop-local

- [x] **`shop/currency_selector`** — embeddable currency dropdown (`currency_ids` optional → all; `default`; `add_empty`); writes `.currency_selector_container[data-value]` + `#currency_selector_value`
- [ ] **Dimensions / variants on product** — presentation of options/variants (dimension pickers, default variant, gallery `ids` linking) is still largely in **Timmy** `shop_product`. Move base logic into **shop** and/or **stock** (there is older stock/product-dimension machinery). Shopify should only supply raw options/variants; site modules stay for chrome (e.g. colour swatches).
