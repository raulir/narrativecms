# Stripe backlog

Provider-side work for ScoreTutor subscriptions. Domain UX lives in [`subscription/docs/todo.md`](../../subscription/docs/todo.md).

## Site review 2026-11-20

Items with their evidence and fixes: `ai/next_big_plan.md`.

- [x] **Item 3. The checkout provider could be posted to directly** (done 2026-11-20). `stripe/subscription_checkout` runs only with `_provider_call`, which `subscription/checkout_start` sets and no request can carry; its definition has `ajax.input: ["cms"]`; it always returns an array (no print/exit). A direct post is refused and logged. "Already subscribed" and `validate_paid_product` stay in the domain, now the only way in. [`../../subscription/docs/stripe_vs_subscription_issues.md`](../../subscription/docs/stripe_vs_subscription_issues.md) § Provider contract. Check: `ai/tmp/payment_inputs_check.js`.
- [x] **Item 8. Webhook order and repeats** (done 2026-11-20; see Other below).
  - **Order:** subscription created / updated events re-read the subscription with `Subscription::retrieve` (`_current_subscription`), as invoices do; a failed read answers 500 and Stripe retries.
  - **Repeats:** each `event.id` is handled once (`stripe_model::webhook_event_claim`), so operator mails no longer repeat.
  - **Idempotency key:** a nonce per checkout start.

## User subscription management (provider)

Support the **manage subscription** page actions via Stripe (not Dashboard-only):

- [x] **Upgrade monthly → yearly** — `change_subscription_price_for_user` + proration; one item / one sub per user
- [x] **Cancel / resume auto-renew** — `set_auto_renew_for_user` → `cancel_at_period_end`
- [x] **Sync** — `sync_user_subscription` / retrieve for user
- [ ] **Customer Portal** alternative — hosted UI if we prefer it later
- [x] Period end from item-level `current_period_end` after upgrades/cancels

## Shop orders

- [x] **Refunds reach the order** (done 2026-11-17: `charge.refunded` → `stripe_order_model::charge_refunded` → `shop_model::set_order_refund`; tested with simulated webhooks, a real refund waits for the account) (shop e-commerce review 2026-11-16, item 3; [`shop/docs/todo.md`](../../shop/docs/todo.md)) — webhook `charge.refunded` (and partial refunds) → `payment_status` refunded / partially refunded, refunded amount, a history line through `shop_model::log_order_state`, an email to the shop.

## Other

- [ ] Live mode keys + Price ids checklist (see [`stripe_checklist.md`](stripe_checklist.md))
- [x] Harden webhook secret (reject unsigned in non-dev)
- [x] Webhook 5xx when entitlement apply fails (Stripe retries)
- [x] `error_log_user` + `cache/stripe.log` on Stripe errors; visitor JSON is generic
- [x] Invoice paid/failed webhooks → same entitlement sync
- [x] Checkout Session idempotency key: `sub_co_{user}_{price}_{currency}_{nonce}` since 2026-11-20 (the key without a nonce was kept by Stripe for 24 hours: a second start with other return addresses failed, a re-buy got the old completed session)
- [x] **Webhook events handled once** (2026-11-20, site review item 8): event ids in `tmp/stripe_webhook_events.json` (7 days); a repeat answers 200 `duplicate`, the same event in progress 409, a failed one is released for Stripe's retry. Subscription created / updated events re-read the subscription from Stripe. Checks: `ai/tmp/stripe_webhook_check.php`, `ai/tmp/stripe_webhook_signed_check.php`.
- [x] **`subscription_billing` provider** (2026-11-20, item 10): `stripe/subscription_billing` answers the subscription module's sync, auto-renew, stop renewal, card update, plan change, checkout links and absolute URL calls (`_provider_call` only).

## Cart checkout (one-off payments)

See [`shop_checkout.md`](shop_checkout.md).

- [x] `stripe/checkout` provides `shop_checkout`; webhook `mode: payment` path; return URL `stripe/checkout_return/` (2026-11-15)
- [ ] Refunds: `charge.refunded` → order `payment_status: refunded`
- [ ] First real test payment with a test key (no key on the dev host when this was built: session creation is untested against the API)

## Legacy

- [x] Removed `payment`, `payment_success`, `payment_hook_success` panels (templates, JS, SCSS, `payment.json`, **Payment settings** menu entry), `order.json` / `order.js` and the unused `test_payments` setting (2026-11-16): an older card-form subscription flow from another project, replaced by `subscription_checkout`.

## Review 2026-11-16

Full text: `ai/big_plan_2026-11-16.md`.

- [x] **Unsigned webhooks marked orders paid in DEV** (input trust, fixed 2026-11-16) — unsigned JSON is accepted only in DEV, with no signing secret configured, on a direct request from this machine (`REMOTE_ADDR` loopback, no `X-Forwarded-For`). With a secret set, every request needs a valid signature, also in DEV.
- [x] **`stripe_model` split (2026-11-16)** into `stripe_model` (core), `stripe_order_model`, `stripe_subscription_model`; the webhook dispatcher and `subscription_checkout` check the optional modules. See [`models.md`](models.md). **Other projects:** the subscription module must call `stripe/stripe_subscription_model` (rename list in `models.md`).
- [ ] `stripe_subscription_model` was moved without a subscription project to run it on: lint and call resolution were checked, the flows were not. Run a subscription checkout, a webhook and the manage actions on a project with the `subscription` module.
- [x] `stripe/checkout` returns an array (`ok`, `redirect` through `ajax.output`); old per-order lock files in `tmp/` are deleted after a day (2026-11-16).
