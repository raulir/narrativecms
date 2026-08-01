# Stripe vs subscription — remaining architecture notes

Follow-up from the pricing → Stripe architecture review. **Items 1–4 are done** (see below). This file tracks what is still open so we can return later.

Also linked from [`shop/docs/todo.md`](../../shop/docs/todo.md).

---

## Done in refactor (1–4)

| # | Fix |
|---|-----|
| **1** | FE calls **`subscription/checkout_start`** only. Stripe provider has **no** `subscription_model` dependency. Intent clear is domain-side. |
| **2** | Entitlement meta is written by **`subscription_model::apply_entitlement_from_provider`**. Stripe webhook only builds facts and calls subscription when the module is installed. |
| **3** | Catalogue / card build lives in **`subscription_model::prepare_pricing_panel`**. Pricing panel is thin. |
| **4** | **user** no longer references subscription. Resume URLs via **extends**: `user_login`, `user_register`, `user_auth_google`. |

### Target dependency direction (after 1–4)

```
shop (settings key + catalogue)
  → subscription (pricing, intent, checkout_start, entitlement)
      → stripe (Checkout Session + webhook facts only)
user ← extended by subscription for post-auth resume only
```

---

## Still open

### 5. Catalogue vs checkout policy gap

`checkout_start` validates `billing_interval` is `month`/`year` but does **not** prove the product sits under a `type=subscription` category.

**Risk:** crafted `product_id` for a normal shop product that happens to have a Stripe price could start Checkout.

**Later:** resolve product → subcategory → category; require `type=subscription` (or a product flag).

### 6. Webhook security

If `webhook_secret` is empty, unsigned JSON is accepted (dev convenience).

**Later:** refuse unsigned events unless `environment === DEV` (or always require secret).

### 7. FE / transport surface

- Pricing still exposes `data-checkout_provider` for diagnostics; checkout always goes through `checkout_start`.
- Provider contract is documented in `subscription.md`; keep I/O stable if a second provider is added.

### 8. Legacy `stripe/payment`

Elements / licences / samba path is **not** ScoreTutor. Do not unify with `subscription_checkout` without a deliberate design.

### 9. User meta API from stripe

Prefer `get_user_meta_value_for_id` / `set_user_meta_for_id` only (partially done). Avoid calling `_parse_user_meta` from other modules.

### 10. Webhook HTTP status / retries

Handler often returns `ok: 1` even if entitlement apply fails after a valid signature, so Stripe may not retry.

**Later:** log failures; return non-2xx only when retry is useful; avoid non-2xx for permanent mapping errors.

### 11. Success UX

Success/cancel URLs still use query flags on resume or home; no dedicated “thanks, Premium active” panel yet.

### 12. Access / ads

`meta.subscription.active` is written (nested object); site access rules and ads still need to **read** it.

---

## Provider contract (`subscription_checkout`)

**Caller:** `subscription/checkout_start` (not browser → stripe).

| Input | Meaning |
|-------|---------|
| `do` | `subscription_checkout` |
| `product_id` | Shop product id |
| `currency_id` | Shop currency panel id |
| `success_url` / `cancel_url` | Optional; domain supplies resume-based URLs |
| `return_result` | `1` when nested under checkout_start (return array, no print) |

| Output | Meaning |
|--------|---------|
| `ok: 1`, `redirect` | Stripe Checkout URL |
| `ok: 0`, `error` | Message |
| `ok: 0`, `login: 1`, `login_url` | Auth required |

---

## Related

- [subscription.md](subscription.md)
- [stripe_checklist.md](../../stripe/docs/stripe_checklist.md)
- [shop.md](../../shop/docs/shop.md) (subscription_checkout setting)
