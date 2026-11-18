# Stripe cart checkout (`stripe/checkout`)

One-off card payment for a shop cart, through a Stripe-hosted Checkout page (`mode: payment`). Subscriptions are a separate path: [`stripe_checklist.md`](stripe_checklist.md), `stripe/subscription_checkout`.

Guest checkout: no user account needed. Stripe collects card, email, phone, delivery address and the delivery method. Nothing card-related touches the site.

## Flow

```
cart.js Checkout button
  → shop/cart do=checkout_check            prices and stock re-checked; changed → cart shown again, stop
  → stripe/checkout do=shop_checkout       shop_model::checkout_check again (server side), then
        stripe_order_model::create_checkout_session   → { ok: 1, redirect: https://checkout.stripe.com/… }
  → buyer pays on Stripe
  → success: {base}stripe/checkout_return/?session_id=cs_…   (module API)
        stripe_order_model::apply_paid_session → shop_model::set_order_paid, clear cart cookie,
        redirect to Stripe settings → Checkout success (else home)
  → webhook checkout.session.completed      same apply (whichever comes first wins; the other does nothing)
  → cancel: Stripe settings → Checkout cancel (else home); the cart is unchanged
```

The cart stays a draft (`status` empty) until it is paid. A paid order is **Unfulfilled + Paid** and no longer the visitor's cart; the next add starts a new one.

## Pieces

| Piece | Role |
|-------|------|
| `config.json` `provides` | `shop_checkout` → `stripe/checkout` (pick it in Shop settings → **Checkout provider**) |
| `panels/checkout.php` | `cms_do=shop_checkout`: current order, `shop_model::checkout_check`, session. Returns `ok` and `redirect` only (`ajax.output`); errors go to the log. `cart.js` calls it with `get_ajax`, so no template is needed. |
| `stripe_order_model::create_checkout_session` | Session from `shop_model::get_payable_order` (shop.md § Payable order): one `price_data` line item per item line (price in pence, name = product + dims), `delivery_options` as shipping options, `metadata.cms_order_id` (session and payment intent), expiry 31 minutes. Expires the cart's earlier session first. Stores `stripe_session_id` and `stripe_checkout_fingerprint` on the order and holds the cart until the session expires. |
| `stripe_order_model::apply_paid_session` | Paid `mode: payment` session → `set_order_paid`. File lock per order (`tmp/stripe_order_{id}.lock`, deleted after a day), already paid → nothing. Re-reads the session with `shipping_cost.shipping_rate` expanded (delivery id), compares currency, item subtotal and the lines fingerprint; a mismatch only sets the order's **Payment note**. |
| `api/checkout_return.php` | Success URL. Marks paid at once (header count right on the thank-you page, works without webhook forwarding). Bad or unpaid session → cancel page. Never shows Stripe errors. |
| `definitions/shop_order.json` | `shop/order` extend: readonly **Checkout session**, **Checkout lines fingerprint** |

What `set_order_paid` stores and sends: [`shop/docs/shop.md`](../../shop/docs/shop.md) § Paid orders.

## Delivery

Every shown **Shop → Delivery methods** row (`shop/delivery`: name, price) is a Stripe shipping option, in list order, at most 5. Stripe preselects the first, so list order sets the default; the buyer may pick another; the paid order gets one delivery line for it. No rows = free delivery (no shipping options sent). Delivery sets and the tax multipliers of the old checkout are not used.

Delivery address countries: Stripe settings → **Delivery countries** (comma-separated ISO codes, default `GB`).

## Webhook events

Endpoint `{base}stripe/webhook/` (same as subscriptions). Sessions with `mode: payment` and `charge.refunded` go to the cart path; all others keep the subscription path.

| Event | Cart path |
|-------|-----------|
| `checkout.session.completed` | paid → order paid; unpaid (delayed payment methods) → nothing yet |
| `checkout.session.async_payment_succeeded` | order paid |
| `checkout.session.expired` | clears the order's `stripe_session_id` |
| `charge.refunded` | full or partial refund (Dashboard or API): the order with this payment intent (`payment_ref`) gets the cumulative `amount_refunded` through `shop_model::set_order_refund` (payment Partially refunded / Refunded, History, refund notice to the shop; order status unchanged). A charge with no shop order (e.g. a subscription invoice) is logged and skipped. |

The handler answers 500 when applying fails, so Stripe retries.

## Settings

- Host config `stripe_secret` (`sk_test_…` / `sk_live_…`). Empty: Checkout fails with the cart's **Checkout is not available** and a log line.
- Stripe settings: **Webhook signing secret**, **Checkout success** (e.g. an Order received page), **Checkout cancel**, **Delivery countries**.
- Shop settings: **Checkout provider** = Stripe checkout, **Notification emails**, order email subjects and intro.
- Stripe settings → **Cart checkout page** (`stripe_order_model::checkout_session_params`, which builds the session params without calling Stripe):
  - **Require terms acceptance** (default No) → `consent_collection.terms_of_service = required`; the tickbox text is **Terms tickbox text** with `{{terms_url}}` = **Terms page** (absolute). Stripe refuses a session with this until Dashboard → Settings → Public details has a **Terms of service URL**, so turn it on only after that is set.
  - **Note under Pay** (optional) → `custom_text.submit`. Messages are capped at 1200 characters.
- The session lives 31 minutes. The model calls `shop_model::hold_cart_until` for that time (plus 2 minutes), so the stock stays held while the buyer is on the Stripe page, whatever the Cart hold setting is.

## Testing locally

- In DEV, with no webhook signing secret set, the webhook accepts unsigned JSON from this machine only (loopback, not through a proxy), so a paid session can be simulated without Stripe: `ai/tmp/stripe_order_webhook_test.php` (creates and removes its own orders, delivery method and queued mails).
- With a test key: Checkout opens the Stripe page; pay with `4242 4242 4242 4242`. The return URL marks the order paid without webhook forwarding. For the webhook too: `stripe listen --forward-to http://david.localhost/stripe/webhook/` and put its `whsec_…` into Stripe settings.

## Not done

Refund button in the CMS (refunds are made in Stripe), VAT breakdown, more than 5 delivery methods, mixed-currency carts.
