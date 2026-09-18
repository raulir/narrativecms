# Subscription backlog

- [x] **Stripe Checkout** (subscription mode, no cart) via `subscription_checkout` provider + `prices[].stripe_price_id`
- [x] **Webhook** → user meta entitlement (`/stripe/webhook/` module API)
- [x] **User meta**: subscription fields + Stripe ids
- [x] Architecture 1–4: domain orchestration, entitlement in subscription, thin pricing, user extends (see [`stripe_vs_subscription_issues.md`](stripe_vs_subscription_issues.md))
- [ ] Architecture 7–9, 11–12: FE diagnostics, user-meta API, success UX, access/ads (same doc; 5–6 and 10 done)
- [ ] **Access / ads** — premium vs basic (read `meta.subscription.active`)
- [ ] **Billing intervals**: weekly, fortnightly
- [ ] Auto-detect visitor currency
- [ ] Rounding policy / marketing round helpers
- [ ] **Stock module**: add `stock` type via category extend
- [ ] Current plan state on pricing cards (logged-in)
- [ ] Auto-create Stripe Prices from CMS

## User subscription management page

Logged-in **manage subscription** UI (domain in subscription; Stripe implements provider actions). Also track provider side in [`stripe/docs/todo.md`](../../stripe/docs/todo.md).

- [x] **Page / panel shell** — `subscription/manage` (“My subscription”); settings labels; user menu link
- [x] **Basic → paid** — Upgrade action area; month+year side by side; checkout → manage page
- [x] **Has-plan UI** — status block + action area (Change / settings); locale dates
- [x] **Upgrade monthly → yearly** — Stripe subscription item update + proration (one sub per user)
- [x] **Year → month** — only in last 30 days of period
- [x] **Cancel auto-renew** — On/Off → `cancel_at_period_end`; access until period end
- [x] **Sync on load** — ajax recheck Stripe vs CMS meta; reload if changed
- [x] **Update payment method** — portal deep-link `flow_data.type=payment_method_update` (no cancel); login-style link under manage action area
- [x] **Arch cleanup A1–A5 / B1 B3–B5** — server-filtered manage cards + pricing_cards partial; thin manage panel; category validation; webhook secret gate; facts/`product_id`; settings trim; header premium helper
- [x] **Template partials + currency_selector** — `templates/pricing/card.tpl.php` (parent loops); `shop/currency_selector` panel (data-value + `#currency_selector_value`); trust-CMS templates; agents.md trust/parent-loop
- [x] **Elevated plan** — user admin FK `elevated_plan` → shop/product; premium without payment; manage status-only
- [x] **Lost Stripe sub → elevated** — only when Stripe **cannot confirm** (404 / `resource_missing`). Natural `canceled` / deleted archives with no grant. Domain owns convert; Stripe probe reports state.
- [ ] **CMS user item: subscription readout** — custom CMS input on `user/user` (list item page) showing current `subscription_subscription` (status, active, interval, ends, plan, Stripe ids). Read-only; not editable. Elevated plan field stays as-is.
- [ ] Deep-link from public pricing when already subscribed
- [ ] Optional user-facing email on manage changes (Stripe Dashboard + technical webhook cover ops)
