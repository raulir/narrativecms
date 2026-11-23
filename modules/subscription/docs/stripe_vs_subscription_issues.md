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
      → stripe, google through provides + run_action only (subscription_checkout, subscription_billing)
user ← extended by subscription for post-auth resume only
```

### Billing through `subscription_billing` (2026-11-20, site review item 10)

`subscription_model` no longer loads a stripe or google model. Everything it asks of a provider (sync, auto-renew, stop renewal, card update, plan change, the Play manage address, checkout links, absolute URLs, telling Play of a Stripe sale) goes through `subscription_model::billing_call($module, $do, $args)`. That is `run_action` on the panel providing `subscription_billing` for that module (`stripe/subscription_billing`, `google/subscription_billing`), with `_provider_call`, so no request can reach them. A disabled provider answers `ok: 0, unavailable: 1`; `stripe_ready()` and `google_module_ready()` ask the registry. `play_manage_url` now has that guard too. Music's account deletion calls `subscription_model::delete_user_data()` instead of deleting `subscription_subscription` rows itself. Probe: `ai/tmp/billing_provider_probe.php`.

### Done since (5–6)

- **5. Catalogue vs checkout policy:** `validate_paid_product()` requires a month or year interval and a product under a `type=subscription` category (product → subcategory → category).
- **6. Webhook security:** without a signing secret the webhook refuses every event; unsigned JSON is accepted only in DEV from this machine. Since 2026-11-20 each event id is handled once (`stripe_model::webhook_event_claim`, `tmp/stripe_webhook_events.json`, 7 days), subscription created / updated events use the subscription read from Stripe now (a late "created" can't undo Premium), and the Checkout idempotency key has a nonce per start. Checks: `ai/tmp/stripe_webhook_check.php`, `ai/tmp/stripe_webhook_signed_check.php`.

---

## Still open

### 7. FE / transport surface

- Pricing still exposes `data-checkout_provider` for diagnostics; checkout always goes through `checkout_start`.
- Provider contract is documented in `subscription.md`; keep I/O stable if a second provider is added.

### 8. Legacy `stripe/payment`

Elements / licences / samba path is **not** ScoreTutor. Do not unify with `subscription_checkout` without a deliberate design.

### 9. User meta API from stripe

Prefer `get_user_meta_value_for_id` / `set_user_meta_for_id` only (partially done). Avoid calling `_parse_user_meta` from other modules.

### 10. Webhook HTTP status / retries

**Done:** entitlement apply failures log (`error_log_user` + `cache/stripe.log`), notify, and **rethrow** so the handler returns **500** and Stripe retries. Permanent mapping misses (no user id) stay 200.

### 11. Success UX

Success/cancel URLs still use query flags on resume or home; no dedicated “thanks, Premium active” panel yet.

### 12. Access / ads

`meta.subscription.active` is written (nested object); site access rules and ads still need to **read** it.

---

## Provider contract (`subscription_checkout`)

**Caller:** `subscription/checkout_start` only (not browser → stripe). Enforced since 2026-11-20: the provider runs only with `_provider_call`, which `checkout_start` sets and no request can carry (leading-`_` keys are dropped from posts and query strings). Its definition lets no posted key through (`ajax.input: ["cms"]`). A direct post answers `ok: 0` with "Checkout starts from subscription/checkout_start" and one error-log line. Before, a signed-in visitor could post to it and skip "already subscribed" and `validate_paid_product`, with return addresses of their choosing.

| Input | Meaning |
|-------|---------|
| `do` | `subscription_checkout` |
| `_provider_call` | `1`, set by `checkout_start` |
| `product_id` | Shop product id |
| `currency_id` | Shop currency panel id |
| `success_url` / `cancel_url` | Optional; domain supplies resume-based URLs |
| `return_result` | `1` when nested under checkout_start (the result merged with the params); without it, the result alone. It never prints. |

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
