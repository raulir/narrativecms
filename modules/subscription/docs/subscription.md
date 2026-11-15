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
| Access | `user_has_active_subscription()` = paid Stripe row **or** elevated product exists |
| Precedence | Active Stripe row wins; else elevated synthetic sub (`source=elevated`) |
| Clear | Set FK empty in admin |
| Use | Testing / comps, or when Stripe no longer has the subscription |

Manage-page Stripe sync (and webhooks):

| Stripe | CMS |
|--------|-----|
| `canceled` / `incomplete_expired` / `customer.subscription.deleted` | Archive paid row. **No** elevated grant (natural end). |
| `resource_missing` / 404 (id in CMS, Stripe cannot confirm) | Same product → `elevated_plan`, archive paid row. |
| Network / other API error | No change. |

Checkout is blocked only for **paid** Stripe (`user_has_paid_subscription`); elevated users can buy again.

Manage UI for elevated: a card-sized banner (plan title + “administratively set”, 35.0rem, centred) then the Basic / purchase block.

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

The Android app can also pay with Google Play. That path is the `google` module, called from the app. Shop settings still have one checkout provider, Stripe. See [`../../google/docs/google.md`](../../google/docs/google.md).

### The installed web app

The web app (`scoretutor.com/app/`, pages from the in-app backend `app.scoretutor.com`) has `_cms_inapp` like the Android app, but no native bridge (`window.AppSplash`). Only the bridge decides the app path (Google Play choice, or the Stripe hand-off in the browser via `browser_open`). Without it, `pricing.js` pays as the website does:

- **Purchase** posts `subscription/checkout_start` with `app_root_url` = `location.origin + cms_app_root()` (e.g. `https://scoretutor.com/app/`), then goes to the Stripe Checkout address in the same window. A path answer (already subscribed) opens through `cms_position_goto`, so it stays in the web app.
- **Return addresses:** the backend's own addresses are on `app.scoretutor.com`, outside the web app. So `subscription_model::app_web_root()` accepts the posted root, and `app_web_rebase()` moves Stripe's success and cancel URLs (`get_checkout_return_urls($app_root)`) and the card-update portal return (`manage.js` → `action_update_payment_method`) to that root, e.g. `https://scoretutor.com/app/pricing/`. The shell reopens that page there (deep link).
- **Accepted roots:** the root is accepted only when its origin is in config `app_web_origins` (array). Without that key, the origin is `base_site` without its leading `app.` / `app_` label (`https://app.scoretutor.com` → `https://scoretutor.com`, `http://app_music.localhost` → `http://music.localhost`). The path must be plain segments ending in `/`. Any other root is ignored, and the addresses stay on the backend (e.g. `musicwrap.localhost`, unless listed).
- Change plan and auto-renew work in place. A Play-bought subscription still answers with the Play manage link wherever it is managed.
- **Success page:** the tab Stripe returns to is usually not the app window: a browser tab, a custom tab, or the external browser. It is not signed in, because the app's sign-in is its own (`app_session`). So with a web app root, success goes to the setting **Web app: checkout success page** (`app_success_link`) on the web app's origin, the website, e.g. `https://scoretutor.com/subscription-change/`. That is a public page (music: panel `music/subscription_change`, guest header and footer). It says the change went through, links back to the app, and offers the login CTA. Without the setting, success is the rebased manage link, as above.

### The app's plans page watches the checkout

In the app (`_cms_inapp`, the web app or the Android app), Purchase leaves the plans page open while Stripe runs elsewhere. `pricing.js` then watches (`pricing_watch_paid`):

- **Starts:**
  - in the web app, on the Stripe address;
  - in the Android app, before the Play / Stripe choice or the `browser_open` hand-off.
- **Asks:** `subscription/pricing` `cms_do=paid_status` → `{ok, paid}` (`user_has_paid_subscription`, a database read). It asks every 5 s, and at once when the page is visible again.
  - The webhook writes the payment, so a Stripe sync would not see it sooner: it needs `stripe_subscription_id`.
- **Paid:**
  - the notice `.pricing_paid_notice`, text from setting **App: payment received** (`paid_message`), on `body` so the page load under it keeps it;
  - **My subscription** opens, because it shows the new plan. Its path is `data-manage_url` on both containers (`subscription_model::manage_page_url()`): the visible page with a `subscription/manage` block, else the `manage_page_link` setting.
  - When My subscription is already the page shown, it loads again: `cms_position_goto(…, {history: 'none', reload: true})`.
- **Ends:**
  - when paid;
  - after 30 min;
  - when the page changes. The start time is kept in `localStorage` `subscription_checkout_started`, so the plans page or My subscription (both are `.pricing_container`) picks the watch up again within the 30 min.
- **Re-runs:** an app page can run `pricing.js` again, so `pricing_watch` keeps `window.pricing_watch` instead of resetting it.
- **My subscription and `pricing.js`:** the manage panel loads `pricing.js` too, for Purchase and the watch.
  - `pricing_init` leaves `.pricing_manage` to `manage.js`, which calls `pricing_purchase_click` and `pricing_watch_resume`. Before, both bound Purchase when both files were on the page, so one click started checkout twice.
  - `pricing_init` still resumes the watch there, whichever file runs last.
- **The success page** `/subscription-change/` makes no claim about the plan. Whoever lands on it is told to check the subscription page in the app or to log in.

Check:
- `grok/tmp/pwa_return_check.php` (CLI);
- `grok/tmp/pwa_payment_check.js` (Playwright, `MUSIC_TEST_PASS`: checkout, returns, the watch);
- `grok/tmp/subscription_change_check.js` (the public page, desktop and phone).

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
