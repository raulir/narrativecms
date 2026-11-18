# Stripe models

Three models, one job each. Panels and APIs load the one they need; the two domain models load the core themselves.

| Model | Job | Needs modules |
|-------|-----|---------------|
| `stripe/stripe_model` | Core: Stripe SDK and API key, `absolute_url`, settings, checkout link URLs, error log, webhook verification and dispatch | — |
| `stripe/stripe_order_model` | Shop cart orders: Checkout session (mode payment), paid session → `shop_model::set_order_paid`, expired session. [`shop_checkout.md`](shop_checkout.md) | `shop` |
| `stripe/stripe_subscription_model` | Subscriptions: customer, Checkout session (mode subscription), webhook entitlement and notifications, manage actions (sync, auto-renew, change price, payment method). [`stripe_checklist.md`](stripe_checklist.md) | `user`, `subscription` |

## Core (`stripe_model`)

| Function | Use |
|----------|-----|
| `absolute_url($url)` | CMS path → absolute URL for Stripe |
| `get_settings()`, `get_webhook_secret()`, `get_checkout_link_urls()` | Stripe settings row |
| `log_error($short, $detail)` | `error_log_user` + `log/stripe.log` |
| `fail($short, $detail, $public)` | log, return `['ok' => 0, 'error' => $public]` |
| `to_array($obj)` | Stripe object or array → array |
| `module_enabled($module)`, `subscriptions_available()` | optional-module checks |
| `handle_webhook_request()` | verify signature, then dispatch |

## Webhook dispatch

`handle_webhook_request()` verifies the event (a valid Stripe signature; unsigned JSON only in DEV, with no secret set, from this machine) and hands it to one model's `handle_webhook_event($type, $object)`:

- Checkout session with `mode: payment` → `stripe_order_model` (when `shop` is enabled).
- Every other event → `stripe_subscription_model` (when `user` and `subscription` are enabled).
- An event one of them would handle, on a site without its modules: logged as skipped, answered 200. Other event types are ignored.
- A handler exception answers 500, so Stripe retries.

`stripe/subscription_checkout` answers "Subscriptions are not available" (and logs) when `user` or `subscription` is missing.

## Renames (2026-11-16) — callers outside this module

Callers of the old single model change the model name; the function names below also changed.

| Before (`stripe_model->…`) | Now |
|----------------------------|-----|
| every subscription function: `ensure_customer_for_user`, `create_subscription_checkout_session`, `retrieve_subscription_for_user`, `probe_user_stripe_subscription`, `sync_user_subscription`, `set_auto_renew_for_user`, `create_payment_method_update_session`, `change_subscription_price_for_user`, `get_webhook_notification_recipients`, `stop_renewal_by_id` (account deletion; came with stripe 0.0.10 on the old single model) | same name on `stripe_subscription_model` |
| `create_order_checkout_session` | `stripe_order_model->create_checkout_session` |
| `apply_order_checkout_session` | `stripe_order_model->apply_paid_session` |
| `_log_stripe_error` | `stripe_model->log_error` |
| `_public_stripe_fail` | `stripe_model->fail` |
| `_to_array` | `stripe_model->to_array` |

Removed: `_clear_entitlement` (no caller).