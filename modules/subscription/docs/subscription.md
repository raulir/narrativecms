# Subscription module

Reusable **subscription catalogue + pricing UI** on top of the **shop** product tree and **shop currencies**, with checkout via a **provider** (Stripe).

## Catalogue shape

| Shop entity | Role |
|-------------|------|
| **Category** `type=subscription` | One pricing catalogue |
| **Subcategory** | Plan name (Basic, Premium) |
| **Product** | One offer: free / month / year |
| **Currency** | `shop/currency` — heading (3-letter code), sign, rate |

## Product prices

- **`price`** — in shop **default currency**.
- **`prices` repeater** — optional rows: `currency_id`, `price`, **`stripe_price_id`**.
- Missing currency row → display = `price × currency.rate` (rounded 2 dp).

Resolver: `shop_model::get_product_price_in_currency($product, $currency_id)` → `{ price, formatted, stripe_price_id, … }`.

## Subscription product fields

`billing_interval` (`none` / `month` / `year`), `period_months`, `featured`, `features` (HTML).

## Settings (`subscription/subscription`)

Admin **Shop → Subscription settings**. Shared labels (monthly/yearly, CTA, errors, manage messages). Merged into pricing + manage via `merge_subscription_pricing_settings()`.

## Panel `subscription/pricing` — card model

Instance fields: **category**, **currencies**, **heading** only. Labels from settings.

Cards are **pre-built combinations**:

| Card type | Rule |
|-----------|------|
| Free | `billing_interval=none` and price 0 → **one** card, always visible |
| Paid | each product × each panel currency → **one** card |

Filters (period + currency) only show/hide cards. Free CTA → CMS **Free CTA link**. Paid CTA → login intent or checkout provider.

## Panel `subscription/manage` — My subscription

Instance: **category**, **currencies**, **heading**. Settings: labels + **My subscription page** link (checkout success return).

| View | UI |
|------|-----|
| `login` | Message + log in |
| `basic` | Status (Basic) + action area **Upgrade** — month+year cards, currency switcher |
| `premium` | Status (plan + renews/ends) + action area **Change** (when allowed) + **settings card** (auto-renew On/Off). **No currency switcher** — uses `meta.subscription.currency_id` |
| `premium` (elevated) | Status only (plan title from product). No auto-renew, payment portal, or change-plan |

Rules: **one subscription per user**. Month→year anytime (Stripe proration). Year→month only in last 30 days of period. Auto-renew = `cancel_at_period_end` inverted.

### Elevated plan (manual, no payment)

Admin **Site users** → field **Elevated plan** (FK `shop/product`, optional). Added by subscription extend of `user/user`.

| | |
|--|--|
| Source of truth | User panel field `elevated_plan` (not meta) |
| Access | `user_has_active_subscription()` = paid meta **or** elevated product exists |
| Precedence | Active Stripe meta wins; else elevated synthetic sub (`source=elevated`) |
| Clear | Set FK empty in admin. Stripe cancel does **not** clear elevated |
| Use | Testing / comps without Checkout |

Stripe webhook and `meta.subscription` stay separate so sync cannot wipe an elevated grant.

Ajax: `sync_subscription` (on load), `set_auto_renew`, `change_plan`. Checkout success prefers manage page link.

**Music user menu:** User header → **Subscription** link → this page.

## Checkout provider (`subscription_checkout`)

Mirrors **shop** cart’s `shop_checkout` pattern:

| Piece | Role |
|-------|------|
| Shop settings field **`subscription_checkout`** | Added by subscription extend of `shop/shop` |
| Stripe `provides.subscription_checkout` | `stripe/subscription_checkout` |
| FE entry | Always **`subscription/checkout_start`** (domain picks provider) |
| Guest Purchase | Session intent → login (subscription extends user login/register/google) → return → auto-start |
| Logged-in Purchase | `checkout_start` → provider → Stripe Checkout Session |

### Guest purchase / login intent

1. Guest CTA → ajax `set_checkout_intent` stores `product_id`, `currency_id`, and a **same-site path** `resume_url` (e.g. `/pricing/`) in `$_SESSION['subscription_checkout_intent']` (TTL 1h).
2. Redirect to login. `subscription` extends `user/login`, `user/register`, `user/auth_google` and sets `success_url` from `get_post_auth_redirect_url()` when intent is present.
3. After auth:
   - **No active subscription** → resume path (pricing) → `data-auto_checkout` → `checkout_start`.
   - **Already subscribed** (`meta.subscription.active`) → clear intent → normal user landing (`get_user_redirect_url()`, e.g. `/start/`) — no payment.
4. Resume URLs are sanitized: path-only or absolute same-host only; external hosts rejected (no open redirect). Full `https://…` values from older clients are normalized to path.
5. `checkout_start` also refuses checkout when already subscribed (returns `already_subscribed` + redirect).

Stripe Dashboard steps: [`../stripe/docs/stripe_checklist.md`](../stripe/docs/stripe_checklist.md).

Architecture follow-ups: [`stripe_vs_subscription_issues.md`](stripe_vs_subscription_issues.md).

### Storage after payment (webhook / manage)

Current entitlement lives in table **`subscription_subscription`** (schema JSON in this module), not user meta.

| Column | API key (helpers) | Meaning |
|--------|-------------------|---------|
| `user_id` | — | user panel id |
| `status` | `status` | Stripe status (`active`, `canceled`, …) / elevated |
| `active` | `active` | 0/1 entitlement |
| `billing_interval` | `interval` | month / year |
| `ends` | `ends` | period end ISO |
| `price_id` | `price_id` | Stripe price id |
| `product_id` | `product_id` | CMS product id |
| `plan` | `plan` | Optional plan title |
| `stripe_subscription_id` | `stripe_subscription_id` | Stripe sub id |
| `stripe_customer_id` | `stripe_customer_id` | Stripe customer |
| `currency_id` | `currency_id` | locked manage currency |
| `cancel_at_period_end` | `cancel_at_period_end` | auto-renew inverted |
| `archived` | — | `0` = current row; `1` = history when Stripe sub id changes |

Helpers still return the same array shape: `get_user_subscription()`, `merge_user_subscription()`, `user_has_active_subscription()`.

One-time migrate from old `meta.subscription`: `php grok/migrate_subscription_meta_to_table.php` (optional `--clear-meta`).

## Music theme

`music` extends `subscription/pricing` with SCSS only.

## Later

See [`todo.md`](todo.md).
