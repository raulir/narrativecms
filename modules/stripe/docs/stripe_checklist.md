# Stripe setup checklist (subscriptions)

Manual Dashboard steps for ScoreTutor / `subscription_checkout`. CMS stores Price ids only; the site creates Checkout Sessions via API.

## 1. Account and keys

1. Create or open a [Stripe](https://dashboard.stripe.com) account.
2. **Developers → API keys**
   - **Secret key** → site config `stripe_secret` (server only).
   - Publishable key is optional for Checkout Session (server-side redirect flow).
3. Work in **Test mode** until purchase works end-to-end; recreate/copy Price ids in Live when going live.

## 2. Products and Prices

For each **paid** shop product × **currency** sold on the pricing panel:

| CMS | Stripe Dashboard |
|-----|------------------|
| Product “Premium month” | Product e.g. **ScoreTutor Premium** (one Product can hold many Prices) |
| Interval month, currency GBP | **Price**: Recurring → **Monthly** → currency **GBP** → amount |
| Interval year, currency GBP | **Price**: Recurring → **Yearly** → currency **GBP** → amount |
| Same for EUR, USD, … | Separate Price per currency (and interval) |

Per Price:

1. **Product catalogue → Add product** (or open existing Premium).
2. **Add price** → Recurring → Monthly or Yearly → currency + amount.
3. Copy **Price ID** (`price_…`) into CMS: product → **Currency prices** → that currency → **Stripe price ID**.

**Free plan** (`billing_interval = none`, price 0): nothing in Stripe.

Example minimum (1 paid plan × 2 intervals × 3 currencies) = **6 Prices** (one shared Product is fine).

Not required for v1:

- Coupons for the “−18%” badge (display-only unless the yearly Price already reflects the discount).
- A Stripe Price for free.
- Shopify / cart setup for this path.

## 3. Checkout Sessions (site-created)

You do **not** create Checkout Sessions in the Dashboard. The provider creates them:

- `mode: subscription`
- `line_items: [{ price: price_…, quantity: 1 }]`
- `customer` (or email)
- `success_url` / `cancel_url`

## 4. Webhooks (entitlement)

1. **Developers → Webhooks → Add endpoint**  
   URL: `{base_url}stripe/webhook/`  
   (module API — not a CMS page, not `/stripe_webhook/` controller)  
   Example: `https://scoretutor.example/stripe/webhook/`
2. Events (recommended):
   - `checkout.session.completed` — first purchase via Checkout
   - `customer.subscription.created` — same handler as updated (belt-and-braces)
   - `customer.subscription.updated` — renewals / status / plan changes
   - `customer.subscription.deleted` — cancel / end
3. Copy **Signing secret** (`whsec_…`) into CMS:  
   **Shop → Stripe → Stripe settings** (`admin/panel_settings/stripe__stripe/`) → **Webhook signing secret**  
   (or host config `stripe_webhook_secret`).
4. Test with Stripe CLI:  
   `stripe listen --forward-to https://yoursite/stripe/webhook/`

Without webhooks, payment can succeed on Stripe while the site never grants Premium.

**Signing secret:** copy `whsec_…` into CMS Stripe settings. Outside DEV, unsigned webhook payloads are rejected.

## 5. Customer Portal (optional later)

**Settings → Billing → Customer portal** for manage/cancel. Not required for first purchase.

## 6. Test cards

Test mode: `4242 4242 4242 4242`, any future expiry, any CVC.

## 7. Site config after Stripe is ready

1. Host config: `"stripe_secret": "sk_test_…"` (or live).
2. CMS **Shop → Stripe → Stripe settings**: webhook signing secret `whsec_…`; optional **Checkout success** / **Checkout cancel** links; **Technical email** (CMS admin) + **Extra notification emails** for all handled webhook events.
3. Shop settings → **Subscription checkout provider** = Stripe subscription checkout.
4. Paste all `price_…` ids on product currency rows.
